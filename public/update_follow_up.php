<?php
// update_follow_up.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // No direct GET access
    header('Location: contacts.php');
    exit;
}

$contact_id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$follow_up_date = $_POST['follow_up_date'] ?? null;

// Normalize empty string to NULL (removes from Upcoming Outreach)
if ($follow_up_date === '') {
    $follow_up_date = null;
}

if ($contact_id <= 0) {
    header('Location: contacts.php?error=' . urlencode('Invalid contact ID.'));
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE contacts
        SET follow_up_date = :follow_up_date
        WHERE id = :id
    ");
    $stmt->execute([
        ':follow_up_date' => $follow_up_date,
        ':id'             => $contact_id,
    ]);

    $msg = 'Follow-up date updated.';
    header('Location: view_contact.php?id=' . $contact_id . '&msg=' . urlencode($msg));
    exit;

} catch (Throwable $e) {
    $err = 'Failed to update follow-up date.';
    header('Location: view_contact.php?id=' . $contact_id . '&error=' . urlencode($err));
    exit;
}
