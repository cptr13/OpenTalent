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

    // Priority 1: uploaded file from form
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/resumes/';
        $originalName = basename($_FILES['resume']['name']);
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
        $targetPath = $uploadDir . $safeName;

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetPath)) {
            $resumeFilename = $safeName;
        } else {
            echo "<div style='color:red; font-weight:bold;'>Resume upload failed. TMP: " . 
                 htmlspecialchars($_FILES['resume']['tmp_name']) . "<br>Target: " . 
                 htmlspecialchars($targetPath) . "<br>Error code: " . $_FILES['resume']['error'] . "</div>";
            exit;
        }

    // Priority 2: resume from parser (source_file)
    } elseif (!empty($_POST['source_file'])) {
        $parsedFile = basename($_POST['source_file']); // sanitize
        $resumePath = __DIR__ . '/../uploads/resumes/' . $parsedFile;

        if (file_exists($resumePath)) {
            $resumeFilename = $parsedFile;
        } else {
            echo "<div style='color:red;'>Error: Parsed resume file not found on server.</div>";
            exit;
        }
    }

    $data = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $_POST['email'] ?? '',
        'secondary_email' => $_POST['secondary_email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'mobile' => $_POST['mobile'] ?? '',
        'website' => $_POST['website'] ?? '',
        'street' => $_POST['street'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'zip' => $_POST['zip'] ?? '',
        'country' => $_POST['country'] ?? '',
        'experience' => $_POST['experience'] ?? '',
        'current_job' => $_POST['current_job'] ?? '',
        'current_salary' => $_POST['current_salary'] ?? '',
        'expected_salary' => $_POST['expected_salary'] ?? '',
        'skills' => $_POST['skills'] ?? '',
        'linkedin' => $_POST['linkedin'] ?? '',
        'qualification' => $_POST['qualification'] ?? '',
        'employer' => $_POST['employer'] ?? '',
        'additional_info' => $_POST['additional_info'] ?? '',
        'resume_filename' => $resumeFilename,
        'resume_text' => $_POST['resume_text'] ?? '',
        'status' => $_POST['status'] ?? 'New',
        'owner' => $owner,
        'created_at' => date('Y-m-d H:i:s')
    ];

    try {
        $sql = "INSERT INTO candidates (
                    first_name, last_name, email, secondary_email, phone, mobile, website,
                    street, city, state, zip, country,
                    experience, current_job, current_salary, expected_salary, skills,
                    linkedin, qualification, employer, additional_info,
                    resume_filename, resume_text, status, owner, created_at
                ) VALUES (
                    :first_name, :last_name, :email, :secondary_email, :phone, :mobile, :website,
                    :street, :city, :state, :zip, :country,
                    :experience, :current_job, :current_salary, :expected_salary, :skills,
                    :linkedin, :qualification, :employer, :additional_info,
                    :resume_filename, :resume_text, :status, :owner, :created_at
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

