<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<div class='alert alert-danger'>Invalid candidate ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-warning'>Candidate not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?></h2>
    <div>
        <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-warning">Edit</a>
        <a href="assign.php?candidate_id=<?= $candidate['id'] ?>" class="btn btn-primary">Assign to Job</a>
    </div>
</div>

<table class="table table-bordered">
    <tr><th>Email</th><td><?= htmlspecialchars($candidate['email']) ?></td></tr>
    <tr><th>Phone</th><td><?= htmlspecialchars($candidate['phone']) ?></td></tr>
    <tr><th>Mobile</th><td><?= htmlspecialchars($candidate['mobile']) ?></td></tr>
    <tr><th>Status</th><td><?= htmlspecialchars($candidate['status']) ?></td></tr>
    <tr><th>Experience</th><td><?= htmlspecialchars($candidate['experience']) ?></td></tr>
    <tr><th>Current Job</th><td><?= htmlspecialchars($candidate['current_job']) ?></td></tr>
    <tr><th>Current Salary</th><td><?= htmlspecialchars($candidate['current_salary']) ?></td></tr>
    <tr><th>Expected Salary</th><td><?= htmlspecialchars($candidate['expected_salary']) ?></td></tr>
    <tr><th>Skills</th><td><?= htmlspecialchars($candidate['skills']) ?></td></tr>
    <tr><th>LinkedIn</th><td><?= htmlspecialchars($candidate['linkedin']) ?></td></tr>
    <tr><th>Facebook</th><td><?= htmlspecialchars($candidate['facebook']) ?></td></tr>
    <tr><th>Twitter</th><td><?= htmlspecialchars($candidate['twitter']) ?></td></tr>
    <tr><th>Website</th><td><?= htmlspecialchars($candidate['website']) ?></td></tr>
    <tr><th>Created At</th><td><?= date('Y-m-d', strtotime($candidate['created_at'])) ?></td></tr>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
