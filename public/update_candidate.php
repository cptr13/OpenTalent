<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function handle_file_upload($field, $upload_dir = null) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Default upload dir: /uploads/resumes/ (absolute path from this file)
    if ($upload_dir === null) {
        $upload_dir = __DIR__ . '/../uploads/resumes/';
    } else {
        // If a relative path was passed, make it absolute from this file
        if (strpos($upload_dir, __DIR__) !== 0) {
            $upload_dir = rtrim(__DIR__ . '/' . ltrim($upload_dir, '/'), '/') . '/';
        }
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Sanitize and de-dupe filename
    $original   = basename($_FILES[$field]['name']);
    $safe       = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $original);
    $filename   = time() . '_' . $safe;
    $targetPath = rtrim($upload_dir, '/') . '/' . $filename;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $targetPath)) {
        // We store just the filename in DB; serving is handled elsewhere
        return $filename;
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        echo "Invalid candidate ID.";
        exit;
    }

    // Convert empty dropdowns to NULL to avoid ENUM truncation
    $current_pay_type  = $_POST['current_pay_type'] ?? null;
    $expected_pay_type = $_POST['expected_pay_type'] ?? null;
    if ($current_pay_type === '')  $current_pay_type = null;
    if ($expected_pay_type === '') $expected_pay_type = null;

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
        'current_pay_type'  => $current_pay_type,
        'expected_pay'      => is_numeric($_POST['expected_pay']) ? (float)$_POST['expected_pay'] : null,
        'expected_pay_type' => $expected_pay_type,
        'linkedin'          => $_POST['linkedin'] ?? '',
        'additional_info'   => $_POST['additional_info'] ?? '',
        'status'            => $_POST['status'] ?? '',
        'source'            => $_POST['source'] ?? '',
        'id'                => $id
    ];

    // Handle attachments (all into /uploads/resumes/)
    $dir = __DIR__ . '/../uploads/resumes/';
    $uploads = [
        'resume_filename'            => handle_file_upload('resume_file', $dir),
        'formatted_resume_filename'  => handle_file_upload('formatted_resume_file', $dir),
        'cover_letter_filename'      => handle_file_upload('cover_letter_file', $dir),
        'contract_filename'          => handle_file_upload('contract_file', $dir),
        'other_attachment_1'         => handle_file_upload('other_attachment_1', $dir),
        'other_attachment_2'         => handle_file_upload('other_attachment_2', $dir),
    ];

    foreach ($uploads as $column => $filename) {
        if ($filename !== null) {
            $data[$column] = $filename;
        }
    }

    // Build SQL dynamically to include only uploaded file columns
    $fields = "
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
    ";

    foreach ($uploads as $column => $filename) {
        if ($filename !== null) {
            $fields .= ", $column = :$column";
        }
    }

    $sql = "UPDATE candidates SET $fields WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    // Associations handled elsewhere (associate.php)
    header("Location: view_candidate.php?id=" . $id);
    exit;
} else {
    echo "Invalid request method.";
}
