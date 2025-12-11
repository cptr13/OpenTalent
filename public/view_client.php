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

// ---- Handle primary contact selection / clear ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['primary_contact_id'])) {
    $newPrimaryId = (int)$_POST['primary_contact_id'];

    try {
        if ($newPrimaryId === 0) {
            // Clear primary contact
            $update = $pdo->prepare("UPDATE clients SET primary_contact_id = NULL WHERE id = ? LIMIT 1");
            $update->execute([(int)$client_id]);
            header("Location: view_client.php?id=" . (int)$client_id . "&msg=" . urlencode('Primary contact cleared.'));
            exit;
        } else {
            // Verify the contact actually belongs to this client
            $check = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND client_id = ? LIMIT 1");
            $check->execute([$newPrimaryId, (int)$client_id]);
            $validContactId = $check->fetchColumn();

            if ($validContactId) {
                $update = $pdo->prepare("UPDATE clients SET primary_contact_id = ? WHERE id = ? LIMIT 1");
                $update->execute([(int)$validContactId, (int)$client_id]);
                header("Location: view_client.php?id=" . (int)$client_id . "&msg=" . urlencode('Primary contact updated.'));
                exit;
            } else {
                $error = "Selected contact is not associated with this client.";
            }
        }
    } catch (Exception $e) {
        $error = "Failed to update primary contact.";
    }
}

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

// Candidate↔Job associations for this client with per-association status
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
            <div class="card h-100 client-card" data-card-id="company_info">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Company Info</span>
                    <a href="edit_client.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Industry:</strong> <?= h($client['industry']) ?></p>
                    <p><strong>Company Size:</strong> <?= h($client['company_size'] ?? '') ?></p>
                    <p><strong>Phone:</strong> <?= h($client['phone'] ?? '') ?></p>
                    <p>
                        <strong>Website:</strong>
                        <?php if (!empty($client['url'])): ?>
                            <a href="<?= h($client['url']) ?>" target="_blank" rel="noopener"><?= h($client['url']) ?></a>
                        <?php endif; ?>
                    </p>
                    <p>
                        <strong>LinkedIn:</strong>
                        <?php if (!empty($client['linkedin'])): ?>
                            <a href="<?= h($client['linkedin']) ?>" target="_blank" rel="noopener"><?= h($client['linkedin']) ?></a>
                        <?php endif; ?>
                    </p>
                    <p><strong>Location:</strong> <?= h($client['location']) ?></p>
                </div>
            </div>

            <div class="card mt-4 client-card" data-card-id="account_details">
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
                                <option value="Not a fit" <?= ($client['status'] ?? '') === 'Not a fit' ? 'selected' : '' ?>>Not a fit</option>
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
            <div class="card h-100 client-card" data-card-id="about">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>About</span>
                    <a href="edit_client.php?id=<?= (int)$client['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <!-- UPDATED: resizable About body -->
                <div class="card-body overflow-auto about-body">
                    <p><?= nl2br(h($client['about'])) ?></p>
                </div>
            </div>

            <?php if (!empty($primary_contact)): ?>
                <div class="card mt-4 client-card" data-card-id="primary_contact">
                    <div class="card-header">
                        <span>Primary Contact</span>
                    </div>
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
        $cardId = 'section_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($title));
    ?>
        <div class="card mb-4 client-card" data-card-id="<?= h($cardId) ?>">
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
                            <?php
                                $isPrimary = ($data['view'] === 'contact'
                                    && !empty($client['primary_contact_id'])
                                    && (int)$client['primary_contact_id'] === (int)($item['id'] ?? 0));
                            ?>
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
                                        <?php if ($data['view'] === 'contact' && $isPrimary): ?>
                                            <span class="badge bg-primary ms-2">Primary Contact</span>
                                        <?php endif; ?>
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
                                        <form method="POST" action="view_client.php?id=<?= (int)$client['id'] ?>" class="d-inline ms-2">
                                            <?php if ($isPrimary): ?>
                                                <input type="hidden" name="primary_contact_id" value="0">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                    Clear Primary
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="primary_contact_id" value="<?= (int)$item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    Set Primary
                                                </button>
                                            <?php endif; ?>
                                        </form>
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
    <div class="card mb-4 client-card" data-card-id="attachments">
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
                                    <li class="list-group-item d-flex justify-content-between	align-items-center">
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

    <div class="card mb-5 client-card" data-card-id="notes">
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

