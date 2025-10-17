<?php
// public/delete_script.php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? 'retire'; // retire | activate

if ($id <= 0) {
    http_response_code(400);
    echo "Missing or invalid id.";
    exit;
}

$val = ($action === 'activate') ? 1 : 0;

try {
    $stmt = $pdo->prepare("UPDATE scripts SET is_active = :v WHERE id = :id");
    $stmt->execute([':v' => $val, ':id' => $id]);

    // Redirect back to list (preserve minimal context if provided)
    $redirect = 'scripts.php';
    if (!empty($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
        $redirect = $_SERVER['HTTP_REFERER'];
    }
    header("Location: $redirect");
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error updating script status.";
}
