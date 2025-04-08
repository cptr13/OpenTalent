<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT jobs.*, clients.name AS client_name FROM jobs LEFT JOIN clients ON jobs.client_id = clients.id ORDER BY jobs.created_at DESC");
$jobs = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Jobs</h2>
    <a href="add_job.php" class="btn btn-primary">+ Add Job</a>
</div>

<?php if (count($jobs) === 0): ?>
    <div class="alert alert-info">No job orders found.</div>
<?php else: ?>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Job Title</th>
                <th>Client</th>
                <th>Location</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td>
                        <a href="view_job.php?id=<?= $job['id'] ?>">
                            <?= htmlspecialchars($job['title']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($job['client_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($job['location']) ?></td>
                    <td><?= htmlspecialchars($job['status']) ?></td>
                    <td><?= date('Y-m-d', strtotime($job['created_at'])) ?></td>
                    <td>
                        <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
