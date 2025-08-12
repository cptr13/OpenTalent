<?php
// Unified file_delete.php: can delete a file, clear a DB reference, or both.
// Query params:
//   path=relative/uploads/path (optional if mode=clear only)
//   mode=delete|clear|both  (default: delete)
//   candidate_id=ID (optional; required if clearing candidate field)
//   field=resume_filename|formatted_resume_filename|cover_letter_filename|other_attachment_1|other_attachment_2|contract_filename
//   return=<url>

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

$mode = $_GET['mode'] ?? 'delete';
$rel  = $_GET['path'] ?? '';
$return = $_GET['return'] ?? ($_SERVER['HTTP_REFERER'] ?? 'index.php');

// Optional candidate context for clearing a DB field
$candidate_id = isset($_GET['candidate_id']) ? (int)$_GET['candidate_id'] : 0;
$field = $_GET['field'] ?? '';

$allowedCandidateFields = [
    'resume_filename',
    'formatted_resume_filename',
    'cover_letter_filename',
    'other_attachment_1',
    'other_attachment_2',
    'contract_filename',
];

// Helpers
function redirect_with_msg(string $url, string $msg) {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    header('Location: ' . $url . $sep . 'msg=' . urlencode($msg));
    exit;
}

// Resolve uploads root
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    redirect_with_msg($return, 'Server path error');
}
$uploadsRoot = $projectRoot . '/uploads';

// Normalize path if provided
$real = null;
if (!empty($rel)) {
    $rel = str_replace('\\', '/', $rel);
    $rel = ltrim($rel, '/');
    $absolute = $uploadsRoot . '/' . $rel;
    $real = realpath($absolute);
    if ($real !== false) {
        // path jail
        if (strpos($real, $uploadsRoot) !== 0) {
            redirect_with_msg($return, 'Invalid path');
        }
    }
}

// Execute actions
$didDelete = false;
$didClear  = false;
$errors    = [];

// 1) Delete file (if mode says so and file exists)
if ($mode === 'delete' || $mode === 'both') {
    if ($real === false || $real === null || !is_file($real)) {
        // Allow delete to silently pass if file is already gone
        // (so a subsequent clear can still happen without error)
    } else {
        $didDelete = @unlink($real);
        if (!$didDelete) $errors[] = 'Delete failed';
    }
}

// 2) Clear candidate DB ref (if requested/whitelisted)
if ($mode === 'clear' || $mode === 'both') {
    if ($candidate_id > 0 && in_array($field, $allowedCandidateFields, true)) {
        try {
            $stmt = $pdo->prepare("UPDATE candidates SET {$field} = NULL WHERE id = ?");
            $stmt->execute([$candidate_id]);
            $didClear = true;
        } catch (Throwable $e) {
            $errors[] = 'DB clear failed';
        }
    } else {
        // no candidate context, ignore silently
    }
}

// Compose message
if ($errors) {
    redirect_with_msg($return, implode('; ', $errors));
}

if ($mode === 'both') {
    redirect_with_msg($return, ($didDelete ? 'File deleted; ' : '') . ($didClear ? 'Reference cleared' : ''));
} elseif ($mode === 'delete') {
    redirect_with_msg($return, $didDelete ? 'File deleted' : 'Nothing to delete');
} else { // clear
    redirect_with_msg($return, $didClear ? 'Reference cleared' : 'Nothing cleared');
}
