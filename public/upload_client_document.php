<?php
// upload_client_document.php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Config
$MAX_BYTES = 15 * 1024 * 1024;
$ALLOWED = ['pdf','doc','docx','rtf','txt','xls','xlsx','csv','ppt','pptx','png','jpg','jpeg','webp'];

// Inputs
$client_id = (int)($_POST['client_id'] ?? 0);
$category  = trim($_POST['category'] ?? '');
$file      = $_FILES['doc'] ?? null;

function normalize_filename(string $name): string {
    // Keep extension; sanitize basename
    $name = str_replace('\\', '/', $name);
    $base = basename($name);
    $parts = explode('.', $base);
    $ext = '';
    if (count($parts) > 1) {
        $ext = array_pop($parts);
        $baseNoExt = implode('.', $parts);
    } else {
        $baseNoExt = $base;
    }
    // Replace weird chars
    $baseNoExt = preg_replace('/[^A-Za-z0-9_\- ]+/', '_', $baseNoExt);
    $baseNoExt = trim($baseNoExt);
    if ($baseNoExt === '') $baseNoExt = 'file';
    $ts = date('Ymd_His');
    $rand = bin2hex(random_bytes(3));
    return $baseNoExt . '_' . $ts . '_' . $rand . ($ext ? '.' . strtolower($ext) : '');
}

if ($client_id <= 0 || $category === '' || !$file) {
    header("Location: view_client.php?id={$client_id}&msg=" . urlencode("Invalid upload request"));
    exit;
}

// Validate size
if ($file['error'] !== UPLOAD_ERR_OK) {
    header("Location: view_client.php?id={$client_id}&msg=" . urlencode("Upload error: " . $file['error']));
    exit;
}
if ($file['size'] > $MAX_BYTES) {
    header("Location: view_client.php?id={$client_id}&msg=" . urlencode("File too large (max 15MB)"));
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED, true)) {
    header("Location: view_client.php?id={$client_id}&msg=" . urlencode("Unsupported file type"));
    exit;
}

// Build target path
$projectRoot = realpath(__DIR__ . '/..');
$uploadsRoot = $projectRoot . '/uploads';
$targetDir   = $uploadsRoot . "/clients/{$client_id}/{$category}";

// Ensure dir
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0775, true);
}

// Normalize and move
$cleanName = normalize_filename($file['name']);
$target = $targetDir . '/' . $cleanName;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    header("Location: view_client.php?id={$client_id}&msg=" . urlencode("Failed to save file"));
    exit;
}

header("Location: view_client.php?id={$client_id}&msg=" . urlencode("File uploaded"));
exit;
