<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = $_POST['job_id'] ?? null;
    $new_status = trim($_POST['status'] ?? '');

    if ($job_id && $new_status) {
        try {
            $stmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $job_id]);

            header("Location: view_job.php?id=" . urlencode($job_id) . "&msg=Job+status+updated");
            exit;
        } catch (PDOException $e) {
            echo "Error updating job status: " . htmlspecialchars($e->getMessage());
        }
    } else {
        echo "Missing job ID or status.";
    }
} else {
    echo "Invalid request method.";
}

