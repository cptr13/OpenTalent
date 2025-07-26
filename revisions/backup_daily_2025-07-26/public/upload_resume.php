<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$candidate_id = $_POST['candidate_id'] ?? null;

if (!$candidate_id) {
    header("Location: candidates.php?msg=Invalid+candidate+ID");
    exit;
}

if (!isset($_FILES['resume_file']) || $_FILES['resume_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: view_candidate.php?id=$candidate_id&msg=Error+uploading+file");
    exit;
}

$uploadDir = __DIR__ . '/../uploads/resumes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$originalName = $_FILES['resume_file']['name'];
$tempPath = $_FILES['resume_file']['tmp_name'];
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$filename = 'resume_' . $candidate_id . '_' . time() . '.' . $extension;
$destination = $uploadDir . $filename;

if (move_uploaded_file($tempPath, $destination)) {
    // Save to DB
    $stmt = $pdo->prepare("UPDATE candidates SET resume_filename = ? WHERE id = ?");
    $stmt->execute([$filename, $candidate_id]);

    header("Location: view_candidate.php?id=$candidate_id&msg=Resume+uploaded+successfully");
    exit;
} else {
    header("Location: view_candidate.php?id=$candidate_id&msg=Failed+to+save+file");
    exit;
}

