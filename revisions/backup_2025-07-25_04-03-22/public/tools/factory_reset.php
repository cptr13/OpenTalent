<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ROOT_PATH', dirname(__DIR__, 2)); // Goes from /public/tools/ → project root

//require_once ROOT_PATH . '/includes/require_login.php';
require_once ROOT_PATH . '/config/database.php';

$confirmation = $_POST['confirm'] ?? null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirmation === 'YES') {
    try {
        // Step 1: Disable foreign key checks and drop all tables
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        // Log which tables were found
        file_put_contents(ROOT_PATH . '/debug_reset_tables.txt', implode("\n", $tables));

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Step 2: Re-run schema.sql
        $schemaPath = ROOT_PATH . '/config/schema.sql';
        if (!file_exists($schemaPath)) {
            throw new Exception("Missing schema.sql — cannot reset without a valid schema file.");
        }

        $schemaSql = file_get_contents($schemaPath);
        if (empty($schemaSql)) {
            throw new Exception("schema.sql is empty or unreadable.");
        }

        // Split and execute SQL statements one-by-one
        $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }

        // Step 3: Wipe uploads folder contents
        $uploadDirs = [
            ROOT_PATH . '/uploads/resumes/',
            ROOT_PATH . '/uploads/attachments/'
        ];
        foreach ($uploadDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }

        $message = "<div class='alert alert-success'>Factory reset completed successfully.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Error during reset: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Factory Reset</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4 text-danger">⚠️ Factory Reset</h2>
    <?= $message ?>
    <div class="card border-danger">
        <div class="card-body">
            <p>This will permanently delete <strong>all database records</strong> and <strong>uploaded files</strong>.</p>
            <p><strong>This action cannot be undone.</strong></p>
            <form method="post">
                <div class="form-group">
                    <label for="confirm">Type <strong>YES</strong> to confirm:</label>
                    <input type="text" name="confirm" id="confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-danger">Run Factory Reset</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>

