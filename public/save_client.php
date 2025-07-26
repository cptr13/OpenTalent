<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $industry = $_POST['industry'] ?? '';
    $location = $_POST['location'] ?? '';
    $account_manager = $_POST['account_manager'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $fax = $_POST['fax'] ?? '';
    $website = $_POST['website'] ?? '';
    $about = $_POST['about'] ?? '';
    $primary_contact_id = $_POST['primary_contact_id'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                name, industry, location, account_manager,
                contact_number, fax, website, about,
                primary_contact_id, created_at
            ) VALUES (
                :name, :industry, :location, :account_manager,
                :contact_number, :fax, :website, :about,
                :primary_contact_id, NOW()
            )
        ");

        $stmt->execute([
            ':name' => $name,
            ':industry' => $industry,
            ':location' => $location,
            ':account_manager' => $account_manager,
            ':contact_number' => $contact_number,
            ':fax' => $fax,
            ':website' => $website,
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
