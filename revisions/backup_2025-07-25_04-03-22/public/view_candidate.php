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

// Save a new note
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
                    <p><strong>Phone:</strong> <?= htmlspecialchars($candidate['phone']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($candidate['email']) ?></p>
                    <p><strong>Secondary Email:</strong> <?= htmlspecialchars($candidate['secondary_email']) ?></p>
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

    <div class="card my-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Status & Roles</span>
            <a href="associate.php?candidate_id=<?= $candidate_id ?>&return=view_candidate.php?id=<?= $candidate_id ?>" class="btn btn-sm btn-outline-success">Associate Job</a>
        </div>
        <div class="card-body">
            <?php if ($associations): ?>
                <?php foreach ($associations as $assoc): ?>
                    <div class="d-flex justify-content-between align-items-start border rounded p-2 mb-3">
                        <div class="flex-grow-1">
                            <a href="view_job.php?id=<?= $assoc['job_id'] ?>" class="fw-bold d-block">
                                <?= htmlspecialchars($assoc['title']) ?>
                            </a>
                        </div>
                        <div class="d-flex flex-column align-items-end">
                            <span class="badge bg-info mb-2"><?= htmlspecialchars($assoc['status']) ?></span>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= $assoc['association_id'] ?>">Change Status</button>
                                <a href="delete_association.php?association_id=<?= $assoc['association_id'] ?>&candidate_id=<?= $candidate_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this candidate from the job?');">🗑️</a>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($association_notes[$assoc['association_id']])): ?>
                        <div class="mb-3 ms-3">
                            <h6 class="fw-bold">Notes:</h6>
                            <ul class="list-group">
                                <?php foreach ($association_notes[$assoc['association_id']] as $note): ?>
                                    <li class="list-group-item small">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?></small><br>
                                                <?= nl2br(htmlspecialchars($note['content'])) ?>
                                            </div>
                                            <div class="d-flex align-items-start gap-2 mt-2">
                                                <a href="edit_note.php?id=<?= $note['id'] ?>&candidate_id=<?= $candidate_id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                                <a href="delete_note.php?id=<?= $note['id'] ?>&return=candidate&id_return=<?= $candidate_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this note?');">Delete</a>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <!-- Status modal code remains unchanged -->
                <?php endforeach; ?>
            <?php else: ?>
                <p>No roles associated.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Extracted Resume Text</span>
            <a href="view_resume_text.php?id=<?= $candidate_id ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open Fullscreen</a>
        </div>
        <div class="card-body" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;">
            <?= !empty($candidate['resume_text']) ? htmlspecialchars($candidate['resume_text']) : '<span class="text-muted">No resume text available.</span>' ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <form method="POST" class="mb-3">
                <textarea name="content" class="form-control mb-2" rows="3" placeholder="Add a note..." required></textarea>
                <button type="submit" class="btn btn-sm btn-success">Save Note</button>
            </form>

            <?php if (empty($candidate_notes)): ?>
                <p class="text-muted mb-0">No notes found.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($candidate_notes as $note): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?></small><br>
                                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                                </div>
                                <div class="d-flex align-items-start gap-2 mt-2">
                                    <a href="edit_note.php?id=<?= $note['id'] ?>&candidate_id=<?= $candidate_id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="delete_note.php?id=<?= $note['id'] ?>&return=candidate&id_return=<?= $candidate_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this note?');">Delete</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Resume Upload Modal -->
<div class="modal fade" id="uploadResumeModal" tabindex="-1" aria-labelledby="uploadResumeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="upload_resume.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="uploadResumeModalLabel">Upload Resume</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="candidate_id" value="<?= $candidate_id ?>">
          <div class="mb-3">
            <label for="resume_file" class="form-label">Select resume file</label>
            <input type="file" class="form-control" name="resume_file" id="resume_file" accept=".pdf,.doc,.docx,.txt,.rtf,.odt" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Upload</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
