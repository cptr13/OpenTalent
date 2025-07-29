<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$lockFile = __DIR__ . '/../INSTALL_LOCKED';
$schemaFile = __DIR__ . '/../config/schema.sql';
$demoFile   = __DIR__ . '/../schema_demo.sql';  // Optional
$backupFile = __DIR__ . '/../restore/backup.sql'; // Optional

require __DIR__ . '/../config/database.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'clean';

    try {
        $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Pick the right SQL file
        $sqlFile = match($mode) {
            'clean' => $schemaFile,
            'demo'  => $demoFile,
            'restore' => $backupFile,
            default => $schemaFile
        };

        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: $sqlFile");
        }

        // Load and run the SQL
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);

        file_put_contents($lockFile, "Install complete via $mode at " . date('Y-m-d H:i:s'));
        $success = "Database setup successful! Redirecting to login page...";
        header("refresh:2;url=login.php");
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
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
    </style>
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4">OpenTalent Installation - Step 3: Load Schema</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label for="mode" class="form-label">Select Installation Type:</label>
                <select name="mode" id="mode" class="form-select">
                    <option value="clean">Clean Install (empty database)</option>
                    <option value="demo">Demo Data</option>
                    <option value="restore">Restore from Backup</option>
                </select>
            </div>
            <div class="d-flex justify-content-between">
                <a href="installer_db.php" class="btn btn-secondary">← Back</a>
                <button type="submit" class="btn btn-primary">Run Setup →</button>
            </div>
        </form>
    </div>
</body>
</html>
