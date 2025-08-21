<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$statusList = require __DIR__ . '/../config/status_list.php';

$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    echo "<div class='alert alert-danger'>Invalid candidate ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Helpers ----
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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
function build_file_view_url(string $relativePath): string {
    return 'file_view.php?path=' . urlencode($relativePath);
}
function build_file_download_url(string $relativePath): string {
    return 'file_download.php?path=' . urlencode($relativePath);
}
// Unified delete URLs (file_delete.php)
function build_delete_only_url(string $relativePath, string $returnUrl): string {
    return 'file_delete.php?mode=delete&path=' . urlencode($relativePath) . '&return=' . urlencode($returnUrl);
}
function build_delete_both_url(string $relativePath, int $candidateId, string $field, string $returnUrl): string {
    // Deletes file AND clears DB field
    return 'file_delete.php?mode=both&path=' . urlencode($relativePath)
        . '&candidate_id=' . $candidateId
        . '&field=' . urlencode($field)
        . '&return=' . urlencode($returnUrl);
}
function build_clear_only_url(int $candidateId, string $field, string $returnUrl): string {
    // Clears DB field only
    return 'file_delete.php?mode=clear&candidate_id=' . $candidateId
        . '&field=' . urlencode($field)
        . '&return=' . urlencode($returnUrl);
}
function render_file_actions_strict(string $relativePath, string $deleteUrl): string {
    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    $btns = '';
    if (is_viewable_inline($ext)) {
        $btns .= '<a class="btn btn-sm btn-outline-primary me-2" target="_blank" href="' . build_file_view_url($relativePath) . '">View</a>';
    }
    $btns .= '<a class="btn btn-sm btn-outline-secondary me-2" href="' . build_file_download_url($relativePath) . '">Download</a>';
    $btns .= '<a class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete file from disk and clear DB?\')" href="' . $deleteUrl . '">Delete</a>';
    return $btns;
}

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-warning'>Candidate not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Email compose link (uses compose_email.php) ----
$full_name = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
$return_to = 'view_candidate.php?id=' . (int)$candidate['id'];
$compose_params = [
    'to'           => $candidate['email'] ?? '',
    'name'         => $full_name,
    'related_type' => 'candidate',
    'related_id'   => (int)$candidate['id'],
    'return_to'    => $return_to,
];
$email_url = 'compose_email.php?' . http_build_query($compose_params);

$error = '';
$flash_message = $_GET['msg'] ?? null;

// Save note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (content, module_type, module_id, created_at) VALUES (?, 'candidate', ?, NOW())");
            $stmt->execute([$content, $candidate_id]);
            header("Location: view_candidate.php?id=$candidate_id&msg=Note+added+successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error saving note: " . h($e->getMessage());
        }
    } else {
        $error = "Note cannot be empty.";
    }
}

