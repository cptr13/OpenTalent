<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$note_id = $_GET['id'] ?? null;
$return = $_GET['return'] ?? null;
$id_return = $_GET['id_return'] ?? null;

if (!$note_id || !$return || !$id_return) {
    echo "<div class='alert alert-danger'>Invalid request. Missing parameters.</div>";
    exit;
}

// Check if the note exists
$stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
$stmt->execute([$note_id]);
$note = $stmt->fetch();

if (!$note) {
    echo "<div class='alert alert-warning'>Note not found.</div>";
    exit;
}

// Delete the note
$stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
if ($stmt->execute([$note_id])) {
    header("Location: view_{$return}.php?id={$id_return}&msg=Note deleted successfully");
    exit;
} else {
    echo "<div class='alert alert-danger'>Failed to delete the note.</div>";
    exit;
}
?>

