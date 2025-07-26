<?php
require_once __DIR__ . '/../includes/require_login.php';
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

// Load jobs for assigning
$stmt = $pdo->query("SELECT id, title FROM jobs ORDER BY created_at DESC");
$jobs = $stmt->fetchAll();

// Load assigned job IDs
$stmt = $pdo->prepare("SELECT job_id FROM associations WHERE candidate_id = ?");
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

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($candidate['first_name'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($candidate['last_name'] ?? '') ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($candidate['email'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">
    </div>


    <button type="submit" class="btn btn-success">Update Candidate</button>
</form>

<?php require_once '../includes/footer.php'; ?>