// Load notes
$stmt = $pdo->prepare("
    SELECT * FROM notes 
    WHERE 
        (module_type = 'candidate' AND module_id = ?) 
        OR 
        (module_type = 'association' AND module_id IN (
            SELECT id FROM associations WHERE candidate_id = ?
        ))
    ORDER BY created_at DESC
");
$stmt->execute([$candidate_id, $candidate_id]);
$all_notes = $stmt->fetchAll();

$candidate_notes = [];
$association_notes = [];
foreach ($all_notes as $note) {
    if ($note['module_type'] === 'association') {
        $association_notes[(int)$note['module_id']][] = $note;
    } else {
        $candidate_notes[] = $note;
    }
}

// Load job associations WITH contacts + client
$stmt = $pdo->prepare("
    SELECT 
        a.id AS association_id,
        a.status,
        j.id AS job_id,
        j.title,
        cl.id AS client_id,
        cl.name AS client_name,
        GROUP_CONCAT(
            CONCAT(
                c.id, ':',
                COALESCE(c.full_name, CONCAT(TRIM(c.first_name), ' ', TRIM(c.last_name)))
            )
            ORDER BY c.last_name, c.first_name
            SEPARATOR ','
        ) AS contact_pairs
    FROM associations a
    JOIN jobs j           ON a.job_id = j.id
    LEFT JOIN clients cl  ON j.client_id = cl.id
    LEFT JOIN job_contacts jc ON jc.job_id = j.id
    LEFT JOIN contacts c  ON c.id = jc.contact_id
    WHERE a.candidate_id = ?
    GROUP BY a.id, a.status, j.id, j.title, cl.id, cl.name
    ORDER BY a.created_at DESC, j.title ASC
");
$stmt->execute([$candidate_id]);
$associations = $stmt->fetchAll();
?>

<!-- Local tweaks -->
<style>
  .assoc-row { line-height: 1; gap: .5rem; }
  .assoc-sep { color: #6c757d; }
  .assoc-link { white-space: nowrap; }
</style>

<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= h($flash_message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?= h(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? '')) ?></h2>
        <div class="d-flex gap-2">
            <?php if (!empty($candidate['email'])): ?>
                <a href="<?= h($email_url) ?>" class="btn btn-sm btn-outline-primary">Email</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="No email on file">Email</button>
            <?php endif; ?>
            <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Information</span>
                    <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Phone:</strong> <?= h($candidate['phone'] ?? '') ?></p>
                    <p><strong>Email:</strong> <?= h($candidate['email'] ?? '') ?></p>
                    <p><strong>Secondary Email:</strong> <?= h($candidate['secondary_email'] ?? '') ?></p>
                    <p><strong>LinkedIn:</strong> <?= h($candidate['linkedin'] ?? '') ?></p>
                    <p><strong>Owner:</strong> <?= h($candidate['owner'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Address</span>
                    <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Street:</strong> <?= h($candidate['street'] ?? '') ?></p>
                    <p><strong>City:</strong> <?= h($candidate['city'] ?? '') ?></p>
                    <p><strong>State:</strong> <?= h($candidate['state'] ?? '') ?></p>
                    <p><strong>Zip:</strong> <?= h($candidate['zip'] ?? '') ?></p>
                    <p><strong>Country:</strong> <?= h($candidate['country'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Resume Text</span>
                    <a href="view_resume_text.php?id=<?= urlencode($candidate_id) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Fullscreen</a>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($candidate['resume_filename'])): ?>
                        <?php
                            $projectRoot = realpath(__DIR__ . '/..');
                            $uploadsRoot = $projectRoot . '/uploads';
                            $rel = 'resumes/' . $candidate['resume_filename'];
                            $abs = $uploadsRoot . '/' . $rel;
                            if (is_file($abs)) {
                                $size = format_bytes((int)filesize($abs));
                                $ext  = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                                $icon = file_icon($ext);
                                $deleteUrl = build_delete_both_url($rel, (int)$candidate_id, 'resume_filename', $_SERVER['REQUEST_URI']);
                            ?>
                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <span><strong>Resume File:</strong> <?= $icon ?> <?= h($candidate['resume_filename']) ?> <span class="text-muted small ms-2">(<?= h($size) ?>)</span></span>
                                    <span class="d-flex gap-2">
                                        <?= render_file_actions_strict($rel, $deleteUrl) ?>
                                    </span>
                                </div>
                            <?php } else { ?>
                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <span><strong>Resume File:</strong> <em class="text-muted">missing on disk</em> (<?= h($candidate['resume_filename']) ?>)</span>
                                    <a class="btn btn-sm btn-outline-dark" onclick="return confirm('Clear database reference?')"
                                       href="<?= h(build_clear_only_url((int)$candidate_id, 'resume_filename', $_SERVER['REQUEST_URI'])) ?>">Clear</a>
                                </div>
                            <?php } ?>
                    <?php endif; ?>
                    <pre style="white-space: pre-wrap;"><?= h($candidate['resume_text'] ?? '') ?></pre>
                </div>
            </div>
        </div>
    </div>

<!-- Attachments -->
<div class="row g-4 mt-3">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">Attachments</div>
            <div class="card-body">
                <ul class="list-group">
                    <?php
                    // Map: label => [field, folder]
                    $attachments = [
                        'Resume'             => ['resume_filename',            'resumes/'],
                        'Formatted Resume'   => ['formatted_resume_filename', 'resumes/'],
                        'Cover Letter'       => ['cover_letter_filename',     'resumes/'],
                        'Other Attachment 1' => ['other_attachment_1',        'resumes/'],
                        'Other Attachment 2' => ['other_attachment_2',        'resumes/'],
                        'Contract'           => ['contract_filename',         'resumes/'],
                    ];
                    $projectRoot = realpath(__DIR__ . '/..');
                    $uploadsRoot = $projectRoot . '/uploads';

                    foreach ($attachments as $label => [$field, $folder]):
                        $filename = $candidate[$field] ?? null;
                        if (empty($filename)) continue;

                        $rel = $folder . $filename;
                        $abs = $uploadsRoot . '/' . $rel;

                        if (!is_file($abs)) {
                            // Ghost reference: show clear action
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong><?= h($label) ?>:</strong> <em class="text-muted">missing on disk</em> (<?= h($filename) ?>)</span>
                                <a class="btn btn-sm btn-outline-dark" onclick="return confirm('Clear database reference?')"
                                   href="<?= h(build_clear_only_url((int)$candidate_id, $field, $_SERVER['REQUEST_URI'])) ?>">Clear</a>
                            </li>
                            <?php
                            continue;
                        }

                        $size = format_bytes((int)filesize($abs));
                        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $icon = file_icon($ext);
                        $deleteUrl = build_delete_both_url($rel, (int)$candidate_id, $field, $_SERVER['REQUEST_URI']);
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong><?= h($label) ?>:</strong> <?= $icon ?> <?= h($filename) ?> <span class="text-muted small ms-2">(<?= h($size) ?>)</span></span>
                            <span class="d-flex gap-2">
                                <?= render_file_actions_strict($rel, $deleteUrl) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

    <!-- Status & Roles -->
    <?php
    // Load associations (same as before)
    $stmt = $pdo->prepare("
        SELECT 
            a.id AS association_id,
            a.status,
            j.id AS job_id,
            j.title,
            cl.id AS client_id,
            cl.name AS client_name,
            GROUP_CONCAT(
                CONCAT(
                    c.id, ':',
                    COALESCE(c.full_name, CONCAT(TRIM(c.first_name), ' ', TRIM(c.last_name)))
                )
                ORDER BY c.last_name, c.first_name
                SEPARATOR ','
            ) AS contact_pairs
        FROM associations a
        JOIN jobs j           ON a.job_id = j.id
        LEFT JOIN clients cl  ON j.client_id = cl.id
        LEFT JOIN job_contacts jc ON jc.job_id = j.id
        LEFT JOIN contacts c  ON c.id = jc.contact_id
        WHERE a.candidate_id = ?
        GROUP BY a.id, a.status, j.id, j.title, cl.id, cl.name
        ORDER BY a.created_at DESC, j.title ASC
    ");
    $stmt->execute([$candidate_id]);
    $associations = $stmt->fetchAll();
    ?>

    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Status & Roles</span>
                    <a href="associate.php?candidate_id=<?= urlencode($candidate_id) ?>" class="btn btn-sm btn-success">+ Associate Job</a>
                </div>
                <div class="card-body">
                    <?php if (empty($associations)): ?>
                        <p class="text-muted">No associated roles yet.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($associations as $a): ?>
                                <?php
                                $contactLinks = [];
                                if (!empty($a['contact_pairs'])) {
                                    $pairs = explode(',', $a['contact_pairs']);
                                    foreach ($pairs as $pair) {
                                        $parts = explode(':', $pair, 2);
                                        if (count($parts) === 2) {
                                            $cid = (int)$parts[0];
                                            $cname = trim($parts[1]);
                                            if ($cid && $cname !== '') {
                                                $contactLinks[] = '<a href="view_contact.php?id=' . $cid . '" class="assoc-link">' . h($cname) . '</a>';
                                            }
                                        }
                                    }
                                }
                                ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="assoc-row d-flex flex-wrap align-items-center">
                                            <a href="view_job.php?id=<?= (int)$a['job_id'] ?>" class="assoc-link"><?= h($a['title']) ?></a>
                                            <span class="assoc-sep">•</span>
                                            <?= !empty($contactLinks) ? implode(', ', $contactLinks) : '<span class="text-muted">No contact</span>' ?>
                                            <span class="assoc-sep">•</span>
                                            <?= !empty($a['client_id']) ? '<a href="view_client.php?id='.(int)$a['client_id'].'" class="assoc-link">'.h($a['client_name'] ?? 'Company').'</a>' : '<span class="text-muted">No company</span>' ?>
                                            <span class="assoc-sep">•</span>
                                            <span class="badge rounded-pill bg-secondary text-white"><?= h($a['status']) ?></span>
                                        </div>
                                        <a href="edit_association.php?id=<?= (int)$a['association_id'] ?>" class="btn btn-sm btn-outline-primary">Edit Status</a>
                                    </div>
                                    <?php if (!empty($association_notes[$a['association_id']])): ?>
                                        <ul class="list-group list-group-flush mt-2 ms-3">
                                            <?php foreach ($association_notes[$a['association_id']] as $n): ?>
                                                <li class="list-group-item small text-muted"><?= nl2br(h($n['content'])) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Notes</div>
                <div class="card-body">
                    <form method="post" class="mb-3">
                        <div class="mb-2">
                            <textarea name="content" class="form-control" rows="3" placeholder="Add a note..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Add Note</button>
                    </form>

                    <?php if (empty($candidate_notes)): ?>
                        <p class="text-muted">No notes yet.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($candidate_notes as $note): ?>
                                <li class="list-group-item"><?= nl2br(h($note['content'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
