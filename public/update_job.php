<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Grab and sanitize form values
    $job_id     = $_POST['id'] ?? null;
    $title      = trim($_POST['title'] ?? '');
    $location   = trim($_POST['location'] ?? '');
    $status     = trim($_POST['status'] ?? '');
    $type       = trim($_POST['type'] ?? '');
    $client_id  = (is_numeric($_POST['client_id'] ?? null)) ? (int)$_POST['client_id'] : null;
    $description = trim($_POST['description'] ?? '');
    $contact_id = (is_numeric($_POST['contact_id'] ?? null)) ? (int)$_POST['contact_id'] : null;

    if ($job_id && $title && $location && $status) {
        try {
            // Update the jobs table
            $stmt = $pdo->prepare("UPDATE jobs SET title = ?, location = ?, status = ?, client_id = ?, type = ?, description = ? WHERE id = ?");
            $stmt->execute([$title, $location, $status, $client_id, $type, $description, $job_id]);

            // Clear existing job_contacts link
            $stmt = $pdo->prepare("DELETE FROM job_contacts WHERE job_id = ?");
            $stmt->execute([$job_id]);

            // Insert new contact link if provided
            if (!empty($contact_id)) {
                $stmt = $pdo->prepare("INSERT INTO job_contacts (job_id, contact_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$job_id, $contact_id]);
            }

            // Redirect to view page
            header("Location: view_job.php?id=" . urlencode($job_id));
            exit;
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Update failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='alert alert-warning'>Missing required fields:</div>";
        echo "<ul>";
        if (!$job_id)   echo "<li>ID is missing</li>";
        if (!$title)    echo "<li>Title is missing</li>";
        if (!$location) echo "<li>Location is missing</li>";
        if (!$status)   echo "<li>Status is missing</li>";
        echo "</ul>";
    }
} else {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
}
?>
