<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>No client ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    echo "<div class='alert alert-warning'>Client not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$status_options = [
    'Active',
    'Inactive',
    'Prospect',
    'Closed',
    'On Hold'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Client</h2>
    <a href="delete_client.php?id=<?= $client['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this client?');">Delete</a>
</div>

<form method="POST" action="update_client.php">
    <input type="hidden" name="id" value="<?= htmlspecialchars($client['id']) ?>">

    <div class="mb-3">
        <label for="name" class="form-label">Client Name</label>
        <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($client['name']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="account_manager" class="form-label">Account Manager</label>
        <input type="text" name="account_manager" id="account_manager" class="form-control" value="<?= htmlspecialchars($client['account_manager']) ?>">
    </div>

    <div class="mb-3">
        <label for="contact_number" class="form-label">Contact Number</label>
        <input type="text" name="contact_number" id="contact_number" class="form-control" value="<?= htmlspecialchars($client['contact_number']) ?>">
    </div>

    <div class="mb-3">
        <label for="fax" class="form-label">Fax</label>
        <input type="text" name="fax" id="fax" class="form-control" value="<?= htmlspecialchars($client['fax']) ?>">
    </div>

    <div class="mb-3">
        <label for="website" class="form-label">Website</label>
        <input type="text" name="website" id="website" class="form-control" value="<?= htmlspecialchars($client['website']) ?>">
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Status</label>
        <select name="status" id="status" class="form-select">
            <?php foreach ($status_options as $option): ?>
                <option value="<?= $option ?>" <?= $client['status'] === $option ? 'selected' : '' ?>>
                    <?= htmlspecialchars($option) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="about" class="form-label">About</label>
        <textarea name="about" id="about" class="form-control" rows="4"><?= htmlspecialchars($client['about']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-success">Update Client</button>
    <a href="delete_client.php?id=<?= $client['id'] ?>" class="btn btn-danger float-end" onclick="return confirm('Are you sure you want to delete this client?');">Delete</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
