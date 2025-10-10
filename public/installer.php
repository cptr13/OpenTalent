<?php
// public/installer.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Prevent rerunning installer
$lockFile = __DIR__ . '/../INSTALL_LOCKED';
if (file_exists($lockFile)) {
    die('<h2>Installer is locked.</h2><p>To run the installer again, delete the file <code>INSTALL_LOCKED</code> in the root directory.</p>');
}

// Check system requirements
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');

$configWritable  = is_writable(__DIR__ . '/../config');
$uploadsWritable = is_writable(__DIR__ . '/../uploads');

$requirements = [
    'PHP Version (>= 7.4)'          => $phpOk,
    'PDO Extension'                  => extension_loaded('pdo'),
    'PDO MySQL Extension'            => extension_loaded('pdo_mysql'),
    'OpenSSL Extension'              => extension_loaded('openssl'),
    'Writable: /config/'             => $configWritable,
    'Writable: /uploads/'            => $uploadsWritable,
];

$allPassed = !in_array(false, $requirements, true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OpenTalent Installer - System Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 700px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4">OpenTalent Installation - Step 1: System Check</h2>

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Requirement</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirements as $label => $passed): ?>
                    <tr class="<?= $passed ? 'table-success' : 'table-danger' ?>">
                        <td><?= htmlspecialchars($label) ?></td>
                        <td><?= $passed ? '✅ Passed' : '❌ Failed' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($allPassed): ?>
            <div class="d-flex justify-content-end mt-4">
                <a href="installer_db.php" class="btn btn-primary">Next: Database Setup →</a>
            </div>
        <?php else: ?>
            <div class="alert alert-danger mt-4">
                Please fix the failed requirements above before continuing.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
