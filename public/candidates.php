<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT * FROM candidates ORDER BY created_at DESC");
$candidates = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Candidates</h2>
    <a href="add_candidate.php" class="btn btn-primary">+ Add Candidate</a>
</div>

<?php if (count($candidates) === 0): ?>
    <div class="alert alert-info">No candidates found.</div>
<?php else: ?>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($candidates as $candidate): ?>
                <tr>
                    <td>
                        <a href="view_candidate.php?id=<?= $candidate['id'] ?>">
                            <?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($candidate['email']) ?></td>
                    <td><?= htmlspecialchars($candidate['phone']) ?></td>
                    <td><?= htmlspecialchars($candidate['status']) ?></td>
                    <td><?= date('Y-m-d', strtotime($candidate['created_at'])) ?></td>
                    <td>
                        <a href="edit_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
