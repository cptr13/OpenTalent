<?php
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: candidates.php?deleted=1");
    exit;
} else {
    echo "Invalid candidate ID.";
}
