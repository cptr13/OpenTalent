<?php
session_start();
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $owner = $_POST['owner'] ?? ($_SESSION['user']['full_name'] ?? 'Unknown');

    if (empty($firstName) || empty($lastName)) {
        echo "Error: First and Last name are required.";
        exit;
    }

    // Default null
    $resumeFilename = null;

    // Resume upload (Priority 1: uploaded file)
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/resumes/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $originalName = basename($_FILES['resume']['name']);
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
        $targetPath = $uploadDir . $safeName;

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
            $resumeFilename = $safeName;
        } else {
            echo "<div style='color:red;'>Resume upload failed: " . htmlspecialchars($_FILES['resume']['error']) . "</div>";
            exit;
        }
    } elseif (!empty($_POST['source_file'])) {
        $parsedFile = basename($_POST['source_file']);
        $resumePath = __DIR__ . '/../uploads/resumes/' . $parsedFile;
        if (file_exists($resumePath)) {
            $resumeFilename = $parsedFile;
        } else {
            echo "<div style='color:red;'>Error: Parsed resume not found on server.</div>";
            exit;
        }
    }

    // Collect form data
    $data = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $_POST['email'] ?? '',
        'secondary_email' => $_POST['secondary_email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'street' => $_POST['street'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'zip' => $_POST['zip'] ?? '',
        'country' => $_POST['country'] ?? '',
        'experience_years' => is_numeric($_POST['experience_years']) ? (int)$_POST['experience_years'] : null,
        'current_job' => $_POST['current_job'] ?? '',
        'current_employer' => $_POST['current_employer'] ?? '',
        'current_pay' => is_numeric($_POST['current_pay']) ? (float)$_POST['current_pay'] : null,
        'current_pay_type' => $_POST['current_pay_type'] ?? '',
        'expected_pay' => is_numeric($_POST['expected_pay']) ? (float)$_POST['expected_pay'] : null,
        'expected_pay_type' => $_POST['expected_pay_type'] ?? '',
        'linkedin' => $_POST['linkedin'] ?? '',
        'additional_info' => $_POST['additional_info'] ?? '',
        'resume_text' => $_POST['resume_text'] ?? '',
        'resume_filename' => $resumeFilename,
        'status' => $_POST['status'] ?? 'New',
        'source' => $_POST['source'] ?? '',
        'owner' => $owner,
        'created_at' => date('Y-m-d H:i:s')
    ];

    try {
        $sql = "INSERT INTO candidates (
                    first_name, last_name, email, secondary_email, phone,
                    street, city, state, zip, country,
                    experience_years, current_job, current_employer,
                    current_pay, current_pay_type, expected_pay, expected_pay_type,
                    linkedin, additional_info,
                    resume_text, resume_filename,
                    status, source, owner, created_at
                ) VALUES (
                    :first_name, :last_name, :email, :secondary_email, :phone,
                    :street, :city, :state, :zip, :country,
                    :experience_years, :current_job, :current_employer,
                    :current_pay, :current_pay_type, :expected_pay, :expected_pay_type,
                    :linkedin, :additional_info,
                    :resume_text, :resume_filename,
                    :status, :source, :owner, :created_at
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        header("Location: candidates.php?success=1");
        exit;
    } catch (PDOException $e) {
        echo "<div style='color:red;'>Error saving candidate: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "Invalid request.";
}
