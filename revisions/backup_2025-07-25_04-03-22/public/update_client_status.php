<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'] ?? null;
    $status = trim($_POST['status'] ?? '');

    if ($client_id && $status) {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET status = ? WHERE id = ?");
            $stmt->execute([$status, $client_id]);

            // Redirect back to the client view page
            header("Location: view_client.php?id=" . urlencode($client_id));
            exit;
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Update failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Missing required fields (client_id or status).</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
}
?>
