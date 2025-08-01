<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$contact_id = $_POST['id'] ?? null;
$new_stage = $_POST['outreach_stage'] ?? null;

if (!$contact_id || !$new_stage) {
    header("Location: view_contact.php?id=$contact_id&msg=Missing+data");
    exit;
}

// Define stage labels for logging
$touchLabels = [
    1 => 'Touch 1 – Email #1',
    2 => 'Touch 2 – LinkedIn #1',
    3 => 'Touch 3 – Email #2',
    4 => 'Touch 4 – LinkedIn #2',
    5 => 'Touch 5 – Email #3',
    6 => 'Touch 6 – LinkedIn #3',
    7 => 'Touch 7 – Email #4',
    8 => 'Touch 8 – LinkedIn #4',
    9 => 'Touch 9 – Email #5',
    10 => 'Touch 10 – LinkedIn #5',
    11 => 'Touch 11 – Call / Voicemail',
    12 => 'Touch 12 – Breakup Email',
];

$stageLabel = $touchLabels[(int)$new_stage] ?? "Touch $new_stage";

// Define delay until next touch (in days)
$nextTouchDelays = [
    1 => 2,
    2 => 2,
    3 => 3,
    4 => 2,
    5 => 3,
    6 => 2,
    7 => 2,
    8 => 2,
    9 => 2,
    10 => 1,
    11 => 1,
    12 => 0 // No next step
];

$delayDays = $nextTouchDelays[(int)$new_stage] ?? null;

// Build SQL to update outreach_stage, last_touch_date, and follow_up_date
if ($delayDays === 0) {
    // Clear follow-up on breakup
    $stmt = $pdo->prepare("UPDATE contacts SET outreach_stage = ?, last_touch_date = NOW(), follow_up_date = NULL WHERE id = ?");
    $stmt->execute([$new_stage, $contact_id]);
} elseif ($delayDays !== null) {
    // Set next follow-up date
    $stmt = $pdo->prepare("UPDATE contacts SET outreach_stage = ?, last_touch_date = NOW(), follow_up_date = DATE_ADD(CURDATE(), INTERVAL ? DAY) WHERE id = ?");
    $stmt->execute([$new_stage, $delayDays, $contact_id]);
} else {
    // Fallback if delay is undefined
    $stmt = $pdo->prepare("UPDATE contacts SET outreach_stage = ?, last_touch_date = NOW() WHERE id = ?");
    $stmt->execute([$new_stage, $contact_id]);
}

// Add auto-generated note
$stmt = $pdo->prepare("INSERT INTO notes (module_type, module_id, content, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([
    'contact',
    $contact_id,
    "Outreach stage changed to: $stageLabel"
]);

header("Location: view_contact.php?id=$contact_id&msg=Outreach+stage+updated");
exit;
