<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$contact_id = $_POST['contact_id'] ?? null;
$client_id = $_POST['client_id'] ?? null;

if ($contact_id && $client_id) {
    try {
        $stmt = $pdo->prepare("UPDATE contacts SET client_id = NULL WHERE id = ?");
        $stmt->execute([$contact_id]);

        header("Location: view_client.php?id=" . urlencode($client_id) . "&msg=Contact+removed+successfully");
        exit;
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error removing contact: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='alert alert-warning'>Missing contact or client ID.</div>";
}
?>

