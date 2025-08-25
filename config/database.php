<?php
$config = [
    'host' => 'localhost',
    'dbname' => 'ot_master_3',
    'user' => 'ot_master_3',
    'pass' => 'ot_master_3'
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
    // Do not die here; let callers handle failure gracefully
    $pdo = null;
}

return $config;
