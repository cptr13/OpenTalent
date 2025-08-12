<?php
// file_download.php
// Streams a file for download from /uploads with path jail security.
// Adds basic download logging. No HTML output.

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php'; // safe: no output

ini_set('display_errors', 0);
error_reporting(E_ALL);

$rel = $_GET['path'] ?? '';
if ($rel === '') {
    http_response_code(400);
    exit('Missing path.');
}

// Normalize the relative path
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');

// Resolve base paths
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    http_response_code(500);
    exit('Server path error.');
}
$uploadsRoot = $projectRoot . '/uploads';

// Build absolute path
$absolute = $uploadsRoot . '/' . $rel;

// Realpath + jail
$real = realpath($absolute);
if ($real === false || strpos($real, $uploadsRoot) !== 0 || !is_file($real)) {
    http_response_code(404);
    exit('File not found.');
}

// Guess mime
$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($real);
    if ($detected) $mime = $detected;
}

$basename = basename($real);
$filesize = @filesize($real);

// Clear any buffers
while (ob_get_level() > 0) { ob_end_clean(); }

// Headers
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $basename . '"; filename*=UTF-8\'\'' . rawurlencode($basename));
header('Content-Transfer-Encoding: binary');
if ($filesize !== false) {
    header('Content-Length: ' . $filesize);
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Log download (best-effort)
try {
    // Create table if needed (id, user_id, path, ip, ua, created_at)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS download_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            path VARCHAR(1024) NOT NULL,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $user_id = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $stmt = $pdo->prepare("INSERT INTO download_logs (user_id, path, ip, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $rel, $ip, $ua]);
} catch (Throwable $e) {
    // ignore logging failures
}

// Stream
$fp = fopen($real, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Could not open file.');
}
fpassthru($fp);
fclose($fp);
exit;
