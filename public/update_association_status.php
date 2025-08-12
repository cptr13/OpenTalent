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
    $candidate_id = (int)$assoc['candidate_id']; // trust DB for logging
    $job_id       = (int)$assoc['job_id'];

    // Update association status
    $stmt = $pdo->prepare("UPDATE associations SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $association_id]);

    // Log KPI event (no-op if not KPI-mapped or already credited)
    $user_id = $_SESSION['user_id'] ?? null;
    kpi_log_status_change($pdo, $candidate_id, $job_id, $new_status, $old_status, $user_id);

    // Save optional note
    if ($note_content !== '') {
        $stmt = $pdo->prepare(
            "INSERT INTO notes (module_type, module_id, content, created_at)
             VALUES (:module_type, :module_id, :content, NOW())"
        );
        $stmt->execute([
            ':module_type' => 'association',
            ':module_id'   => $association_id,
            ':content'     => $note_content
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
