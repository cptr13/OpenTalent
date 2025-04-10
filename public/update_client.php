<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $company_name = $_POST['company_name'] ?? '';
    $industry = $_POST['industry'] ?? '';
    $website = $_POST['website'] ?? '';
    $location = $_POST['location'] ?? '';
    $company_size = $_POST['company_size'] ?? '';
    $account_manager = $_POST['account_manager'] ?? '';
    $status = $_POST['status'] ?? '';
    $description = $_POST['description'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if ($id) {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET 
                company_name = ?, 
                industry = ?, 
                website = ?, 
                location = ?, 
                company_size = ?, 
                account_manager = ?, 
                status = ?, 
                description = ?, 
                notes = ?
                WHERE id = ?");

            $stmt->execute([
                $company_name,
                $industry,
                $website,
                $location,
                $company_size,
                $account_manager,
                $status,
                $description,
                $notes,
                $id
            ]);

            header("Location: view_client.php?id=" . $id);
            exit;

        } catch (PDOException $e) {
            echo "Update failed: " . $e->getMessage();
        }
    } else {
        echo "Missing client ID.";
    }
} else {
    echo "Invalid request.";
}
