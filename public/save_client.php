<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $account_manager = $_POST['account_manager'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $fax = $_POST['fax'] ?? '';
    $website = $_POST['website'] ?? '';
    $about = $_POST['about'] ?? '';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                name, account_manager, contact_number, fax, website, about, created_at
            ) VALUES (
                :name, :account_manager, :contact_number, :fax, :website, :about, NOW()
            )
        ");

        $stmt->execute([
            ':name' => $name,
            ':account_manager' => $account_manager,
            ':contact_number' => $contact_number,
            ':fax' => $fax,
            ':website' => $website,
            ':about' => $about
        ]);

        header("Location: clients.php?success=1");
        exit;
    } catch (PDOException $e) {
        echo "Error saving client: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
