<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';  // input still named company_name in form, maps to 'name' in DB
    $industry = $_POST['industry'] ?? '';
    $url = $_POST['website'] ?? '';        // input named 'website', maps to 'url' in DB
    $location = $_POST['location'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $account_manager = $_POST['account_manager'] ?? '';
    $status = $_POST['status'] ?? '';
    $about = $_POST['about'] ?? '';
    $notes = $_POST['notes'] ?? '';        // placeholder — to be removed/handled later

    if ($id) {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET 
                name = ?, 
                industry = ?, 
                url = ?, 
                location = ?, 
                phone = ?, 
                account_manager = ?, 
                status = ?, 
                about = ?,
                updated_at = NOW()
                WHERE id = ?");

            $stmt->execute([
                $name,
                $industry,
                $url,
                $location,
                $phone,
                $account_manager,
                $status,
                $about,
                $id
            ]);

            header("Location: view_client.php?id=" . urlencode($id));
            exit;

        } catch (PDOException $e) {
            error_log("Client update failed: " . $e->getMessage());
            echo "<div class='alert alert-danger'>An error occurred while updating the client. Please try again later.</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Missing client ID. Update cannot proceed.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
}

