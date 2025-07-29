<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once '../config/database.php';

$id = $_POST['id'] ?? null;
$new_status = $_POST['outreach_status'] ?? null;

if (!$id || !$new_status) {
    die("Missing required data.");
}

// Get the old status
$stmt = $pdo->prepare("SELECT outreach_status FROM contacts WHERE id = ?");
$stmt->execute([$id]);
$old_status = $stmt->fetchColumn();

// Update the status
$stmt = $pdo->prepare("UPDATE contacts SET outreach_status = ?, last_touch_date = NOW() WHERE id = ?");
$stmt->execute([$new_status, $id]);

// Log a note
if ($old_status !== $new_status) {
    $stmt = $pdo->prepare("INSERT INTO notes (module_type, module_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([
        'contact',
        $id,
        "Outreach status changed from \"$old_status\" to \"$new_status\"."
    ]);
}

header("Location: view_contact.php?id=$id");
exit;
?>

