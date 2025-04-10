<?php
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: contacts.php?deleted=1");
    exit;
} else {
    echo "Invalid contact ID.";
}
