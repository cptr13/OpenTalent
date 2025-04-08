<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Fetch notes with candidate and job info
$sql = "
    SELECT n.*, 
           c.name AS candidate_name, 
           j.title AS job_title
    FROM notes n
    LEFT JOIN candidates c ON n.candidate_id = c.id
    LEFT JOIN jobs j ON n.job_id = j.id
    ORDER BY n.created_at DESC
";
$stmt = $pdo->query($sql);
$notes = $stmt->fetchAll();
?>

<h2 class="mb-4">Activity Logs</h2>

<table class="table table-striped table-bordered">
    <thead class="thead-dark">
        <tr>
            <th>Candidate</th>
            <th>Job</th>
            <th>Note</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($notes): ?>
            <?php foreach ($notes as $note): ?>
                <tr>
                    <td><?= htmlspecialchars($note['candidate_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($note['job_title'] ?? 'N/A') ?></td>
                    <td><?= nl2br(htmlspecialchars($note['content'])) ?></td>
                    <td><?= date('Y-m-d', strtotime($note['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" class="text-center text-muted">No activity yet.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
