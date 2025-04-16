<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>No job ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    echo "<div class='alert alert-warning'>Job not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Status options
$status_options = [
    'Open',
    'In Progress',
    'Closed',
    'On Hold',
    'Canceled',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Job</h2>
    <a href="delete_job.php?id=<?= $job['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this job?');">Delete</a>
</div>

<form method="POST" action="update_job.php">
    <input type="hidden" name="id" value="<?= $job['id'] ?>">

    <div class="mb-3">
        <label for="title" class="form-label">Job Title</label>
        <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($job['title']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="location" class="form-label">Location</label>
        <input type="text" name="location" id="location" class="form-control" value="<?= htmlspecialchars($job['location']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Status</label>
        <select name="status" id="status" class="form-select" required>
            <?php foreach ($status_options as $option): ?>
                <option value="<?= $option ?>" <?= $job['status'] === $option ? 'selected' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea name="description" id="description" class="form-control" rows="5"><?= htmlspecialchars($job['description']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-success">Update Job</button>
    <a href="delete_job.php?id=<?= $job['id'] ?>" class="btn btn-danger float-end" onclick="return confirm('Are you sure you want to delete this job?');">Delete</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
