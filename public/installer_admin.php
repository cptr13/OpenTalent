<?php
// public/installer_admin.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function abs_url(string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

// Paths
$lockFile     = realpath(__DIR__ . '/../') . '/INSTALL_LOCKED';
$dbConfigPath = realpath(__DIR__ . '/../') . '/config/database.php';

// If finalized, go to login
if (file_exists($lockFile)) {
    header('Location: ' . abs_url('login.php'));
    exit;
}

// Never redirect to DB automatically; we’ll block with a message instead.
$blockingError = '';
$config = [];
$pdo = null;
$currentDb = '(unknown)';

// Try load config safely (no output)
if (!is_readable($dbConfigPath)) {
    $blockingError = "Database config missing or unreadable at: {$dbConfigPath}";
} else {
    if (function_exists('opcache_invalidate')) { @opcache_invalidate($dbConfigPath, true); }
    clearstatcache(true, $dbConfigPath);

    $ret = (static function($path){
        ob_start();
        $r = @include $path;
        ob_end_clean();
        if (is_array($r)) return $r;

        $config = null;
        ob_start();
        @include $path;
        ob_end_clean();
        return is_array($config) ? $config : [];
    })($dbConfigPath);

    if (!isset($ret['host'],$ret['dbname'],$ret['user'])) {
        $blockingError = "Could not read DB credentials from config/database.php.";
    } else {
        $config = $ret;
        try {
            $host = ($config['host'] === 'localhost') ? '127.0.0.1' : $config['host'];
            $dsn  = "mysql:host={$host};dbname={$config['dbname']};charset=utf8mb4";
            $pdo  = new PDO($dsn, $config['user'], $config['pass'] ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $currentDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'users'
                LIMIT 1
            ");
            $stmt->execute();
            $hasUsers = (bool)$stmt->fetchColumn();
            if (!$hasUsers) {
                $blockingError = "Required table 'users' not found in database '{$currentDb}'. "
                               . "Run Step 3 (Load Schema) on this DB, then return here.";
            }
        } catch (Throwable $e) {
            $blockingError = "Database connection/check failed: " . $e->getMessage();
        }
    }
}

$error   = '';
$success = false;
$adminId = null;
$currentName  = 'Administrator';
$currentEmail = 'admin@example.com';

if (!$blockingError && $pdo instanceof PDO) {
    try {
        $row = $pdo->query("SELECT id, full_name, email FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1")
                   ->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $defaultPass = password_hash('HXP7Ov8ViFYb3l', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name,email,password,role,force_password_change)
                                   VALUES (?,?,?,?,1)");
            $stmt->execute(['Administrator','admin@example.com',$defaultPass,'admin']);
            $row = $pdo->query("SELECT id, full_name, email FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1")
                       ->fetch(PDO::FETCH_ASSOC);
        }
        $adminId      = $row['id'] ?? null;
        $currentName  = $row['full_name'] ?? 'Administrator';
        $currentEmail = $row['email'] ?? 'admin@example.com';
    } catch (Throwable $e) {
        $blockingError = "Failed to verify/create admin: " . $e->getMessage();
    }
}

// Handle save
if (!$blockingError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['admin_email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = trim($_POST['new_password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        try {
            $hashed   = password_hash($password, PASSWORD_DEFAULT);
            $fullName = $fullName !== '' ? $fullName : 'Administrator';

            if ($adminId) {
                $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, password=?, force_password_change=0 WHERE id=?");
                $stmt->execute([$fullName, $email, $hashed, $adminId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (full_name,email,password,role,force_password_change)
                                       VALUES (?,?,?,'admin',0)");
                $stmt->execute([$fullName, $email, $hashed]);
                $adminId = (int)$pdo->lastInsertId();
            }

            $_SESSION['installer_admin_email'] = $email;
            $_SESSION['installer_admin_name']  = $fullName;

            header('Location: ' . abs_url('installer_smtp.php'));
            exit;

        } catch (PDOException $e) {
            $error = ($e->getCode() === '23000')
                ? "That email address is already in use. Please choose a different one."
                : "Failed to update admin credentials. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>OpenTalent Installer - Admin Setup</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; }
    .container { max-width: 650px; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
  </style>
</head>
<body>
<div class="container py-5">
  <h2 class="mb-4">OpenTalent Installation - Step 4: Admin Setup</h2>

  <?php if ($blockingError): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($blockingError) ?></div>
    <div class="d-flex gap-2">
      <a class="btn btn-primary" href="<?= htmlspecialchars(abs_url('installer_schema.php')) ?>">Go to Step 3 (Load Schema) →</a>
      <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(abs_url('installer_db.php')) ?>">DB Settings</a>
    </div>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label for="admin_email" class="form-label">Admin Email</label>
        <input type="email" name="admin_email" id="admin_email" class="form-control"
               value="<?= htmlspecialchars($_POST['admin_email'] ?? $currentEmail) ?>" required>
      </div>

      <div class="mb-3">
        <label for="full_name" class="form-label">Display Name</label>
        <input type="text" name="full_name" id="full_name" class="form-control"
               value="<?= htmlspecialchars($_POST['full_name'] ?? $currentName) ?>">
      </div>

      <div class="mb-3">
        <label for="new_password" class="form-label">New Password</label>
        <input type="password" name="new_password" id="new_password" class="form-control" required>
      </div>

      <div class="mb-4">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
      </div>

      <div class="d-flex justify-content-between">
        <a href="<?= htmlspecialchars(abs_url('installer_schema.php')) ?>" class="btn btn-secondary">← Back</a>
        <button class="btn btn-primary" type="submit">Save &amp; Continue →</button>
      </div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
