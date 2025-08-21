<?php
$config = [
    'host' => 'localhost',
    'dbname' => 'your_database_name',
    'user' => 'your_database_user',
    'pass' => 'your_database_password'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!$pdo) {
        file_put_contents(__DIR__ . '/pdo_error.log', "PDO connection returned null.\n", FILE_APPEND);
    }
} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/pdo_error.log', "PDOException: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection failed. Check pdo_error.log for details.");
}
