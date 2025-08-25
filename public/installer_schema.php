<?php
// public/installer_schema.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session & output buffering EARLY to prevent "headers already sent"
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

$cfgPath    = __DIR__ . '/../config/database.php';
$schemaFile = __DIR__ . '/../config/schema.sql';
$demoFile   = __DIR__ . '/../schema_demo.sql';      // Optional
$backupFile = __DIR__ . '/../restore/backup.sql';   // Optional

// Invalidate opcache for config file (in case creds were changed)
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($cfgPath, true);
}

/**
 * Safely load DB config from config/database.php without letting it print
 * anything that would break redirects. Works whether the file `return`s an
 * array OR sets `$config = [...]`.
 */
function load_db_config_safely(string $path): array {
    if (!file_exists($path)) return [];

    // Capture any accidental output
    ob_start();
    $ret = @include $path;
    $noise = ob_get_clean();
    // If it printed anything, we keep the buffer but DO NOT flush yet.
    // (We’ll still be able to redirect because we started an outer buffer.)

    if (is_array($ret)) {
        return $ret;
    }

    // If file didn’t return an array, try to read `$config` from its scope
    $cfg = (static function ($p) {
        $config = null;
        // Capture any output again
        ob_start();
        @include $p;
        ob_end_clean();
        return is_array($config) ? $config : [];
    })($path);

    return $cfg;
}

$config = load_db_config_safely($cfgPath);

// Simple debug flag
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';

$error   = '';
$success = '';
$alreadyOk = !empty($_SESSION['installer_schema_ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'clean';

    try {
        if (empty($config) || !isset($config['host'], $config['dbname'], $config['user'])) {
            throw new RuntimeException('Could not load DB credentials from config/database.php');
        }

        // Normalize localhost → 127.0.0.1 (sockets vs TCP weirdness)
        $host = ($config['host'] === 'localhost') ? '127.0.0.1' : $config['host'];
        $dsn  = "mysql:host={$host};dbname={$config['dbname']};charset=utf8mb4";

        $pdo = new PDO($dsn, $config['user'], $config['pass'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Choose the SQL file
        $sqlFile = match ($mode) {
            'clean'   => $schemaFile,
            'demo'    => $demoFile,
            'restore' => $backupFile,
            default   => $schemaFile
        };

        if (!$sqlFile || !file_exists($sqlFile)) {
            throw new RuntimeException("SQL file not found: $sqlFile");
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Selected SQL file is empty or unreadable: $sqlFile");
        }

        $pdo->exec($sql);

        // Mark success in session and jump to next step
        $_SESSION['installer_schema_ok'] = true;
        $_SESSION['installer_db_host']   = $config['host'];
        $_SESSION['installer_db_name']   = $config['dbname'];
        $_SESSION['installer_db_user']   = $config['user'];

        // Try header redirect first
        $target = 'installer_admin.php';
        if (!headers_sent()) {
            // Flush the buffer so headers go first
            ob_end_clean();
            header("Location: $target", true, 302);
            exit;
        } else {
            // Fallback: JS + meta redirect
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=', htmlspecialchars($target), '"></head><body>';
            echo '<script>location.replace("', htmlspecialchars($target), '");</script>';
            echo 'Redirecting to <a href="', htmlspecialchars($target), '">Admin Setup</a>…</body></html>';
            // Ensure nothing else gets sent
            exit;
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        unset($_SESSION['installer_schema_ok']);
    }
} else {
    // GET request: if we already ran, show a continue UI
    if (!empty($_SESSION['installer_schema_ok'])) {
        $success = 'Database setup previously completed.';
    }
}

// DO NOT flush output buffer yet; we may still want to redirect via header above.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OpenTalent Installer - Schema Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 650px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">OpenTalent Installation - Step 3: Load Schema</h2>

    <?php if ($debug): ?>
        <div class="alert alert-info small">
            <div><strong>DEBUG</strong> reading <span class="mono"><?= htmlspecialchars($cfgPath) ?></span></div>
            <div>DB Host: <span class="mono"><?= htmlspecialchars($config['host'] ?? '(missing)') ?></span></div>
            <div>DB Name: <span class="mono"><?= htmlspecialchars($config['dbname'] ?? '(missing)') ?></span></div>
            <div>DB User: <span class="mono"><?= htmlspecialchars($config['user'] ?? '(missing)') ?></span></div>
            <div>Schema OK flag: <span class="mono"><?= !empty($_SESSION['installer_schema_ok']) ? 'true' : 'false' ?></span></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (empty($_SESSION['installer_schema_ok']) || $error): ?>
        <form method="POST" novalidate>
            <div class="mb-4">
                <label for="mode" class="form-label">Select Installation Type:</label>
                <select name="mode" id="mode" class="form-select">
                    <option value="clean"   <?= (($_POST['mode'] ?? '') === 'clean')   ? 'selected' : '' ?>>Clean Install (empty database)</option>
                    <option value="demo"    <?= (($_POST['mode'] ?? '') === 'demo')    ? 'selected' : '' ?>>Demo Data</option>
                    <option value="restore" <?= (($_POST['mode'] ?? '') === 'restore') ? 'selected' : '' ?>>Restore from Backup</option>
                </select>
            </div>

            <div class="d-flex justify-content-between">
                <a href="installer_db.php" class="btn btn-secondary">← Back</a>
                <button type="submit" class="btn btn-primary">Run Setup →</button>
            </div>
        </form>
    <?php else: ?>
        <div class="d-flex justify-content-between mt-3">
            <a href="installer_db.php" class="btn btn-secondary">← Back</a>
            <a href="installer_admin.php" class="btn btn-primary">Next: Admin Setup →</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
<?php
// Final safety: flush any buffered output only now.
if (ob_get_level()) {
    @ob_end_flush();
}
