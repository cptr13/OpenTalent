<?php
// public/installer_admin.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$lockFile = __DIR__ . '/../INSTALL_LOCKED';
$config = require __DIR__ . '/../config/database.php';

$error = '';
$success = false;

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure admin user exists — create if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute(['admin@example.com']);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        $defaultPass = password_hash('HXP7Ov8ViFYb3l', PASSWORD_DEFAULT);
        $createStmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, force_password_change) VALUES (?, ?, ?, ?, ?)");
        $createStmt->execute(['Administrator', 'admin@example.com', $defaultPass, 'admin', 1]);
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET password = ?, force_password_change = 0 WHERE email = ?");
            $stmt->execute([$hashed, 'admin@example.com']);

            file_put_contents($lockFile, "Install completed and admin password reset on " . date('Y-m-d H:i:s'));
            $success = true;
            header("refresh:2;url=login.php");
        } catch (Exception $e) {
            $error = "Error updating password: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OpenTalent Installer - Admin Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 600px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4">OpenTalent Installation - Step 4: Set Admin Password</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">Password updated successfully. Redirecting to login...</div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Set Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
