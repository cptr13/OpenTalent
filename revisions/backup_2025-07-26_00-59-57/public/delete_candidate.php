<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;

// Validate ID
if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) {
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "Invalid candidate ID.";
    echo "</body></html>";
    exit;
}

try {
    $pdo->beginTransaction();

    // Delete from associations (formerly applications)
    $stmt = $pdo->prepare("DELETE FROM associations WHERE candidate_id = ?");
    $stmt->execute([$id]);

    // Delete notes tied to the candidate
    $stmt = $pdo->prepare("DELETE FROM notes WHERE candidate_id = ?");
    $stmt->execute([$id]);

    // Delete candidate record
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();

    header("Location: candidates.php?deleted=1");
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "Error deleting candidate: " . htmlspecialchars($e->getMessage());
    echo "</body></html>";
}
?>

