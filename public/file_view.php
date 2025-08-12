<?php
require_once __DIR__ . '/../includes/require_login.php';

// Base /uploads directory (one level up from /public)
$baseDir = realpath(__DIR__ . '/../uploads');
if ($baseDir === false) {
    http_response_code(500);
    echo "Storage path not found.";
    exit;
}

// Expect a relative path under /uploads, e.g. "resumes/foo.pdf" or "clients/42/contracts/bar.pdf"
$rel = $_GET['path'] ?? '';
$rel = ltrim($rel, "/"); // remove any leading slash

if ($rel === '' || strpos($rel, "\0") !== false) {
    http_response_code(400);
    echo "Missing or invalid path parameter.";
    exit;
}

$fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . $rel);

// Security: ensure resolved path exists and is still under /uploads
if (
    $fullPath === false ||
    strncmp($fullPath, $baseDir, strlen($baseDir)) !== 0 ||
    !is_file($fullPath) ||
    !is_readable($fullPath)
) {
    http_response_code(404);
    echo "File not found or access denied.";
    exit;
}

// Detect MIME type (fall back to octet-stream)
$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
        $detected = finfo_file($f, $fullPath);
        if ($detected) $mime = $detected;
        finfo_close($f);
    }
}

// Clean output buffers before streaming
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . rawurlencode(basename($fullPath)) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
