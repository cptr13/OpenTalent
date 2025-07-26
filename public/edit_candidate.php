<?php
require_once '../includes/header.php';
require_once '../config/database.php';

// Get candidate ID
$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    echo "<div class='alert alert-danger'>Invalid candidate ID.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Load candidate data
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-danger'>Candidate not found.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Load available statuses
$statuses = ['New', 'In Review', 'Interviewing', 'Offered', 'Hired', 'Rejected'];

// Load jobs for assigning
$stmt = $pdo->query("SELECT id, title FROM jobs ORDER BY created_at DESC");
$jobs = $stmt->fetchAll();

// Load assigned job IDs
$stmt = $pdo->prepare("SELECT job_id FROM applications WHERE candidate_id = ?");
$stmt->execute([$candidate_id]);
$assigned_jobs = array_column($stmt->fetchAll(), 'job_id');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Candidate</h2>
    <a href="paste_resume.php?redirect=edit&id=<?= $candidate['id'] ?>" class="btn btn-outline-warning">
        📋 Paste Resume
    </a>
</div>

<form method="POST" action="update_candidate.php" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $candidate['id'] ?>">

    <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($candidate['name'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($candidate['email'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= $status ?>" <?= $candidate['status'] === $status ? 'selected' : '' ?>>
                    <?= $status ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Assign to Job(s)</label>
        <select name="job_ids[]" class="form-select" multiple>
            <?php foreach ($jobs as $job): ?>
                <option value="<?= $job['id'] ?>" <?= in_array($job['id'], $assigned_jobs) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($job['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
    </div>

    <button type="submit" class="btn btn-success">Update Candidate</button>
</form>

<?php require_once '../includes/footer.php'; ?>
