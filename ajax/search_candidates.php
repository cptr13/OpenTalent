<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name
        FROM candidates
        WHERE LOWER(CONCAT(first_name, ' ', last_name)) LIKE LOWER(?)
        ORDER BY first_name ASC, last_name ASC
        LIMIT 10
    ");
    $stmt->execute(['%' . $query . '%']);
    $results = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id' => $row['id'],
            'label' => trim($row['first_name'] . ' ' . $row['last_name']) // 👈 this is the key that JS uses to display
        ];
    }

    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
