<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/kpi_logger.php'; // KPI logger
require_once __DIR__ . '/../config/status.php';        // getStatusList('candidate')

// Helpers
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$association_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($association_id <= 0) {
    echo "<div class='alert alert-danger'>Missing association ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch the association (including current status + candidate/job IDs + names)
$stmt = $pdo->prepare("
    SELECT a.*,
           c.id    AS candidate_id,
           CONCAT(TRIM(COALESCE(c.first_name,'')), ' ', TRIM(COALESCE(c.last_name,''))) AS candidate_name,
           j.id    AS job_id,
           j.title AS job_title
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

// Build candidate status list (grouped)
try {
    $candidateStatusList = getStatusList('candidate'); // ['Category' => ['Sub1', ...], ...]
} catch (Throwable $e) {
    $candidateStatusList = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status   = trim($_POST['status'] ?? '');
    $note_content = trim($_POST['note'] ?? '');

    // Basic required field
    if ($new_status === '') {
        echo "<div class='alert alert-danger'>Status is required.</div>";
    } else {
        // Validate against candidate status list
        $isValid = false;
        foreach ($candidateStatusList as $cat => $subs) {
            if (in_array($new_status, $subs, true)) { $isValid = true; break; }
        }
        if (!$isValid) {
            echo "<div class='alert alert-danger'>Invalid status value.</div>";
        } else {
            // If no change, short-circuit and redirect (PRG)
            $old_status   = $association['status'] ?? null;
            $candidate_id = (int)$association['candidate_id'];
            $job_id       = (int)$association['job_id'];

            if ((string)$old_status === (string)$new_status) {
                header('Location: view_candidate.php?id=' . $candidate_id . '&msg=' . urlencode('No changes made.'));
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Update association status
                $stmt = $pdo->prepare("UPDATE associations SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $association_id]);

                // Log KPI event with correct signature/order:
                // kpi_log_status_change(PDO $pdo, int $association_id, int $candidate_id, int $job_id, string $new_status, string $old_status, ?int $user_id)
                $user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
                $user_id = (is_numeric($user_id) && (int)$user_id > 0) ? (int)$user_id : null;

                kpi_log_status_change(
                    $pdo,
                    (int)$association_id,
                    (int)$candidate_id,
                    (int)$job_id,
                    (string)$new_status,
                    (string)$old_status,
                    $user_id
                );

                // Auto-note when status actually changed (attach to candidate)
                $job_title = (string)($association['job_title'] ?? '');
                $auto_note = "Status changed: " . (string)$old_status . " → " . (string)$new_status;
                if ($job_id > 0) {
                    $auto_note .= $job_title !== ''
                        ? " (Job: {$job_title} #{$job_id})"
                        : " (Job ID: {$job_id})";
                }
                $stmt = $pdo->prepare("
                    INSERT INTO notes (content, module_type, module_id, created_at)
                    VALUES (:content, 'candidate', :module_id, NOW())
                ");
                $stmt->execute([
                    ':content'   => $auto_note,
                    ':module_id' => $candidate_id
                ]);

                // Optional manual note (also attach to candidate)
                if ($note_content !== '') {
                    $stmt = $pdo->prepare("
                        INSERT INTO notes (content, module_type, module_id, created_at)
                        VALUES (:content, 'candidate', :module_id, NOW())
                    ");
                    $stmt->execute([
                        ':content'   => $note_content,
                        ':module_id' => $candidate_id
                    ]);
                }

                $pdo->commit();

                // PRG to candidate page with flash
                header('Location: view_candidate.php?id=' . $candidate_id . '&msg=' . urlencode('Association status updated.'));
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo "<div class='alert alert-danger'>Error updating association: " . h($e->getMessage()) . "</div>";
            }
        }
    }
}

// Refresh local state if we didn’t redirect (e.g., validation error)
if (isset($new_status) && $new_status !== '' && !empty($isValid)) {
    $association['status'] = $new_status;
}
?>

<div class="container mt-4">
    <h2>Edit Association</h2>

    <?php if ($update_message): ?>
        <div class="alert alert-success"><?= h($update_message) ?></div>
    <?php endif; ?>

    <p><strong>Candidate:</strong> <?= h($association['candidate_name'] ?? '') ?></p>
    <p><strong>Job:</strong> <?= h($association['job_title'] ?? '') ?></p>

    <form method="POST" class="mt-3">
        <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select" required>
                <?php foreach ($candidateStatusList as $category => $statuses): ?>
                    <optgroup label="<?= h($category) ?>">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= h($status) ?>" <?= (($association['status'] ?? '') === $status) ? 'selected' : '' ?>>
                                <?= h($status) ?>
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
