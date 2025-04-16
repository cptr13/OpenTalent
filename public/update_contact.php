<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $client_id = $_POST['client_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $title = $_POST['title'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (!$id || !$client_id) {
        echo "Missing contact or client ID.";
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE contacts
            SET client_id = :client_id,
                full_name = :name,
                job_title = :title,
                email = :email,
                phone = :phone
            WHERE id = :id
        ");

        $stmt->execute([
            ':client_id' => $client_id,
            ':name' => $name,
            ':title' => $title,
            ':email' => $email,
            ':phone' => $phone,
            ':id' => $id
        ]);

        header("Location: contacts.php?success=1");
        exit;
    } catch (PDOException $e) {
        echo "Error updating contact: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
