<?php
$passwords = [
    'Nhl8S0waolDSsx',
    '0tnvw37u24Bdko',
    'viewer123'
];

foreach ($passwords as $label => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<strong>Password:</strong> $password<br>";
    echo "<strong>Hash:</strong> $hash<br><br>";
}
?>
