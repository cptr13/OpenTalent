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

    $data = [
        'first_name'        => trim($_POST['first_name'] ?? ''),
        'last_name'         => trim($_POST['last_name'] ?? ''),
        'email'             => $_POST['email'] ?? '',
        'secondary_email'   => $_POST['secondary_email'] ?? '',
        'phone'             => $_POST['phone'] ?? '',
        'street'            => $_POST['street'] ?? '',
        'city'              => $_POST['city'] ?? '',
        'state'             => $_POST['state'] ?? '',
        'zip'               => $_POST['zip'] ?? '',
        'country'           => $_POST['country'] ?? '',
        'experience_years'  => is_numeric($_POST['experience_years']) ? (int)$_POST['experience_years'] : null,
        'current_job'       => $_POST['current_job'] ?? '',
        'current_employer'  => $_POST['employer'] ?? '',
        'current_pay'       => is_numeric($_POST['current_pay']) ? (float)$_POST['current_pay'] : null,
        'current_pay_type'  => $_POST['current_pay_type'] ?? '',
        'expected_pay'      => is_numeric($_POST['expected_pay']) ? (float)$_POST['expected_pay'] : null,
        'expected_pay_type' => $_POST['expected_pay_type'] ?? '',
        'linkedin'          => $_POST['linkedin'] ?? '',
        'additional_info'   => $_POST['additional_info'] ?? '',
        'status'            => $_POST['status'] ?? '',
        'source'            => $_POST['source'] ?? '',
        'id'                => $id
    ];

    $sql = "
        UPDATE candidates SET
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            secondary_email = :secondary_email,
            phone = :phone,
            street = :street,
            city = :city,
            state = :state,
            zip = :zip,
            country = :country,
            experience_years = :experience_years,
            current_job = :current_job,
            current_employer = :current_employer,
            current_pay = :current_pay,
            current_pay_type = :current_pay_type,
            expected_pay = :expected_pay,
            expected_pay_type = :expected_pay_type,
            linkedin = :linkedin,
            additional_info = :additional_info,
            status = :status,
            source = :source
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    // Job associations
    $job_ids = $_POST['job_ids'] ?? [];
    $pdo->prepare("DELETE FROM associations WHERE candidate_id = ?")->execute([$id]);

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
