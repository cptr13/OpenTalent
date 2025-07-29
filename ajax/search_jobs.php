<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title FROM jobs WHERE title LIKE ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute(['%' . $q . '%']);

    $results = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id' => $row['id'],
            'label' => $row['title'] // ✅ correct key for JS autocomplete
        ];
    }

    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

