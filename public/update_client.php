<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

            // Safe redirect after update
            header("Location: view_client.php?id=" . urlencode($id));
            exit;

        } catch (PDOException $e) {
            // Log error privately (not shown to users)
            error_log("Client update failed: " . $e->getMessage());

            // Show safe message
            echo "<div class='alert alert-danger'>An error occurred while updating the client. Please try again later.</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Missing client ID. Update cannot proceed.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
}
?>
