<?php
require_once __DIR__ . '/../includes/require_login.php';
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

<form method="POST" action="update_client.php" enctype="multipart/form-data">
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
        <label for="phone" class="form-label">Phone</label>
        <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($client['phone']) ?>">
    </div>

    <div class="mb-3">
        <label for="website" class="form-label">Website</label>
        <input type="text" name="website" id="website" class="form-control" value="<?= htmlspecialchars($client['url']) ?>">
    </div>

    <div class="mb-3">
        <label for="location" class="form-label">Location</label>
        <input type="text" name="location" id="location" class="form-control" value="<?= htmlspecialchars($client['location']) ?>">
    </div>

    <div class="mb-3">
        <label for="industry" class="form-label">Industry</label>
        <input type="text" name="industry" id="industry" class="form-control" value="<?= htmlspecialchars($client['industry']) ?>">
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

    <!-- Attachments (client-level) -->
    <hr class="my-4">
    <h5>Attachments</h5>
    <p class="text-muted small mb-3">Optional: upload client documents (e.g., MSA, SOW, NDA). Max 5MB each.</p>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">MSA</label>
            <input type="file" name="msa_file" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">SOW</label>
            <input type="file" name="sow_file" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">NDA</label>
            <input type="file" name="nda_file" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Other Attachment 1</label>
            <input type="file" name="other_attachment_1" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label">Other Attachment 2</label>
            <input type="file" name="other_attachment_2" class="form-control">
        </div>
    </div>

    <div class="d-flex align-items-center mt-4">
        <button type="submit" class="btn btn-success">Update Client</button>
        <a href="delete_client.php?id=<?= $client['id'] ?>" class="btn btn-danger ms-auto" onclick="return confirm('Are you sure you want to delete this client?');">Delete</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
