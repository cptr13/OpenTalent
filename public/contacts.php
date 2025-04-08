<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT contacts.*, clients.name AS client_name
    FROM contacts
    LEFT JOIN clients ON contacts.client_id = clients.id
    ORDER BY contacts.created_at DESC
");
$contacts = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Contacts</h2>
    <a href="add_contact.php" class="btn btn-primary">+ Add Contact</a>
</div>

<?php if (count($contacts) === 0): ?>
    <div class="alert alert-info">No contacts found.</div>
<?php else: ?>
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Title</th>
                <th>Company</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contacts as $contact): ?>
                <tr>
                    <td>
                        <a href="view_contact.php?id=<?= $contact['id'] ?>">
                            <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($contact['job_title']) ?></td>
                    <td><?= htmlspecialchars($contact['client_name']) ?></td>
                    <td><?= htmlspecialchars($contact['email']) ?></td>
                    <td><?= htmlspecialchars($contact['phone']) ?></td>
                    <td><?= date('Y-m-d', strtotime($contact['created_at'])) ?></td>
                    <td>
                        <a href="edit_contact.php?id=<?= $contact['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
