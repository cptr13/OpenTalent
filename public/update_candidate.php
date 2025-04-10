<?php
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo "Invalid candidate ID.";
        exit;
    }

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

    header("Location: view_candidate.php?id=" . $id);
    exit;
} else {
    echo "Invalid request method.";
}
