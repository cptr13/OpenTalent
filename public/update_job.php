<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Grab and sanitize form values
    $job_id   = $_POST['id'] ?? null;
    $title    = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status   = trim($_POST['status'] ?? '');

    if ($job_id && $title && $location && $status) {
        try {
            // Prepare and execute update
            $stmt = $pdo->prepare("UPDATE jobs SET title = ?, location = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $location, $status, $job_id]);

            // Redirect to view_job.php
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
