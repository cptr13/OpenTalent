<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/kpi_logger.php'; // <-- add KPI logger

// Load status list from config
$statusList = require __DIR__ . '/../config/status_list.php';

$association_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($association_id <= 0) {
    echo "<div class='alert alert-danger'>Missing association ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch the association (including current status + candidate/job IDs)
$stmt = $pdo->prepare("
    SELECT a.*, 
           c.name  AS candidate_name, 
           j.title AS job_title, 
           c.id    AS candidate_id
    FROM associations a 
    JOIN candidates c ON a.candidate_id = c.id 
    JOIN jobs j       ON a.job_id = j.id 
    WHERE a.id = ?
");
$stmt->execute([$association_id]);
$association = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$association) {
    echo "<div class='alert alert-warning'>Association not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$update_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status   = trim($_POST['status'] ?? '');
    $note_content = trim($_POST['note'] ?? '');

    if ($new_status === '') {
        echo "<div class='alert alert-danger'>Status is required.</div>";
    } else {
        try {
            // Capture old status + candidate/job from the fetched association
            $old_status   = $association['status'] ?? null;
            $candidate_id = (int)$association['candidate_id'];
            $job_id       = (int)$association['job_id'];

            // Update association status
            $stmt = $pdo->prepare("UPDATE associations SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $association_id]);

            // Log KPI event (no-op if not mapped or already credited)
            $user_id = $_SESSION['user_id'] ?? null;
            kpi_log_status_change($pdo, $candidate_id, $job_id, $new_status, $old_status, $user_id);

            // Optional: Add a note to the record
            if ($note_content !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO notes (content, module_type, module_id, created_at)
                    VALUES (:content, 'association', :module_id, NOW())
                ");
                $stmt->execute([
                    ':content'   => $note_content,
                    ':module_id' => $association_id
                ]);
            }

            $update_message        = "Association updated successfully.";
            $association['status'] = $new_status; // reflect updated status for the form

        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error updating association: " . htmlspecialchars($e->getMessage()) . "</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
        }
    }
}
?>

<div class="container mt-4">
    <h2>Edit Association</h2>

    <?php if ($update_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($update_message) ?></div>
    <?php endif; ?>

    <p><strong>Candidate:</strong> <?= htmlspecialchars($association['candidate_name'] ?? '') ?></p>
    <p><strong>Job:</strong> <?= htmlspecialchars($association['job_title'] ?? '') ?></p>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select" required>
                <?php foreach ($statusList as $category => $statuses): ?>
                    <optgroup label="<?= htmlspecialchars($category) ?>">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= ($association['status'] ?? '') === $status ? 'selected' : '' ?>>
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
        <a href="view_candidate.php?id=<?= (int)($association['candidate_id'] ?? 0) ?>" class="btn btn-secondary ms-2">Back to Candidate</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
