<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $industry = $_POST['industry'] ?? '';
    $location = $_POST['location'] ?? '';
    $account_manager = $_POST['account_manager'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $website = $_POST['website'] ?? ''; // from form input
    $about = $_POST['about'] ?? '';
    $primary_contact_id = $_POST['primary_contact_id'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                name, industry, location, account_manager,
                phone, url, about,
                primary_contact_id, created_at
            ) VALUES (
                :name, :industry, :location, :account_manager,
                :phone, :url, :about,
                :primary_contact_id, NOW()
            )
        ");

        $stmt->execute([
            ':name' => $name,
            ':industry' => $industry,
            ':location' => $location,
            ':account_manager' => $account_manager,
            ':phone' => $phone,
            ':url' => $website, // bound to "url" in DB
            ':about' => $about,
            ':primary_contact_id' => $primary_contact_id ?: null
        ]);

        header("Location: clients.php?success=1");
        exit;
    } catch (PDOException $e) {
        echo "Error saving client: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}

