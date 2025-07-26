<?php
require_once '../includes/header.php';
require_once '../config/database.php';

try {
    $stmt = $pdo->query("
        SELECT 
            a.id,
            a.job_id,
            a.candidate_id,
            a.assigned_at,
            j.title AS job_title,
            j.status AS job_status,
            c.name AS candidate_name,
            c.status AS candidate_status
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN candidates c ON a.candidate_id = c.id
        ORDER BY a.assigned_at DESC
    ");
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading applications: " . $e->getMessage() . "</div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Candidate-Job Assignments</h2>
    <a href="assign.php" class="btn btn-primary">+ Assign Candidate</a>
</div>

<?php if (count($applications) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="thead-dark">
                <tr>
                    <th>Candidate</th>
                    <th>Candidate Status</th>
                    <th>Job Title</th>
                    <th>Job Status</th>
                    <th>Assigned At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                    <tr>
                        <td>
                            <a href="view_candidate.php?id=<?= $app['candidate_id'] ?>">
                                <?= htmlspecialchars($app['candidate_name']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= htmlspecialchars($app['candidate_status']) ?></span>
                        </td>
                        <td>
                            <a href="view_job.php?id=<?= $app['job_id'] ?>">
                                <?= htmlspecialchars($app['job_title']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($app['job_status']) ?></span>
                        </td>
                        <td><?= date("Y-m-d", strtotime($app['assigned_at'])) ?></td>
                        <td>
                            <a href="delete_application.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Remove this candidate-job assignment?')">
                                Unassign
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">No candidate-job assignments yet.</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
