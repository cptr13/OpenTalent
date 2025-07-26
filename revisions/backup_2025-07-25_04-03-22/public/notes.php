<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

try {
    $sql = "
        SELECT n.*, 
            c.id AS candidate_id, CONCAT(c.first_name, ' ', c.last_name) AS candidate_name,
            j.id AS job_id, j.title AS job_title,
            cl.id AS client_id, cl.name AS client_name,
            ct.id AS contact_id, CONCAT(ct.first_name, ' ', ct.last_name) AS contact_name
        FROM notes n
        LEFT JOIN candidates c ON n.module_type = 'candidate' AND n.module_id = c.id
        LEFT JOIN jobs j ON n.module_type = 'job' AND n.module_id = j.id
        LEFT JOIN clients cl ON n.module_type = 'client' AND n.module_id = cl.id
        LEFT JOIN contacts ct ON n.module_type = 'contact' AND n.module_id = ct.id
        ORDER BY n.created_at DESC
    ";
    $stmt = $pdo->query($sql);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading notes: " . htmlspecialchars($e->getMessage()) . "</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<div class="container-fluid px-4 mt-4">
    <h2 class="mb-4">Activity Logs</h2>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Candidate</th>
                    <th>Job</th>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($notes): ?>
                    <?php foreach ($notes as $note): ?>
                        <tr>
                            <td>
                                <?php if (!empty($note['candidate_name'])): ?>
                                    <a href="view_candidate.php?id=<?= $note['candidate_id'] ?>">
                                        <?= htmlspecialchars($note['candidate_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($note['job_title'])): ?>
                                    <a href="view_job.php?id=<?= $note['job_id'] ?>">
                                        <?= htmlspecialchars($note['job_title']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($note['client_name'])): ?>
                                    <a href="view_client.php?id=<?= $note['client_id'] ?>">
                                        <?= htmlspecialchars($note['client_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($note['contact_name'])): ?>
                                    <a href="view_contact.php?id=<?= $note['contact_id'] ?>">
                                        <?= htmlspecialchars($note['contact_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= nl2br(htmlspecialchars($note['content'] ?? '')) ?></td>
                            <td>
                                <?= !empty($note['created_at']) 
                                    ? date('Y-m-d H:i', strtotime($note['created_at'])) 
                                    : '<span class="text-muted">—</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No activity yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
