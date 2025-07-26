<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
    exit;
}

$application_id = $_POST['application_id'] ?? null;
$status = trim($_POST['new_status'] ?? '');
$note = trim($_POST['note'] ?? '');
$candidate_id = $_POST['candidate_id'] ?? null;

// Optional redirect override
$return = $_POST['return'] ?? 'candidate';
$id_return = $_POST['id_return'] ?? $candidate_id;

if (!$application_id || !$status || !$candidate_id) {
    echo "<div class='alert alert-danger'>Missing required fields.</div>";
    exit;
}

try {
    // Update application status
    $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->execute([$status, $application_id]);

    // Save optional note
    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO notes (candidate_id, content) VALUES (?, ?)");
        $stmt->execute([$candidate_id, $note]);
    }

    // Redirect back to appropriate view
    if ($return === 'job' && is_numeric($id_return)) {
        header("Location: view_job.php?id=" . urlencode($id_return) . "&msg=Status+updated");
    } else {
        header("Location: view_candidate.php?id=" . urlencode($candidate_id) . "&msg=Status+updated");
    }
    exit;
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error updating status: " . htmlspecialchars($e->getMessage()) . "</div>";
}

