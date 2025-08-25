<?php
// public/installer_db.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// If installer is already finalized, bounce to login
$lockFile = __DIR__ . '/../INSTALL_LOCKED';
if (file_exists($lockFile)) {
    header('Location: login.php');
    exit;
}

$configPath = __DIR__ . '/../config/database.php';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? '');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = trim($_POST['db_pass'] ?? '');

    if ($host && $name && $user) {
        try {
            // Test connection
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Ensure /config is writable before writing database.php
            $configDir = dirname($configPath);
            if (!is_writable($configDir)) {
                $error = "Unable to write database.php. Please make sure the /config directory is writable.";
            } else {
                // Always write database.php with new credentials
                $configContent = "<?php\n" .
                    '$config = [' . "\n" .
                    "    'host' => '$host',\n" .
                    "    'dbname' => '$name',\n" .
                    "    'user' => '$user',\n" .
                    "    'pass' => '$pass'\n" .
                    "];\n\n" .
                    "try {\n" .
                    "    \$pdo = new PDO(\n" .
                    "        \"mysql:host={\$config['host']};dbname={\$config['dbname']};charset=utf8mb4\",\n" .
                    "        \$config['user'],\n" .
                    "        \$config['pass']\n" .
                    "    );\n" .
                    "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n" .
                    "    if (!\$pdo) {\n" .
                    "        file_put_contents(__DIR__ . '/pdo_error.log', \"PDO connection returned null.\\n\", FILE_APPEND);\n" .
                    "    }\n" .
                    "} catch (PDOException \$e) {\n" .
                    "    file_put_contents(__DIR__ . '/pdo_error.log', \"PDOException: \" . \$e->getMessage() . \"\\n\", FILE_APPEND);\n" .
                    "    // Do not die here; let callers handle failure gracefully\n" .
                    "    \$pdo = null;\n" .
                    "}\n\n" .
                    "return \$config;\n";

                $written = file_put_contents($configPath, $configContent);
                if ($written === false) {
                    $error = "Failed to write database.php. Please check file permissions on /config.";
                } else {
                    // Success: proceed to schema step (no lock file here)
                    header("Location: installer_schema.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            // Log detailed driver message, show generic error to user
            @file_put_contents(__DIR__ . '/../config/pdo_error.log', "Installer DB connection failed: " . $e->getMessage() . "\n", FILE_APPEND);
            $error = "Connection failed. Please verify host, database name, username, and password.";
        }
    } else {
        $error = "All fields except password are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OpenTalent Installer - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 600px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4">OpenTalent Installation - Step 2: Database Setup</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="db_host" class="form-label">Database Host</label>
                <input type="text" class="form-control" name="db_host" id="db_host"
                       value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
            </div>
            <div class="mb-3">
                <label for="db_name" class="form-label">Database Name</label>
                <input type="text" class="form-control" name="db_name" id="db_name"
                       value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="db_user" class="form-label">Database User</label>
                <input type="text" class="form-control" name="db_user" id="db_user"
                       value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="db_pass" class="form-label">Database Password</label>
                <input type="password" class="form-control" name="db_pass" id="db_pass">
            </div>

            <div class="d-flex justify-content-between">
                <a href="installer.php" class="btn btn-secondary">← Back</a>
                <button type="submit" class="btn btn-primary">Test &amp; Save →</button>
            </div>
        </form>
    </div>
</body>
</html>
