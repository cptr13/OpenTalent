<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/require_login.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// ✅ Load DB config from shared file
$host = $config['host'];
$user = $config['user'];
$pass = $config['pass'];
$dbname = $config['dbname'];

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

$uploadSuccess = false;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_FILES['backup']) ||
        $_FILES['backup']['error'] !== UPLOAD_ERR_OK ||
        !is_uploaded_file($_FILES['backup']['tmp_name'])
    ) {
        $error = "Please select a valid ZIP file to restore.";
    } else {
        $zipFile = $_FILES['backup']['tmp_name'];

        if (!file_exists($zipFile) || filesize($zipFile) < 4) {
            $error = "Uploaded file is missing or invalid.";
        } else {
            $header = file_get_contents($zipFile, false, null, 0, 4);
            if (bin2hex($header) !== '504b0304') {
                $error = "Uploaded file is not a valid ZIP archive.";
            } else {
                $tmpRoot = __DIR__ . '/../../tmp_restore';
                if (!is_writable($tmpRoot)) {
                    $error = "The tmp_restore folder is not writable. Please check permissions.";
                } else {
                    $timestamp = date('Ymd_His');
                    $restoreDir = $tmpRoot . "/restore_" . $timestamp;
                    mkdir($restoreDir, 0755, true);

                    file_put_contents(__DIR__ . '/../../debug_restore_info.txt', print_r([
                        'zipFile' => $zipFile,
                        'exists' => file_exists($zipFile),
                        'size' => filesize($zipFile),
                        'mime' => mime_content_type($zipFile),
                        'is_uploaded' => is_uploaded_file($zipFile),
                        'header' => bin2hex($header),
                    ], true));

                    echo "<pre>";
                    echo "== DEBUG: ZIP File Upload Info ==\n";
                    echo "zipFile: $zipFile\n";
                    echo "Exists? " . (file_exists($zipFile) ? "Yes" : "No") . "\n";
                    echo "Readable? " . (is_readable($zipFile) ? "Yes" : "No") . "\n";
                    echo "Size: " . (file_exists($zipFile) ? filesize($zipFile) : 'N/A') . "\n";
                    echo "Realpath: " . realpath($zipFile) . "\n";
                    echo "Header Bytes: " . bin2hex($header) . "\n";
                    echo "</pre>";

                    $zip = new ZipArchive();
                    $zipOpenResult = $zip->open($zipFile);
                    if ($zipOpenResult === true) {
                        $zip->extractTo($restoreDir);
                        $zip->close();

                        $sqlFile = $restoreDir . '/database.sql';
                        if (!file_exists($sqlFile)) {
                            $error = "No database.sql file found in ZIP.";
                        } else {
                            $cmd = "mysql -h " . escapeshellarg($host) . " -u " . escapeshellarg($user);
                            if (!empty($pass)) {
                                $cmd .= " -p" . escapeshellarg($pass);
                            }
                            $cmd .= " " . escapeshellarg($dbname);

                            $descriptorspec = [
                                0 => ["file", $sqlFile, "r"],
                                1 => ["pipe", "w"],
                                2 => ["pipe", "w"]
                            ];

                            file_put_contents(__DIR__ . '/../../debug_mysql_command.txt', $cmd . PHP_EOL);

                            $process = proc_open($cmd, $descriptorspec, $pipes);
                            if (is_resource($process)) {
                                $stdout = stream_get_contents($pipes[1]);
                                $stderr = stream_get_contents($pipes[2]);
                                fclose($pipes[1]);
                                fclose($pipes[2]);
                                $return_value = proc_close($process);

                                if ($return_value !== 0) {
                                    $error = "Database restore failed: " . nl2br(htmlspecialchars($stderr));
                                } else {
                                    $restoredUploads = $restoreDir . '/uploads';
                                    $uploadsDir = __DIR__ . '/../uploads';

                                    if (is_dir($restoredUploads)) {
                                        rrmdir($uploadsDir);
                                        rename($restoredUploads, $uploadsDir);
                                    }

                                    $success = "Restore completed successfully.";
                                    $uploadSuccess = true;
                                }
                            } else {
                                $error = "Could not initiate database restore process.";
                            }
                        }
                    } else {
                        $error = "Failed to open ZIP archive. Error code: $zipOpenResult";
                    }

                    rrmdir($restoreDir);
                }
            }
        }
    }
}
?>

<div class="container mt-5">
    <h2>Restore Backup</h2>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" id="restoreForm">
        <div class="form-group">
            <label for="backup">Upload Backup ZIP (must include database.sql):</label>
            <input type="file" name="backup" id="backup" class="form-control-file" required>
        </div>
        <button type="button" id="submitBtn" class="btn btn-primary mt-3" disabled>Restore</button>
    </form>

    <script>
        const fileInput = document.getElementById('backup');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('restoreForm');

        fileInput.addEventListener('change', function () {
            submitBtn.disabled = !this.files.length;
        });

        submitBtn.addEventListener('click', function () {
            if (fileInput.files.length) {
                form.submit();
            }
        });
    </script>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
