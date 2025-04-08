<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("SELECT * FROM clients ORDER BY created_at DESC");
$clients = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Clients</h2>
    <a href="add_client.php" class="btn btn-primary">+ Add Client</a>
</div>

<?php if (count($clients) === 0): ?>
    <div class="alert alert-info">No clients found.</div>
<?php else: ?>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Account Manager</th>
                <th>Contact Number</th>
                <th>Website</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td>
                        <a href="view_client.php?id=<?= $client['id'] ?>">
                            <?= htmlspecialchars($client['name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($client['account_manager']) ?></td>
                    <td><?= htmlspecialchars($client['contact_number']) ?></td>
                    <td><?= htmlspecialchars($client['website']) ?></td>
                    <td><?= date('Y-m-d', strtotime($client['created_at'])) ?></td>
                    <td>
                        <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
