<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Load status list from config
$statusList = require __DIR__ . '/../config/status_list.php';

$association_id = $_GET['id'] ?? null;

if (!$association_id) {
    echo "<div class='alert alert-danger'>Missing association ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch the association
$stmt = $pdo->prepare("SELECT a.*, c.name AS candidate_name, j.title AS job_title, c.id AS candidate_id 
    FROM associations a 
    JOIN candidates c ON a.candidate_id = c.id 
    JOIN jobs j ON a.job_id = j.id 
    WHERE a.id = ?");
$stmt->execute([$association_id]);
$association = $stmt->fetch();

if (!$association) {
    echo "<div class='alert alert-warning'>Association not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$update_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'] ?? '';
    $note = trim($_POST['note'] ?? '');

    // Update association status
    $stmt = $pdo->prepare("UPDATE associations SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $association_id]);

    // Optional: Add a note to the record
    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO notes (content, module_type, module_id, created_at) VALUES (?, 'association', ?, NOW())");
        $stmt->execute([$note, $association_id]);
    }

    $update_message = "Association updated successfully.";
    $association['status'] = $new_status; // reflect updated status
}
?>

<div class="container mt-4">
    <h2>Edit Association</h2>

    <?php if ($update_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($update_message) ?></div>
    <?php endif; ?>

    <p><strong>Candidate:</strong> <?= htmlspecialchars($association['candidate_name']) ?></p>
    <p><strong>Job:</strong> <?= htmlspecialchars($association['job_title']) ?></p>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select" required>
                <?php foreach ($statusList as $category => $statuses): ?>
                    <optgroup label="<?= htmlspecialchars($category) ?>">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= $association['status'] === $status ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="note" class="form-label">Optional Note</label>
            <textarea name="note" id="note" class="form-control" rows="3" placeholder="Add a note..."></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="view_candidate.php?id=<?= $association['candidate_id'] ?>" class="btn btn-secondary ms-2">Back to Candidate</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

