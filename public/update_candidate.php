<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo "Invalid candidate ID.";
        exit;
    }

    // Update candidate details
    $stmt = $pdo->prepare("
        UPDATE candidates SET
            name = ?,
            email = ?,
            phone = ?,
            linkedin = ?,
            facebook = ?,
            twitter = ?,
            website = ?,
            job_title = ?,
            employer = ?,
            experience = ?,
            current_salary = ?,
            expected_salary = ?,
            skills = ?,
            status = ?,
            source = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $_POST['name'] ?? '',
        $_POST['email'] ?? '',
        $_POST['phone'] ?? '',
        $_POST['linkedin'] ?? '',
        $_POST['facebook'] ?? '',
        $_POST['twitter'] ?? '',
        $_POST['website'] ?? '',
        $_POST['job_title'] ?? '',
        $_POST['employer'] ?? '',
        $_POST['experience'] ?? '',
        $_POST['current_salary'] ?? '',
        $_POST['expected_salary'] ?? '',
        $_POST['skills'] ?? '',
        $_POST['status'] ?? '',
        $_POST['source'] ?? '',
        $id
    ]);

    // Handle job assignments
    $job_ids = $_POST['job_ids'] ?? [];

    // Clear previous assignments
    $pdo->prepare("DELETE FROM applications WHERE candidate_id = ?")->execute([$id]);

    // Add new ones
    if (!empty($job_ids) && is_array($job_ids)) {
        $insertStmt = $pdo->prepare("INSERT INTO applications (candidate_id, job_id) VALUES (?, ?)");
        foreach ($job_ids as $job_id) {
            $insertStmt->execute([$id, $job_id]);
        }
    }

    header("Location: view_candidate.php?id=" . $id);
    exit;
} else {
    echo "Invalid request method.";
}
