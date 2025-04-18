<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $client_id = $_POST['client_id'] ?? null;
    $status = $_POST['status'] ?? 'Open';

    try {
        $stmt = $pdo->prepare("INSERT INTO jobs (title, description, location, client_id, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $location, $client_id, $status]);
        header('Location: jobs.php');
        exit;
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error saving job: " . $e->getMessage() . "</div>";
    }
}

// Load clients for dropdown
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Add Job</h2>
</div>

<form method="POST">
    <div class="mb-3">
        <label class="form-label">Job Title</label>
        <input type="text" name="title" class="form-control" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Client</label>
        <select name="client_id" class="form-control" required>
            <option value="">-- Select Client --</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Location</label>
        <input type="text" name="location" class="form-control">
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="5"></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Initial Status</label>
        <select name="status" class="form-control">
            <option value="Open">Open</option>
            <option value="On Hold">On Hold</option>
            <option value="Closed">Closed</option>
            <option value="Filled">Filled</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>

    <button type="submit" class="btn btn-success">Save Job</button>
</form>

<?php require_once '../includes/footer.php'; ?>
