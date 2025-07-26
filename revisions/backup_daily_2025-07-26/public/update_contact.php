<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $client_id = $_POST['client_id'] ?? null;
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $title = $_POST['title'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $follow_up_date = $_POST['follow_up_date'] ?? null;
    $follow_up_notes = $_POST['follow_up_notes'] ?? '';
    $outreach_stage = $_POST['outreach_stage'] ?? 1;
    $last_touch_date = $_POST['last_touch_date'] ?? null;
    $outreach_status = $_POST['outreach_status'] ?? 'Active';

    if (!$id || !$client_id) {
        echo "Missing contact or client ID.";
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE contacts
            SET client_id = :client_id,
                first_name = :first_name,
                last_name = :last_name,
                title = :title,
                email = :email,
                phone = :phone,
                follow_up_date = :follow_up_date,
                follow_up_notes = :follow_up_notes,
                outreach_stage = :outreach_stage,
                last_touch_date = :last_touch_date,
                outreach_status = :outreach_status
            WHERE id = :id
        ");

        $stmt->execute([
            ':client_id' => $client_id,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':title' => $title,
            ':email' => $email,
            ':phone' => $phone,
            ':follow_up_date' => $follow_up_date ?: null,
            ':follow_up_notes' => $follow_up_notes,
            ':outreach_stage' => $outreach_stage,
            ':last_touch_date' => $last_touch_date ?: null,
            ':outreach_status' => $outreach_status,
            ':id' => $id
        ]);

        header("Location: view_contact.php?id=" . $id . "&msg=Contact+updated+successfully");
        exit;
    } catch (PDOException $e) {
        echo "Error updating contact: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}

