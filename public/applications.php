<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Get all applications with joined candidate and job info
$sql = "
    SELECT a.id, a.applied_at, 
           c.name AS candidate_name, 
           j.title AS job_title
    FROM applications a
    LEFT JOIN candidates c ON a.candidate_id = c.id
    LEFT JOIN jobs j ON a.job_id = j.id
    ORDER BY a.applied_at DESC
";
$stmt = $pdo->query($sql);
$applications = $stmt->fetchAll();
?>

<h2 class="mb-4">Applications</h2>

<table class="table table-striped table-bordered">
    <thead class="thead-dark">
        <tr>
            <th>Candidate</th>
            <th>Job</th>
            <th>Applied At</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($applications): ?>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?= htmlspecialchars($app['candidate_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($app['job_title'] ?? 'Unknown') ?></td>
                    <td><?= date('Y-m-d', strtotime($app['applied_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="text-center text-muted">No applications found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
