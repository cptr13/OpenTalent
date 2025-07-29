<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$q = trim($q);

if (empty($q)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, name 
    FROM clients 
    WHERE LOWER(name) LIKE LOWER(?) 
    ORDER BY name ASC 
    LIMIT 10
");
$stmt->execute(['%' . $q . '%']);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return in format: [{ label: "General Motors", value: 87 }, ...]
$results = array_map(function($client) {
    return [
        'label' => $client['name'],
        'value' => $client['id']
    ];
}, $clients);

echo json_encode($results);

