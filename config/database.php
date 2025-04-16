<?php

$host = 'localhost';
$dbname = 'Yourdatabasename'; // your actual database name
$user = 'Yourdatabaseusername'; // your actual DB user
$pass = 'Yourdatabaseuserpassword'; // 🔐 your updated password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ Confirm PDO is connected
    if (!$pdo) {
        file_put_contents(__DIR__ . '/pdo_error.log', "PDO connection returned null.\n", FILE_APPEND);
    }
} catch (PDOException $e) {
    // 🧪 Log actual connection errors
    file_put_contents(__DIR__ . '/pdo_error.log', "PDOException: " . $e->getMessage() . "\n", FILE_APPEND);
    die("Database connection failed. Check pdo_error.log for details.");
}
?>
