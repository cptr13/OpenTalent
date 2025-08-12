<?php
require_once __DIR__ . '/../includes/require_login.php';

// Base uploads directory (parent of all categories)
$baseDir = realpath(__DIR__ . '/../uploads/');
if ($baseDir === false) {
    http_response_code(500);
    echo "Storage path not found.";
    exit;
}

// Get and sanitize requested file path
// Example: file=clients/contracts/filename.pdf
$requestedFile = $_GET['file'] ?? '';
if ($requestedFile === '') {
    http_response_code(400);
    echo "Missing file parameter.";
    exit;
}

// Build the full path safely
$filePath = realpath($baseDir . DIRECTORY_SEPARATOR . $requestedFile);

// Security: ensure resolved path is inside $baseDir
if (
    $filePath === false ||
    strpos($filePath, $baseDir) !== 0 ||
    !is_file($filePath) ||
    !is_readable($filePath)
) {
    http_response_code(404);
    echo "File not found or access denied.";
    exit;
}

// Detect mime type (fallback to octet-stream)
$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
        $detected = finfo_file($f, $filePath);
        if ($detected) $mime = $detected;
        finfo_close($f);
    }
}

// Clean any active output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Decide content disposition (view or download)
$disposition = isset($_GET['view']) && $_GET['view'] == 1 ? 'inline' : 'attachment';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . basename($filePath) . '"');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
