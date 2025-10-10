<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/status_badge.php'; // <- for contact_status_badge()

$client_id = $_GET['id'] ?? null;

if (!$client_id) {
    echo "<div class='alert alert-danger'>No valid client ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Helpers ----
function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function label_item(array $item): string {
    $first = trim((string)($item['first_name'] ?? ''));
    $last  = trim((string)($item['last_name'] ?? ''));
    $nameCombo = trim("$first $last");
    if ($nameCombo !== '') return $nameCombo;
    if (!empty($item['name']))  return (string)$item['name'];
    if (!empty($item['title'])) return (string)$item['title'];
    return '';
}
// List files helper (under uploads/clients/<id>/<category>)
function list_client_files(int $clientId, string $category): array {
    $baseDir = realpath(__DIR__ . '/..'); // project root
    if ($baseDir === false) return [];
    $dir = rtrim($baseDir, '/\\') . "/uploads/clients/{$clientId}/{$category}/";
    $files = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
            $path = $dir . $f;
            if (is_file($path)) {
                $files[] = $f;
            }
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    }
    return $files;
}

// ---- File UI helpers ----
function is_viewable_inline(string $ext): bool {
    return in_array(strtolower($ext), ['pdf','png','jpg','jpeg','gif','webp'], true);
}
function file_icon(string $ext): string {
    $ext = strtolower($ext);
    if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) return '🖼️';
    if ($ext === 'pdf') return '📕';
    if (in_array($ext, ['doc','docx'])) return '📝';
    if (in_array($ext, ['xls','xlsx','csv'])) return '📊';
    if (in_array($ext, ['ppt','pptx'])) return '📽️';
    if (in_array($ext, ['txt','rtf'])) return '📄';
    return '📎';
}
function format_bytes(int $bytes): string {
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return sprintf('%.1f %s', $bytes, $units[$i]);
}

