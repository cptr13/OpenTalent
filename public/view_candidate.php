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

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-warning'>Candidate not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

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
            $error = "Error saving note: " . htmlspecialchars($e->getMessage());
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

// Load job associations
$stmt = $pdo->prepare("SELECT j.title, j.id as job_id, a.status, a.id as association_id FROM associations a JOIN jobs j ON a.job_id = j.id WHERE a.candidate_id = ?");
$stmt->execute([$candidate_id]);
$associations = $stmt->fetchAll();
?>

<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></h2>
        <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Information</span>
                    <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Phone:</strong> <?= htmlspecialchars($candidate['phone'] ?? '') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($candidate['email'] ?? '') ?></p>
                    <p><strong>Secondary Email:</strong> <?= htmlspecialchars($candidate['secondary_email'] ?? '') ?></p>
                    <p><strong>LinkedIn:</strong> <?= htmlspecialchars($candidate['linkedin'] ?? '') ?></p>
                    <p><strong>Owner:</strong> <?= htmlspecialchars($candidate['owner'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Address</span>
                    <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Street:</strong> <?= htmlspecialchars($candidate['street'] ?? '') ?></p>
                    <p><strong>City:</strong> <?= htmlspecialchars($candidate['city'] ?? '') ?></p>
                    <p><strong>State:</strong> <?= htmlspecialchars($candidate['state'] ?? '') ?></p>
                    <p><strong>Zip:</strong> <?= htmlspecialchars($candidate['zip'] ?? '') ?></p>
                    <p><strong>Country:</strong> <?= htmlspecialchars($candidate['country'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Professional</div>
                <div class="card-body">
                    <p><strong>Current Job:</strong> <?= htmlspecialchars($candidate['current_job'] ?? '') ?></p>
                    <p><strong>Current Employer:</strong> <?= htmlspecialchars($candidate['current_employer'] ?? '') ?></p>
                    <p><strong>Current Pay:</strong> <?= htmlspecialchars($candidate['current_pay'] ?? '') ?></p>
                    <p><strong>Pay Type:</strong> <?= htmlspecialchars($candidate['current_pay_type'] ?? '') ?></p>
                    <p><strong>Expected Pay:</strong> <?= htmlspecialchars($candidate['expected_pay'] ?? '') ?></p>
                    <p><strong>Expected Pay Type:</strong> <?= htmlspecialchars($candidate['expected_pay_type'] ?? '') ?></p>
                    <p><strong>Experience (Years):</strong> <?= htmlspecialchars($candidate['experience_years'] ?? '') ?></p>
                    <p><strong>Source:</strong> <?= htmlspecialchars($candidate['source'] ?? '') ?></p>
                    <p><strong>Additional Info:</strong><br><?= nl2br(htmlspecialchars($candidate['additional_info'] ?? '')) ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
    <span>Resume Text</span>
    <a href="view_resume_text.php?id=<?= $candidate_id ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Fullscreen</a>
</div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($candidate['resume_filename'])): ?>
                        <p><strong>Resume File:</strong>
                            <a href="/uploads/resumes/<?= urlencode($candidate['resume_filename']) ?>" target="_blank">
                                <?= htmlspecialchars($candidate['resume_filename']) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <pre style="white-space: pre-wrap;"><?= htmlspecialchars($candidate['resume_text'] ?? '') ?></pre>
                </div>
            </div>
        </div>
    </div>

<!-- Remaining content (Attachments, Status & Roles, Notes) stays unchanged -->
<!-- You confirmed not to touch anything else -->

<!-- Attachments -->
<div class="row g-4 mt-3">
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header">Attachments</div>
            <div class="card-body">
                <ul class="list-group">
                    <?php
                    $attachments = [
                        'Resume' => $candidate['resume_filename'] ?? null,
                        'Formatted Resume' => $candidate['formatted_resume_filename'] ?? null,
                        'Cover Letter' => $candidate['cover_letter_filename'] ?? null,
                        'Other Attachment 1' => $candidate['other_attachment_1'] ?? null,
                        'Other Attachment 2' => $candidate['other_attachment_2'] ?? null,
                        'Contract' => $candidate['contract_filename'] ?? null
                    ];

                    foreach ($attachments as $label => $filename):
                        if (!empty($filename)):
                            $safeFile = htmlspecialchars($filename);
                            $url = "/uploads/resumes/" . urlencode($filename);
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong><?= $label ?>:</strong> <?= $safeFile ?></span>
                            <span>
                                <a href="<?= $url ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">View</a>
                                <a href="<?= $url ?>" download class="btn btn-sm btn-outline-secondary">Download</a>
                            </span>
                        </li>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>


    <!-- Status & Roles -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Status & Roles</span>
                    <a href="associate.php?candidate_id=<?= $candidate_id ?>" class="btn btn-sm btn-success">+ Associate Job</a>
                </div>
                <div class="card-body">
                    <?php if (empty($associations)): ?>
                        <p class="text-muted">No associated roles yet.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($associations as $a): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <a href="view_job.php?id=<?= $a['job_id'] ?>">
                                                <?= htmlspecialchars($a['title']) ?>
                                            </a>
                                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($a['status']) ?></span>
                                        </div>
                                        <a href="edit_association.php?id=<?= $a['association_id'] ?>" class="btn btn-sm btn-outline-primary">Edit Status</a>
                                    </div>
                                    <?php if (!empty($association_notes[$a['association_id']])): ?>
                                        <ul class="list-group list-group-flush mt-2 ms-3">
                                            <?php foreach ($association_notes[$a['association_id']] as $n): ?>
                                                <li class="list-group-item small text-muted"><?= nl2br(htmlspecialchars($n['content'])) ?></li>
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
                                <li class="list-group-item"><?= nl2br(htmlspecialchars($note['content'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
