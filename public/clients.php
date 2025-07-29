<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';

$clients = [];

try {
    $stmt = $pdo->query("SELECT id, name, industry, location, account_manager, created_at FROM clients ORDER BY created_at DESC");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error loading clients: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Clients</h2>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            + Add
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="add_client.php">Add New Client</a></li>
            <li><a class="dropdown-item" href="import_clients_contacts.php">Import Clients & Contacts</a></li>
        </ul>
    </div>
</div>

<div style="overflow-x: auto;">
<table class="table table-striped table-bordered resizable" id="resizableTable">
    <thead class="table-dark">
        <tr>
            <th>Name</th>
            <th>Industry</th>
            <th>Location</th>
            <th>Account Manager</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($clients)): ?>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><a href="view_client.php?id=<?= $client['id'] ?>"><?= htmlspecialchars($client['name'] ?? '') ?></a></td>
                    <td><?= htmlspecialchars($client['industry'] ?? '') ?></td>
                    <td><?= htmlspecialchars($client['location'] ?? '') ?></td>
                    <td><?= htmlspecialchars($client['account_manager'] ?? '') ?></td>
                    <td><?= htmlspecialchars($client['created_at'] ?? '') ?></td>
                    <td>
                        <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center">No clients found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

<style>
    th {
        position: relative;
    }
    th.resizer {
        cursor: col-resize;
        user-select: none;
    }
    th .resizer {
        position: absolute;
        right: 0;
        top: 0;
        width: 5px;
        height: 100%;
        cursor: col-resize;
        z-index: 1;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('resizableTable');
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