// ---- Universal file link helpers ----
function build_file_view_url(string $relativePath): string {
    return 'file_view.php?path=' . urlencode($relativePath);
}
function build_file_download_url(string $relativePath): string {
    return 'file_download.php?path=' . urlencode($relativePath);
}
function build_file_delete_url(string $relativePath, string $returnUrl): string {
    return 'file_delete.php?path=' . urlencode($relativePath) . '&return=' . urlencode($returnUrl);
}
function render_file_actions_strict(string $relativePath): string {
    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    $btns = '';
    if (is_viewable_inline($ext)) {
        $btns .= '<a class="btn btn-sm btn-outline-primary me-2" target="_blank" href="' . build_file_view_url($relativePath) . '">View</a>';
    }
    $btns .= '<a class="btn btn-sm btn-outline-secondary me-2" href="' . build_file_download_url($relativePath) . '">Download</a>';
    $btns .= '<a class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this file?\')" href="' . build_file_delete_url($relativePath, $_SERVER['REQUEST_URI']) . '">Delete</a>';
    return $btns;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([(int)$client_id]);
$client = $stmt->fetch();

if (!$client) {
    echo "<div class='alert alert-warning'>Client not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$error = '';

// Save note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    $note = trim($_POST['note']);
    if ($note !== '') {
        $stmt = $pdo->prepare("INSERT INTO notes (content, module_type, module_id, created_at) VALUES (?, 'client', ?, NOW())");
        $stmt->execute([$note, (int)$client_id]);
        header("Location: view_client.php?id=$client_id&msg=Note+added+successfully");
        exit;
    } else {
        $error = "Note cannot be empty.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE module_type = 'client' AND module_id = ? ORDER BY created_at DESC");
$stmt->execute([(int)$client_id]);
$notes = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT n.*, c.name AS candidate_name, j.title AS job_title
    FROM notes n
    JOIN associations a ON n.module_type = 'association' AND n.module_id = a.id
    JOIN jobs j ON a.job_id = j.id
    JOIN candidates c ON a.candidate_id = c.id
    WHERE j.client_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([(int)$client_id]);
$association_notes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE client_id = ?");
$stmt->execute([(int)$client_id]);
$jobs = $stmt->fetchAll();

// Contacts (include contact_status)
$stmt = $pdo->prepare("SELECT id, first_name, last_name, title, email, contact_status FROM contacts WHERE client_id = ?");
$stmt->execute([(int)$client_id]);
$contacts = $stmt->fetchAll();

// NEW: Candidate↔Job associations for this client with per-association status
$stmt = $pdo->prepare("
    SELECT 
        a.id            AS association_id,
        c.id            AS candidate_id,
        c.first_name    AS candidate_first,
        c.last_name     AS candidate_last,
        j.id            AS job_id,
        j.title         AS job_title,
        a.status        AS assoc_status
    FROM associations a
    JOIN candidates c ON c.id = a.candidate_id
    JOIN jobs j       ON j.id = a.job_id
    WHERE j.client_id = ?
    ORDER BY a.created_at DESC, a.id DESC
");
$stmt->execute([(int)$client_id]);
$candidate_associations = $stmt->fetchAll();

$primary_contact = null;
if (!empty($client['primary_contact_id'])) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, title, email FROM contacts WHERE id = ?");
    $stmt->execute([(int)$client['primary_contact_id']]);
    $primary_contact = $stmt->fetch();
}

$flash_message = $_GET['msg'] ?? null;

// Categories for client documents
$docCategories = [
    'contracts' => 'Contracts',
    'benefits'  => 'Benefits Info',
    'info'      => 'Client Info',
    'misc'      => 'Misc Attachments'
];
?>

<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= h($flash_message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= h($client['name']) ?></h2>
        <div>
            <a href="edit_client.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-primary me-2">Edit Client</a>
            <a href="delete_client.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete Client</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 d-flex flex-column">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Company Info</span>
                    <a href="edit_client.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Industry:</strong> <?= h($client['industry']) ?></p>
                    <p><strong>Phone:</strong> <?= h($client['phone'] ?? '') ?></p>
                    <p>
                        <strong>Website:</strong>
                        <?php if (!empty($client['url'])): ?>
                            <a href="<?= h($client['url']) ?>" target="_blank" rel="noopener"><?= h($client['url']) ?></a>
                        <?php endif; ?>
                    </p>
                    <p><strong>Location:</strong> <?= h($client['location']) ?></p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">Account Details</div>
                <div class="card-body">
                    <form method="POST" action="update_client_status.php" class="row g-2 align-items-center mb-3">
                        <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                        <div class="col-auto">
                            <label class="col-form-label"><strong>Status:</strong></label>
                        </div>
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm">
                                <option value="Lead" <?= ($client['status'] ?? '') === 'Lead' ? 'selected' : '' ?>>Lead</option>
                                <option value="Prospect" <?= ($client['status'] ?? '') === 'Prospect' ? 'selected' : '' ?>>Prospect</option>
                                <option value="Customer" <?= ($client['status'] ?? '') === 'Customer' ? 'selected' : '' ?>>Customer</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                        </div>
                    </form>
                    <p><strong>Account Manager:</strong> <?= h($client['account_manager']) ?></p>
                    <p><strong>Created At:</strong> <?= h($client['created_at']) ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6 d-flex flex-column">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>About</span>
                    <a href="edit_client.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body overflow-auto" style="max-height: 340px;">
                    <p><?= nl2br(h($client['about'])) ?></p>
                </div>
            </div>

            <?php if (!empty($primary_contact)): ?>
                <div class="card mt-4">
                    <div class="card-header">Primary Contact</div>
                    <div class="card-body">
                        <h5>
                            <a href="view_contact.php?id=<?= (int)$primary_contact['id'] ?>" class="text-decoration-none">
                                <?= h(trim(($primary_contact['first_name'] ?? '') . ' ' . ($primary_contact['last_name'] ?? ''))) ?>
                            </a>
                        </h5>
                        <?php if (!empty($primary_contact['title'])): ?>
                            <p><strong>Title:</strong> <?= h($primary_contact['title']) ?></p>
                        <?php endif; ?>
                        <p><strong>Email:</strong> <?= h($primary_contact['email']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Build sections; Associated Candidates now uses per-association rows with status and job title.
    $sections = [
        'Associated Contacts'   => ['items' => $contacts,   'url' => "add_contact.php?client_id=".(int)$client['id'], 'label' => '+ Associate Contact',  'view' => 'contact'],
        'Associated Job Orders' => ['items' => $jobs,       'url' => "add_job.php?client_id=".(int)$client['id']."&client_name=" . urlencode((string)$client['name']), 'label' => '+ Create Job Order', 'view' => 'job'],
        'Associated Candidates' => ['items' => $candidate_associations, 'url' => "assign.php?client_id=".(int)$client['id'], 'label' => '+ Associate Candidate', 'view' => 'candidate_assoc'],
    ];
    foreach ($sections as $title => $data):
    ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= h($title) ?></span>
                <a href="<?= h($data['url']) ?>" class="btn btn-sm btn-outline-secondary"><?= h($data['label']) ?></a>
            </div>
            <div class="card-body">
                <?php if (empty($data['items'])): ?>
                    <p>No <?= strtolower(h($title)) ?> found for this client.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($data['items'] as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="text-truncate">
                                    <?php if ($data['view'] === 'candidate_assoc'): ?>
                                        <!-- Candidate Name (link) • Job Title (link) -->
                                        <a href="view_candidate.php?id=<?= (int)$item['candidate_id'] ?>">
                                            <strong><?= h(trim(($item['candidate_first'] ?? '') . ' ' . ($item['candidate_last'] ?? ''))) ?></strong>
                                        </a>
                                        <span class="text-muted"> • </span>
                                        <a href="view_job.php?id=<?= (int)$item['job_id'] ?>" class="text-decoration-none">
                                            <?= h($item['job_title'] ?? '') ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="view_<?= h($data['view']) ?>.php?id=<?= (int)$item['id'] ?>">
                                            <strong><?= h(label_item($item)) ?></strong>
                                        </a>
                                        <?php if ($data['view'] === 'contact' && !empty($item['title'])): ?>
                                            <span class="text-muted ms-1">— <?= h($item['title']) ?></span>
                                        <?php elseif (!empty($item['title'])): ?>
                                            <div class="text-muted small"><?= h($item['title']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="text-nowrap">
                                    <?php if ($data['view'] === 'contact'): ?>
                                        <?= contact_status_badge($item['contact_status'] ?? null, 'sm') ?>
                                    <?php elseif ($data['view'] === 'candidate_assoc'): ?>
                                        <?= contact_status_badge($item['assoc_status'] ?? null, 'sm') ?>
                                    <?php else: ?>
                                        <?php if (!empty($item['status'])): ?>
                                            <span class="badge bg-secondary"><?= h($item['status']) ?></span>
                                        <?php elseif (!empty($item['email'])): ?>
                                            <span><?= h($item['email']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Attachments Card -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Client Attachments</span>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <?php foreach ($docCategories as $key => $label): ?>
                    <div class="col-md-6">
                        <h6 class="mb-2"><?= h($label) ?></h6>
                        <form class="d-flex align-items-center gap-2 mb-2" method="POST" action="upload_client_document.php" enctype="multipart/form-data">
                            <input type="hidden" name="client_id" value="<?= (int)$client_id ?>">
                            <input type="hidden" name="category" value="<?= h($key) ?>">
                            <input type="file" name="doc" class="form-control form-control-sm" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Upload</button>
                        </form>

                        <?php
                            $files = list_client_files((int)$client_id, $key);
                            if (empty($files)):
                        ?>
                            <div class="text-muted small">No files uploaded.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-sm">
                                <?php
                                $projectRoot = realpath(__DIR__ . '/..');
                                $uploadsRoot = $projectRoot . '/uploads';
                                foreach ($files as $f):
                                    $relative = "clients/{$client_id}/{$key}/{$f}";
                                    $abs = $uploadsRoot . '/' . $relative;
                                    $size = is_file($abs) ? format_bytes((int)filesize($abs)) : '—';
                                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                    $icon = file_icon($ext);
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span class="text-truncate" style="max-width: 70%;">
                                            <?= $icon ?> <?= h($f) ?>
                                            <span class="text-muted ms-2 small">(<?= h($size) ?>)</span>
                                        </span>
                                        <span class="d-flex gap-2">
                                            <?= render_file_actions_strict($relative) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="form-text mt-3">
                Allowed types: pdf, doc, docx, rtf, txt, xls, xlsx, csv, ppt, pptx, png, jpg, jpeg, webp. Max 15MB.
            </div>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <form method="POST" class="mb-3">
                <textarea name="note" class="form-control" rows="3" placeholder="Add a note..." required></textarea>
                <button type="submit" class="btn btn-sm btn-success mt-2">Save Note</button>
            </form>

            <?php if (empty($notes) && empty($association_notes)): ?>
                <p class="text-muted">No notes found.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notes as $note): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?> (Client)</small><br>
                                    <?= nl2br(h($note['content'])) ?>
                                </div>
                                <div class="d-flex align-items-start gap-2 mt-2">
                                    <a href="edit_note.php?id=<?= (int)$note['id'] ?>&client_id=<?= (int)$client_id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="delete_note.php?id=<?= (int)$note['id'] ?>&return=client&id_return=<?= (int)$client_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($association_notes as $note): ?>
                        <li class="list-group-item">
                            <div>
                                <small class="text-muted">
                                    <?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?>
                                    (Association: <?= h($note['candidate_name']) ?> → <?= h($note['job_title']) ?>)
                                </small><br>
                                <?= nl2br(h($note['content'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
