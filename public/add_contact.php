<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Fetch clients for dropdown
$clientStmt = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC");
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <h2 class="mb-4">Add New Contact</h2>

    <form method="POST" action="save_contact.php">
        <div class="mb-3">
            <label for="client_id" class="form-label">Associated Client</label>
            <select name="client_id" id="client_id" class="form-select" required>
                <option value="">Select a client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">Full Name</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title / Position</label>
            <input type="text" name="title" id="title" class="form-control">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="text" name="phone" id="phone" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Save Contact</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
