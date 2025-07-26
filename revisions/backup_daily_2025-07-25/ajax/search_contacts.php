<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$q = trim($q);

if (empty($q)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, full_name 
        FROM contacts 
        WHERE LOWER(full_name) LIKE LOWER(?) 
        ORDER BY full_name ASC 
        LIMIT 10
    ");
    $stmt->execute(['%' . $q . '%']);

    $results = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id' => $row['id'],
            'label' => $row['full_name']
        ];
    }

    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

