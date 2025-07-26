<?php
//require_once __DIR__ . '/../includes/require_login.php';

$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generate Password Hash</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">Generated Hash</div>
            <div class="card-body">
                <p><strong>Password:</strong> <code><?= htmlspecialchars($password) ?></code></p>
                <p><strong>Hash:</strong> <code><?= htmlspecialchars($hash) ?></code></p>
            </div>
        </div>
    </div>
</body>
</html>

