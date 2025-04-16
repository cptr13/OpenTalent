<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$candidate_id = $_GET['id'] ?? null;
$open_app_id = $_GET['open_app'] ?? null;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['note'])) {
        $note_content = trim($_POST['note']);
        if ($note_content !== '') {
            $stmt = $pdo->prepare("INSERT INTO notes (candidate_id, content, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$candidate_id, $note_content]);
        }
        header("Location: view_candidate.php?id=$candidate_id");
        exit;
    }

    if (isset($_POST['assign_job'])) {
        $job_id = $_POST['job_id'] ?? null;
        $status = $_POST['status'] ?? 'Screening: Associated to Job';
        $note_content = $_POST['note_content'] ?? '';

        if ($job_id) {
            $stmt = $pdo->prepare("INSERT INTO applications (candidate_id, job_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$candidate_id, $job_id, $status]);

            $stmt = $pdo->prepare("SELECT client_id FROM jobs WHERE id = ?");
            $stmt->execute([$job_id]);
            $jobInfo = $stmt->fetch();
            $client_id = $jobInfo['client_id'] ?? null;

            if ($note_content !== '') {
                $stmt = $pdo->prepare("INSERT INTO notes (candidate_id, job_id, client_id, content, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$candidate_id, $job_id, $client_id, $note_content]);
            }

            header("Location: view_candidate.php?id=$candidate_id");
            exit;
        }
    }

    if (isset($_POST['upload_resume']) && isset($_FILES['resume_file'])) {
        $upload_dir = __DIR__ . '/../uploads/resumes/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_tmp = $_FILES['resume_file']['tmp_name'];
        $original_name = basename($_FILES['resume_file']['name']);
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $new_name = 'resume_' . $candidate_id . '_' . time() . '.' . $ext;

        $destination = $upload_dir . $new_name;
        if (move_uploaded_file($file_tmp, $destination)) {
            $stmt = $pdo->prepare("UPDATE candidates SET resume_filename = ? WHERE id = ?");
            $stmt->execute([$new_name, $candidate_id]);
            header("Location: view_candidate.php?id=$candidate_id");
            exit;
        } else {
            echo "<div class='alert alert-danger'>Failed to upload resume.</div>";
        }
    }
}

$stmt = $pdo->prepare("SELECT j.title, j.id as job_id, a.status, a.id as application_id FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.candidate_id = ?");
$stmt->execute([$candidate_id]);
$applications = $stmt->fetchAll();

$jobStmt = $pdo->query("SELECT id, title FROM jobs ORDER BY created_at DESC");
$jobs = $jobStmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM notes WHERE candidate_id = ? ORDER BY created_at DESC");
$stmt->execute([$candidate_id]);
$notes = $stmt->fetchAll();

$status_groups = [
    '1 - Screening' => ['Screening: Associated to Job', 'Screening: Attempted to Contact'],
    '2 - Interview' => ['Interview: Client Interview Scheduled'],
    '3 - Offered' => ['Offered: Offer Made'],
    '4 - Hired' => ['Hired'],
    '5 - Status Change' => ['Status Change: On Hold'],
    '6 - Rejected' => ['Rejected: Rejected by client']
];
?>

<div class="container-fluid mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= htmlspecialchars($candidate['name']) ?></h2>
        <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">Contact Info</div>
        <div class="card-body">
            <p><strong>Email:</strong> <?= htmlspecialchars($candidate['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($candidate['phone']) ?></p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Attachments</div>
        <div class="card-body">
            <?php if (!empty($candidate['resume_filename'])): ?>
                <p><strong>Resume:</strong><br>
                    <?= htmlspecialchars($candidate['resume_filename']) ?><br>
                    <a href="../uploads/resumes/<?= urlencode($candidate['resume_filename']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">View Resume</a>
                    <a href="../uploads/resumes/<?= urlencode($candidate['resume_filename']) ?>" download class="btn btn-sm btn-outline-secondary mt-1">Download</a>
                </p>
            <?php else: ?>
                <p>No attachments available.</p>
            <?php endif; ?>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="mb-3">
        <div class="input-group">
            <input type="file" name="resume_file" class="form-control" required>
            <button type="submit" name="upload_resume" class="btn btn-outline-primary">Upload Resume</button>
        </div>
    </form>

    <div class="card mb-3">
        <div class="card-header">Assign to Job</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Select Job:</label>
                    <select name="job_id" class="form-select" required>
                        <option value="">-- Choose --</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Initial Status:</label>
                    <select name="status" class="form-select">
                        <?php foreach ($status_groups as $group => $options): ?>
                            <optgroup label="<?= $group ?>">
                                <?php foreach ($options as $status): ?>
                                    <option value="<?= $status ?>"><?= $status ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Optional Note:</label>
                    <textarea name="note_content" class="form-control"></textarea>
                </div>
                <button type="submit" name="assign_job" class="btn btn-success">Assign Candidate</button>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Status & Roles</div>
        <div class="card-body">
            <?php if ($applications): ?>
                <?php foreach ($applications as $app): ?>
                    <div class="d-flex justify-content-between align-items-start border rounded p-2 mb-3">
                        <div class="flex-grow-1">
                            <a href="view_job.php?id=<?= $app['job_id'] ?>" class="fw-bold d-block">
                                <?= htmlspecialchars($app['title']) ?>
                            </a>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-info mb-2 d-block"><?= htmlspecialchars($app['status']) ?></span>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?= $app['application_id'] ?>">
                                Change Status
                            </button>
                        </div>
                    </div>

                    <div class="modal fade" id="statusModal<?= $app['application_id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form method="POST" action="update_application_status.php">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Status for <?= htmlspecialchars($app['title']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                        <input type="hidden" name="candidate_id" value="<?= $candidate_id ?>">
                                        <div class="mb-3">
                                            <label>Status</label>
                                            <select name="new_status" class="form-select" required>
                                                <?php foreach ($status_groups as $group => $options): ?>
                                                    <optgroup label="<?= $group ?>">
                                                        <?php foreach ($options as $status): ?>
                                                            <option value="<?= $status ?>" <?= $app['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label>Notes (optional)</label>
                                            <textarea name="note" class="form-control" rows="3"></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Update</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No roles assigned.</p>
            <?php endif; ?>
        </div>
    </div>

    <h4>Add Note</h4>
    <form method="POST" class="mb-4">
        <textarea name="note" class="form-control mb-2" rows="3" required></textarea>
        <button type="submit" class="btn btn-primary">Add Note</button>
    </form>

    <h4>Past Notes</h4>
    <ul class="list-group mb-5">
        <?php if (empty($notes)): ?>
            <li class="list-group-item">No notes found.</li>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <li class="list-group-item">
                    <strong><?= $note['created_at'] ?></strong><br>
                    <?= htmlspecialchars($note['content']) ?>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
