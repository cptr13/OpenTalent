<?php
require_once __DIR__ . '/../includes/require_login.php';
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
        FROM associations a
        JOIN jobs j ON a.job_id = j.id
        JOIN candidates c ON a.candidate_id = c.id
        ORDER BY a.assigned_at DESC
    ");
    $associations = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading associations: " . $e->getMessage() . "</div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Associated Candidates & Jobs</h2>
    <a href="assign.php" class="btn btn-primary">+ Associate Candidate</a>
</div>

<?php if (count($associations) > 0): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="thead-dark">
                <tr>
                    <th>Candidate</th>
                    <th>Candidate Status</th>
                    <th>Job Title</th>
                    <th>Job Status</th>
                    <th>Associated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($associations as $assoc): ?>
                    <tr>
                        <td>
                            <a href="view_candidate.php?id=<?= $assoc['candidate_id'] ?>">
                                <?= htmlspecialchars($assoc['candidate_name']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= htmlspecialchars($assoc['candidate_status']) ?></span>
                        </td>
                        <td>
                            <a href="view_job.php?id=<?= $assoc['job_id'] ?>">
                                <?= htmlspecialchars($assoc['job_title']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars($assoc['job_status']) ?></span>
                        </td>
                        <td><?= date("Y-m-d", strtotime($assoc['assigned_at'])) ?></td>
                        <td class="d-flex gap-2 flex-wrap">
                            <a href="view_association.php?id=<?= $assoc['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <a href="edit_association.php?id=<?= $assoc['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <a href="delete_association.php?id=<?= $assoc['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Remove this association between candidate and job?')">
                                Unassign
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-info">No candidate-job associations yet.</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

