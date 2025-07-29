<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';


$module_type = $_POST['module_type'] ?? '';
$module_id = $_POST['module_id'] ?? '';
$note = trim($_POST['note'] ?? '');

if (!$module_type || !$module_id || !$note) {
    die("Missing required information.");
}

try {
    // Store note in the 'content' column, add current timestamp
    $stmt = $pdo->prepare("INSERT INTO notes (module_type, module_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$module_type, $module_id, $note]);

    // Redirect based on module type with success message
    $redirectMap = [
        'candidate' => "view_candidate.php?id=$module_id",
        'client'    => "view_client.php?id=$module_id",
        'job'       => "view_job.php?id=$module_id",
        'contact'   => "view_contact.php?id=$module_id"
    ];

    $redirectTo = $redirectMap[$module_type] ?? 'index.php';
    header("Location: $redirectTo?msg=Note+added+successfully");
    exit;
} catch (PDOException $e) {
    echo "Error saving note: " . htmlspecialchars($e->getMessage());
}

