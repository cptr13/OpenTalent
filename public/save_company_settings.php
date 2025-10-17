<?php
// public/save_company_settings.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: admin.php?saved=0");
    exit;
}

$company_name = trim($_POST['company_name'] ?? '');
$logo_path = null;

try {
    // Ensure uploads directory exists
    $uploadDir = __DIR__ . '/../uploads/logo/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    // Handle logo upload if provided
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        if (in_array($ext, $allowed, true)) {
            $filename = 'logo_' . time() . '.' . $ext;
            $destination = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
                $logo_path = 'uploads/logo/' . $filename;
            }
        }
    }

    // Create system_settings table if missing
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) DEFAULT NULL,
            logo_path VARCHAR(255) DEFAULT NULL
        )
    ");

    // Check if record exists
    $stmt = $pdo->query("SELECT id FROM system_settings LIMIT 1");
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        if ($logo_path) {
            $update = $pdo->prepare("UPDATE system_settings SET company_name=?, logo_path=? WHERE id=?");
            $update->execute([$company_name, $logo_path, $exists['id']]);
        } else {
            $update = $pdo->prepare("UPDATE system_settings SET company_name=? WHERE id=?");
            $update->execute([$company_name, $exists['id']]);
        }
    } else {
        $insert = $pdo->prepare("INSERT INTO system_settings (company_name, logo_path) VALUES (?, ?)");
        $insert->execute([$company_name, $logo_path]);
    }

    header("Location: admin.php?saved=1");
    exit;
} catch (Throwable $e) {
    error_log("Company settings save error: " . $e->getMessage());
    header("Location: admin.php?saved=0");
    exit;
}
