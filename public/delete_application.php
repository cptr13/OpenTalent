<?php
require_once __DIR__ . '/../config/database.php';

$application_id = $_GET['id'] ?? null;
$candidate_id = $_GET['candidate_id'] ?? null;

if (!$application_id || !$candidate_id) {
    echo "<div class='alert alert-danger'>Invalid request. Missing application ID or candidate ID.</div>";
    exit;
}

try {
    // Delete the application (job assignment)
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);

    // Redirect back to the candidate view page
    header("Location: view_candidate.php?id=" . urlencode($candidate_id));
    exit;
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error deleting assignment: " . htmlspecialchars($e->getMessage()) . "</div>";
}
