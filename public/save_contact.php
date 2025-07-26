<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['name'] ?? '';
    $title = $_POST['title'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $client_id = $_POST['client_id'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                full_name, job_title, email, phone, client_id, created_at
            ) VALUES (
                :full_name, :job_title, :email, :phone, :client_id, NOW()
            )
        ");

        $stmt->execute([
            ':full_name' => $full_name,
            ':job_title' => $title,
            ':email' => $email,
            ':phone' => $phone,
            ':client_id' => $client_id ?: null
        ]);

        // Redirect back to client view if available
        if ($client_id) {
            header("Location: view_client.php?id=" . $client_id);
        } else {
            header("Location: contacts.php?success=1");
        }
        exit;
    } catch (PDOException $e) {
        echo "Error saving contact: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
