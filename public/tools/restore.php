<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/require_login.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

$host   = $config['host'];
$user   = $config['user'];
$pass   = $config['pass'];
$dbname = $config['dbname'];

/**
 * IMPORTANT:
 * We treat schema.sql as source of truth for "engine/seed" tables.
 * Restore should NOT overwrite these.
 *
 * We will TRUNCATE everything EXCEPT these protected tables before importing SQL,
 * so restores into a fresh DB don’t collide with seeded rows (your KPI duplicate).
 */
$protectedTables = [
    // Scripts / pipeline engine tables you do NOT want overwritten
    'scripts',
    'outreach_templates',
    'script_types',
    'tone_kits',
    'tone_phrases',
    'script_templates',
    'script_rules_stage',
    'script_rules_persona',
    'script_activity_log',
    'script_templates_unified',
    'script_templates_unified_bak_20251102',
    'outreach_objections',
    'outreach_responses',

    // If you want schema defaults to ALWAYS win, add more here.
    // Example (optional): 'kpi_goals', 'kpi_status_map'
];

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function is_valid_zip_upload(array $file): array {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? -1;
        return [false, "Upload failed. PHP upload error code: {$code}"];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, "Upload failed. File not recognized as an uploaded file."];
    }
    if (!file_exists($file['tmp_name']) || filesize($file['tmp_name']) < 4) {
        return [false, "Uploaded file is missing or too small."];
    }
    $header = file_get_contents($file['tmp_name'], false, null, 0, 4);
    if (bin2hex($header) !== '504b0304') {
        return [false, "Uploaded file is not a valid ZIP archive."];
    }
    return [true, ""];
}

/**
 * Find best SQL file inside extracted backup folder.
 * Supports:
 * - legacy: database.sql
 * - newer: database_full.sql, database_content.sql
 */
function pick_sql_file(string $restoreDir): array {
    $candidates = [
        'database_content.sql',
        'database_full.sql',
        'database.sql',
    ];
    foreach ($candidates as $name) {
        $path = $restoreDir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($path) && filesize($path) > 0) {
            return [$path, $name];
        }
    }
    return [null, null];
}

/**
 * Truncate all tables except protected ones.
 * This prevents duplicate key crashes when schema.sql already seeded tables.
 */
