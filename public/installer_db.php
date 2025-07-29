<?php
// public/installer_db.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$configPath = __DIR__ . '/../config/database.php';
$lockFile = __DIR__ . '/../INSTALL_LOCKED';
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
                "    die(\"Database connection failed. Check pdo_error.log for details.\");\n" .
                "}\n";

            file_put_contents($configPath, $configContent);

            // Write install lock
            file_put_contents($lockFile, "Installation completed at " . date('Y-m-d H:i:s'));

            header("Location: installer_schema.php");
            exit;
        } catch (PDOException $e) {
            $error = "Connection failed: " . $e->getMessage();
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
                <button type="submit" class="btn btn-primary">Test & Save →</button>
            </div>
        </form>
    </div>
</body>
</html>
