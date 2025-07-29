<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $title = $_POST['title'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $client_id = $_POST['client_id'] ?? null;
    $follow_up_date = $_POST['follow_up_date'] ?? null;
    $follow_up_notes = $_POST['follow_up_notes'] ?? '';
    $outreach_stage = $_POST['outreach_stage'] ?? 1;
    $last_touch_date = $_POST['last_touch_date'] ?? null;
    $outreach_status = $_POST['outreach_status'] ?? 'Active';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                first_name,
                last_name,
                title,
                email,
                phone,
                client_id,
                follow_up_date,
                follow_up_notes,
                outreach_stage,
                last_touch_date,
                outreach_status,
                created_at
            ) VALUES (
                :first_name,
                :last_name,
                :title,
                :email,
                :phone,
                :client_id,
                :follow_up_date,
                :follow_up_notes,
                :outreach_stage,
                :last_touch_date,
                :outreach_status,
                NOW()
            )
        ");

        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':title' => $title,
            ':email' => $email,
            ':phone' => $phone,
            ':client_id' => $client_id ?: null,
            ':follow_up_date' => $follow_up_date ?: null,
            ':follow_up_notes' => $follow_up_notes,
            ':outreach_stage' => $outreach_stage,
            ':last_touch_date' => $last_touch_date ?: null,
            ':outreach_status' => $outreach_status
        ]);

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

