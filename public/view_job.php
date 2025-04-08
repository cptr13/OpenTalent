<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<div class='alert alert-danger'>No job ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT jobs.*, clients.name AS client_name FROM jobs LEFT JOIN clients ON jobs.client_id = clients.id WHERE jobs.id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    echo "<div class='alert alert-warning'>Job not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= htmlspecialchars($job['title']) ?></h2>
    <div>
        <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn btn-warning">Edit</a>
        <a href="assign.php?job_id=<?= $job['id'] ?>" class="btn btn-primary">Assign Candidate</a>
    </div>
</div>

<table class="table table-bordered">
    <tr><th>Client</th><td><?= htmlspecialchars($job['client_name'] ?? 'N/A') ?></td></tr>
    <tr><th>Location</th><td><?= htmlspecialchars($job['location']) ?></td></tr>
    <tr><th>Status</th><td><?= htmlspecialchars($job['status']) ?></td></tr>
    <tr><th>Created At</th><td><?= date('Y-m-d', strtotime($job['created_at'])) ?></td></tr>
    <tr><th>Description</th><td><?= nl2br(htmlspecialchars($job['description'])) ?></td></tr>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
