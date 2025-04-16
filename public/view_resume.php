<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    die("Unauthorized access.");
}

$upload_dir = __DIR__ . '/../uploads/';
$filename = basename($_GET['file'] ?? '');

$file_path = realpath($upload_dir . $filename);

// Security check: ensure the file is inside the upload folder and exists
if (
    $file_path &&
    strpos($file_path, realpath($upload_dir)) === 0 &&
    file_exists($file_path)
) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Content-Length: ' . filesize($file_path));
    flush();
    readfile($file_path);
    exit;
} else {
    echo "File not found or access denied.";
}
