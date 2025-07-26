<?php
require_once __DIR__ . '/../config/database.php';

$application_id = $_POST['application_id'] ?? null;
$status = $_POST['new_status'] ?? null;
$note = trim($_POST['note'] ?? '');
$candidate_id = $_POST['candidate_id'] ?? null;

if (!$application_id || !$status || !$candidate_id) {
    echo "<div class='alert alert-danger'>Invalid request.</div>";
    exit;
}

try {
    // Update application status
    $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->execute([$status, $application_id]);

    // Save optional note
    if ($note !== '') {
        $stmt = $pdo->prepare("INSERT INTO notes (candidate_id, content) VALUES (?, ?)");
        $stmt->execute([$candidate_id, $note]);
    }

    // Redirect back to candidate page
    header("Location: view_candidate.php?id=" . urlencode($candidate_id));
    exit;
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error updating status: " . htmlspecialchars($e->getMessage()) . "</div>";
}
