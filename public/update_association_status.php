<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/kpi_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
    exit;
}

$association_id = isset($_POST['association_id']) ? (int)$_POST['association_id'] : 0;
$new_status     = trim($_POST['new_status'] ?? '');
$note_content   = trim($_POST['note'] ?? '');
$post_candidate = isset($_POST['candidate_id']) ? (int)$_POST['candidate_id'] : 0;

// Optional redirect override
$return    = $_POST['return']    ?? 'candidate';
$id_return = isset($_POST['id_return']) && is_numeric($_POST['id_return']) ? (int)$_POST['id_return'] : $post_candidate;

if ($association_id <= 0 || $new_status === '' || $post_candidate <= 0) {
    echo "<div class='alert alert-danger'>Missing required fields.</div>";
    exit;
}

try {
    // Fetch existing association to get old status + true candidate/job IDs
    $stmt = $pdo->prepare("SELECT candidate_id, job_id, status FROM associations WHERE id = ?");
    $stmt->execute([$association_id]);
    $assoc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assoc) {
        echo "<div class='alert alert-danger'>Association not found.</div>";
        exit;
    }

    $old_status   = $assoc['status'] ?? null;
    $candidate_id = (int)$assoc['candidate_id']; // trust DB for logging/notes
    $job_id       = (int)$assoc['job_id'];

    // Update association status
    $stmt = $pdo->prepare("UPDATE associations SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $association_id]);

    // Log KPI event (correct signature/order)
    $user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    $user_id = (is_numeric($user_id) && (int)$user_id > 0) ? (int)$user_id : null;

    // kpi_log_status_change(PDO $pdo, int $association_id, int $candidate_id, int $job_id, string $new_status, string $old_status, ?int $user_id)
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
    if ($old_status !== $new_status) {
        // Optional: pull job title for context
        $job_title = '';
        if ($job_id > 0) {
            $jt = $pdo->prepare("SELECT title FROM jobs WHERE id = ?");
            $jt->execute([$job_id]);
            $job_title = (string)($jt->fetchColumn() ?: '');
        }

        $auto_note = "Status changed: " . (string)$old_status . " → " . (string)$new_status;
        if ($job_id > 0) {
            $auto_note .= $job_title !== ''
                ? " (Job: {$job_title} #{$job_id})"
                : " (Job ID: {$job_id})";
        }

        $stmt = $pdo->prepare(
            "INSERT INTO notes (module_type, module_id, content, created_at)
             VALUES ('candidate', :module_id, :content, NOW())"
        );
        $stmt->execute([
            ':module_id' => $candidate_id,
            ':content'   => $auto_note
        ]);
    }

    // Save optional manual note (attach to candidate, not association)
    if ($note_content !== '') {
        $stmt = $pdo->prepare(
            "INSERT INTO notes (module_type, module_id, content, created_at)
             VALUES ('candidate', :module_id, :content, NOW())"
        );
        $stmt->execute([
            ':module_id' => $candidate_id,
            ':content'   => $note_content
        ]);
    }

    // Redirect back to appropriate view
    if ($return === 'job' && $id_return > 0) {
        header("Location: view_job.php?id=" . urlencode((string)$id_return) . "&msg=Status+updated");
    } else {
        // fall back to DB candidate_id if posted one is missing/incorrect
        $redir_cand = $post_candidate > 0 ? $post_candidate : $candidate_id;
        header("Location: view_candidate.php?id=" . urlencode((string)$redir_cand) . "&msg=Status+updated");
    }
    exit;

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error updating status: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}
