<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Fetch clients for dropdown
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();

// Handle optional preselected client
$preselected_client_id = $_GET['client_id'] ?? null;

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Open');
    $client_id = $_POST['client_id'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO jobs (title, description, status, client_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $description, $status, $client_id]);

    $success = true;
}
?>

<h2 class="mb-4">Add New Job</h2>

<?php if ($success): ?>
    <div class="alert alert-success">Job added successfully!</div>
<?php endif; ?>

<form method="post">
    <div class="form-group">
        <label>Job Title</label>
        <input type="text" name="title" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Client</label>
        <select name="client_id" class="form-control">
            <option value="">— No Client —</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= $client['id'] ?>" <?= $client['id'] == $preselected_client_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($client['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="4"></textarea>
    </div>

    <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
            <option value="Open">Open</option>
            <option value="Closed">Closed</option>
            <option value="On Hold">On Hold</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Save Job</button>
    <a href="jobs.php" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
