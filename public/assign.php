<?php
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate_id = $_POST['candidate_id'] ?? null;
    $job_id = $_POST['job_id'] ?? null;
    $status = $_POST['status'] ?? 'Screening: Associated to Job';

    if ($candidate_id && $job_id) {
        try {
            // Insert new application
            $stmt = $pdo->prepare("INSERT INTO applications (candidate_id, job_id, status, assigned_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$candidate_id, $job_id, $status]);

            // Get the last inserted application_id
            $application_id = $pdo->lastInsertId();

            // Redirect to view_candidate with modal trigger
            header("Location: view_candidate.php?id=" . urlencode($candidate_id) . "&open_app=" . urlencode($application_id));
            exit;
        } catch (PDOException $e) {
            echo "Error assigning candidate: " . $e->getMessage();
        }
    } else {
        echo "Candidate ID and Job ID are required.";
    }
} else {
    echo "Invalid request method.";
}
