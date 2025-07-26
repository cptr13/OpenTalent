<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo "Invalid candidate ID.";
        exit;
    }

    // Prepare update
    $stmt = $pdo->prepare("
        UPDATE candidates SET
            first_name = ?,
            last_name = ?,
            email = ?,
            phone = ?,
            linkedin = ?,
            current_employer = ?,
            current_salary = ?,
            expected_salary = ?,
            skills = ?,
            status = ?,
            source = ?
        WHERE id = ?
    ");

    $stmt->execute([
        trim($_POST['first_name'] ?? ''),
        trim($_POST['last_name'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['phone'] ?? ''),
        trim($_POST['linkedin'] ?? ''),
        trim($_POST['current_employer'] ?? ''),
        trim($_POST['current_salary'] ?? ''),
        trim($_POST['expected_salary'] ?? ''),
        trim($_POST['skills'] ?? ''),
        trim($_POST['status'] ?? ''),
        trim($_POST['source'] ?? ''),
        $id
    ]);

    // Handle job assignments
    $job_ids = $_POST['job_ids'] ?? [];

    // Remove old associations
    $pdo->prepare("DELETE FROM associations WHERE candidate_id = ?")->execute([$id]);

    // Insert new associations
    if (!empty($job_ids) && is_array($job_ids)) {
        $insertStmt = $pdo->prepare("INSERT INTO associations (candidate_id, job_id) VALUES (?, ?)");
        foreach ($job_ids as $job_id) {
            $insertStmt->execute([$id, $job_id]);
        }
    }

    header("Location: view_candidate.php?id=" . $id);
    exit;
} else {
    echo "Invalid request method.";
}