function truncate_non_protected_tables(PDO $pdo, string $dbName, array $protectedTables): void {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    $stmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = :db
          AND TABLE_TYPE = 'BASE TABLE'
    ");
    $stmt->execute([':db' => $dbName]);

    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($tables as $t) {
        // Skip protected tables
        if (in_array($t, $protectedTables, true)) {
            continue;
        }
        // TRUNCATE is faster/cleaner than DELETE and resets auto_increment
        $pdo->exec("TRUNCATE TABLE `{$t}`");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
}

/**
 * Run mysql CLI import from a file.
 */
function mysql_import_file(string $host, string $user, string $pass, string $db, string $sqlFile): array {
    $cmd = "mysql -h " . escapeshellarg($host) . " -u " . escapeshellarg($user);
    if ($pass !== '') {
        $cmd .= " -p" . escapeshellarg($pass);
    }
    $cmd .= " " . escapeshellarg($db);

    $descriptorspec = [
        0 => ["file", $sqlFile, "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $process = proc_open($cmd, $descriptorspec, $pipes);
    if (!is_resource($process)) {
        return [false, "Could not initiate mysql restore process."];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $return_value = proc_close($process);

    if ($return_value !== 0) {
        $msg = trim($stderr) !== '' ? $stderr : ($stdout ?: 'Unknown mysql error.');
        return [false, $msg];
    }

    return [true, ""];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Validate ZIP upload
    if (!isset($_FILES['backup'])) {
        $error = "Please select a backup ZIP file.";
    } else {
        list($okZip, $zipErr) = is_valid_zip_upload($_FILES['backup']);
        if (!$okZip) {
            $error = "❌ " . $zipErr;
        } else {
            $zipFile = $_FILES['backup']['tmp_name'];

            // 2) Prepare extraction
            $tmpRoot = __DIR__ . '/../../tmp_restore';
            if (!is_dir($tmpRoot)) {
                @mkdir($tmpRoot, 0755, true);
            }
            if (!is_writable($tmpRoot)) {
                $error = "The tmp_restore folder is not writable. Please check permissions.";
            } else {
                $timestamp = date('Ymd_His');
                $restoreDir = $tmpRoot . "/restore_" . $timestamp;
                @mkdir($restoreDir, 0755, true);

                // 3) Extract ZIP
                $zip = new ZipArchive();
                $zipOpenResult = $zip->open($zipFile);
                if ($zipOpenResult !== true) {
                    $error = "Failed to open ZIP archive. Error code: $zipOpenResult";
                    rrmdir($restoreDir);
                } else {
                    $zip->extractTo($restoreDir);
                    $zip->close();

                    // 4) Pick SQL file
                    list($sqlPath, $sqlName) = pick_sql_file($restoreDir);
                    if (!$sqlPath) {
                        $error = "No SQL file found in ZIP. Expected one of: database_content.sql, database_full.sql, database.sql";
                        rrmdir($restoreDir);
                    } else {
                        // 5) Connect PDO
                        try {
                            $dsnHost = ($host === 'localhost') ? '127.0.0.1' : $host;
                            $dsn = "mysql:host={$dsnHost};dbname={$dbname};charset=utf8mb4";
                            $pdo = new PDO($dsn, $user, $pass);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                            // 6) TRUNCATE non-protected tables BEFORE import
                            truncate_non_protected_tables($pdo, $dbname, $protectedTables);

                            // 7) Import SQL
                            list($okImport, $importErr) = mysql_import_file($host, $user, (string)$pass, $dbname, $sqlPath);
                            if (!$okImport) {
                                $error = "Database restore failed:\n" . $importErr;
                            } else {
                                // 8) Restore uploads
                                $restoredUploads = $restoreDir . '/uploads';

                                // Project standard: uploads lives at project root: /uploads
                                $uploadsDir = realpath(__DIR__ . '/../../uploads');
                                if ($uploadsDir === false) {
                                    // If it doesn't exist, create it
                                    $uploadsDir = __DIR__ . '/../../uploads';
                                    @mkdir($uploadsDir, 0755, true);
                                }

                                if (is_dir($restoredUploads)) {
                                    // Replace uploads dir contents
                                    rrmdir($uploadsDir);
                                    @mkdir($uploadsDir, 0755, true);
                                    @rename($restoredUploads, $uploadsDir);
                                }

                                $success = "Restore completed successfully (protected tables preserved). SQL used: {$sqlName}";
                            }
                        } catch (Throwable $e) {
                            $error = "Restore failed: " . $e->getMessage();
                        }

                        // cleanup extracted folder
                        rrmdir($restoreDir);
                    }
                }
            }
        }
    }
}
?>

<div class="container mt-5">
    <h2>Restore Backup</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="white-space: pre-wrap;"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="alert alert-secondary">
        <strong>What this does:</strong>
        <ul class="mb-0">
            <li>Loads a backup ZIP</li>
            <li>Preserves “engine/seed” tables (scripts/pipeline/etc.) created by schema.sql</li>
            <li>Truncates everything else to avoid duplicate-key failures</li>
            <li>Imports the SQL from the ZIP</li>
            <li>Restores uploads folder if included</li>
        </ul>
    </div>

    <form method="post" enctype="multipart/form-data" id="restoreForm">
        <div class="form-group">
            <label for="backup">Upload Backup ZIP (must include database.sql or database_full.sql/database_content.sql):</label>
            <input type="file" name="backup" id="backup" class="form-control-file" required>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Restore</button>
    </form>

    <hr>

    <details class="mt-3">
        <summary><strong>Protected tables (not overwritten)</strong></summary>
        <pre class="mt-2"><?php echo htmlspecialchars(implode("\n", $protectedTables)); ?></pre>
    </details>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
