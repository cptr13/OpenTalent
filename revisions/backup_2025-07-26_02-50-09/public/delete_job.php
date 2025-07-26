<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: jobs.php?deleted=1");
    exit;
} else {
    echo "Invalid job ID.";
}