<style>
    /* Minimize / maximize */
    .client-card {
        cursor: grab;
    }
    .client-card .card-header {
        position: relative;
        padding-right: 2rem; /* room for toggle */
    }
    .client-card .card-toggle-btn {
        position: absolute;
        top: 50%;
        right: .5rem;
        transform: translateY(-50%);
        border: none;
        background: transparent;
        padding: 0;
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
        color: #666;
    }
    .client-card .card-toggle-btn:hover {
        color: #000;
    }
    .client-card.card-collapsed > .card-body,
    .client-card.card-collapsed > .list-group,
    .client-card.card-collapsed > .card-footer {
        display: none !important;
    }

    /* Drag styles */
    .client-card.dragging {
        opacity: 0.6;
        cursor: grabbing;
    }
    .client-card-dropzone {
        border: 2px dashed #0d6efd;
        border-radius: .5rem;
        height: 12px;
        margin: 4px 0;
    }

    /* Resizable About card body */
    .client-card[data-card-id="about"] .about-body {
        min-height: 150px;
        max-height: 800px;
        height: 340px;      /* default */
        resize: vertical;   /* draggable bottom-right corner */
        overflow: auto;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const CLIENT_ID = <?= (int)$client_id ?>;
    const ORDER_KEY_PREFIX = 'client_card_order_' + CLIENT_ID + '_';
    const STATE_KEY = 'client_card_state_' + CLIENT_ID;

    const allCards = Array.from(document.querySelectorAll('.client-card'));
    if (!allCards.length) return;

    // ---- State helpers ----
    function loadOrder(groupKey) {
        try {
            const raw = localStorage.getItem(groupKey);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }
    function saveOrder(groupKey, ids) {
        try {
            localStorage.setItem(groupKey, JSON.stringify(ids));
        } catch (e) {}
    }

    function loadState() {
        try {
            const raw = localStorage.getItem(STATE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }
    function saveState(map) {
        try {
            localStorage.setItem(STATE_KEY, JSON.stringify(map));
        } catch (e) {}
    }

    const collapseState = loadState();

    // ---- Group cards by parent (so each column/section reorders independently) ----
    const parentMap = new Map();
    allCards.forEach(card => {
        const parent = card.parentElement;
        if (!parent) return;
        if (!parentMap.has(parent)) parentMap.set(parent, []);
        parentMap.get(parent).push(card);
    });

    let groupIndex = 0;

    parentMap.forEach((cards, parent) => {
        const groupKey = ORDER_KEY_PREFIX + groupIndex;

        // Apply saved order for this group
        (function applySavedOrder() {
            const saved = loadOrder(groupKey);
            if (!saved || !saved.length) return;

            const map = {};
            cards.forEach(card => {
                const id = card.getAttribute('data-card-id');
                if (id) map[id] = card;
            });

            saved.forEach(id => {
                if (map[id]) {
                    parent.appendChild(map[id]);
                }
            });
        })();

        // Drag & drop for this group
        let dragCard = null;
        let dropZone = null;

        function createDropZone() {
            const dz = document.createElement('div');
            dz.className = 'client-card-dropzone';
            return dz;
        }

        function clearDropZone() {
            if (dropZone && dropZone.parentElement) {
                dropZone.parentElement.removeChild(dropZone);
            }
            dropZone = null;
        }

        cards.forEach(card => {
            card.setAttribute('draggable', 'true');

            card.addEventListener('dragstart', function (e) {
                dragCard = card;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragend', function () {
                card.classList.remove('dragging');
                dragCard = null;
                clearDropZone();

                // Save new order for this group
                const order = Array.from(parent.querySelectorAll('.client-card'))
                    .map(c => c.getAttribute('data-card-id'))
                    .filter(Boolean);
                saveOrder(groupKey, order);
            });

            card.addEventListener('dragover', function (e) {
                if (!dragCard || dragCard === card) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const rect = card.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                const before = e.clientY < midpoint;

                if (!dropZone) {
                    dropZone = createDropZone();
                }

                if (before) {
                    parent.insertBefore(dropZone, card);
                } else {
                    if (card.nextSibling) {
                        parent.insertBefore(dropZone, card.nextSibling);
                    } else {
                        parent.appendChild(dropZone);
                    }
                }
            });

            card.addEventListener('drop', function (e) {
                e.preventDefault();
                if (!dragCard || !dropZone) return;
                parent.insertBefore(dragCard, dropZone);
                clearDropZone();
            });
        });

        parent.addEventListener('dragover', function (e) {
            if (!dragCard) return;
            e.preventDefault();
        });
        parent.addEventListener('drop', function (e) {
            if (!dragCard) return;
            e.preventDefault();
            clearDropZone();
        });

        groupIndex++;
    });

    // ---- Minimize / maximize buttons + apply saved collapsed state ----
    allCards.forEach(card => {
        const id = card.getAttribute('data-card-id') || '';
        const header = card.querySelector('.card-header');
        if (!header) return;

        // Apply saved state
        if (id && collapseState[id] === true) {
            card.classList.add('card-collapsed');
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'card-toggle-btn';
        btn.setAttribute('aria-label', 'Toggle card');
        btn.textContent = card.classList.contains('card-collapsed') ? '+' : '−';

        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            card.classList.toggle('card-collapsed');
            const collapsed = card.classList.contains('card-collapsed');
            btn.textContent = collapsed ? '+' : '−';

            if (id) {
                collapseState[id] = collapsed;
                saveState(collapseState);
            }
        });

        header.appendChild(btn);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
