<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>Invalid job ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    echo "<div class='alert alert-danger'>Job not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch clients
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'Open');
    $client_id = $_POST['client_id'] ?? null;

    try {
        $stmt = $pdo->prepare("UPDATE jobs SET title = ?, description = ?, status = ?, client_id = ? WHERE id = ?");
        $stmt->execute([$title, $description, $status, $client_id, $id]);

        // Redirect to view page after update
        header("Location: view_job.php?id=" . $id);
        exit;
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Update failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<h2 class="mb-4">Edit Job</h2>

<form method="post">
    <div class="form-group">
        <label>Job Title</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($job['title']) ?>" required>
    </div>

    <div class="form-group">
        <label>Client</label>
        <select name="client_id" class="form-control">
            <option value="">— No Client —</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= $client['id'] ?>" <?= $client['id'] == $job['client_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($client['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($job['description']) ?></textarea>
    </div>

    <div class="form-group">
        <label>Status</label>
        <select name="status" class="form-control">
            <option value="Open" <?= $job['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
            <option value="Closed" <?= $job['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
            <option value="On Hold" <?= $job['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Update Job</button>
    <a href="view_job.php?id=<?= $job['id'] ?>" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
