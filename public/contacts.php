<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../config/database.php';

$contacts = [];

try {
    $stmt = $pdo->query("
        SELECT contacts.id, contacts.full_name, contacts.email, contacts.phone, contacts.created_at,
               contacts.client_id, clients.name AS company_name
        FROM contacts
        LEFT JOIN clients ON contacts.client_id = clients.id
        ORDER BY contacts.created_at DESC
    ");
    $contacts = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading contacts: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Contacts</h2>
    <div>
        <a href="add_contact.php" class="btn btn-primary">+ Add Contact</a>
    </div>
</div>

<div style="overflow-x: auto;">
<table class="table table-striped table-bordered resizable" id="resizableContacts">
    <thead class="table-dark">
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Company</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($contacts)): ?>
            <?php foreach ($contacts as $contact): ?>
                <tr>
                    <td>
                        <a href="view_contact.php?id=<?= $contact['id'] ?>">
                            <?= htmlspecialchars($contact['full_name'] ?? '') ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($contact['phone'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($contact['client_id'])): ?>
                            <a href="view_client.php?id=<?= $contact['client_id'] ?>">
                                <?= htmlspecialchars($contact['company_name']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($contact['created_at'] ?? '') ?></td>
                    <td>
                        <a href="edit_contact.php?id=<?= $contact['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="delete_contact.php?id=<?= $contact['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this contact?');">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center">No contacts found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<style>
    th {
        position: relative;
    }
    th .resizer {
        position: absolute;
        right: 0;
        top: 0;
        width: 5px;
        height: 100%;
        cursor: col-resize;
        user-select: none;
        z-index: 1;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('resizableContacts');
        const cols = table.querySelectorAll('th');

        cols.forEach(th => {
            const resizer = document.createElement('div');
            resizer.classList.add('resizer');
            th.appendChild(resizer);
            resizer.addEventListener('mousedown', initResize);
        });

        let startX, startWidth, currentCol;

        function initResize(e) {
            currentCol = e.target.parentElement;
            startX = e.clientX;
            startWidth = currentCol.offsetWidth;
            document.addEventListener('mousemove', resizeColumn);
            document.addEventListener('mouseup', stopResize);
        }

        function resizeColumn(e) {
            const width = startWidth + (e.clientX - startX);
            currentCol.style.width = width + 'px';
        }

        function stopResize() {
            document.removeEventListener('mousemove', resizeColumn);
            document.removeEventListener('mouseup', stopResize);
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>
