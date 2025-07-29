<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$association_id = $_GET['association_id'] ?? null;
$candidate_id = $_GET['candidate_id'] ?? null;

if (!$association_id || !$candidate_id) {
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "<div class='alert alert-danger'>Invalid request. Missing association ID or candidate ID.</div>";
    echo "</body></html>";
    exit;
}

try {
    // Delete the association (candidate-job link)
    $stmt = $pdo->prepare("DELETE FROM associations WHERE id = ?");
    $stmt->execute([$association_id]);

    // Redirect back to candidate view
    header("Location: view_candidate.php?id=" . urlencode($candidate_id) . "&msg=Association+removed");
    exit;
} catch (PDOException $e) {
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "<div class='alert alert-danger'>Error deleting association: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "</body></html>";
}
?>

