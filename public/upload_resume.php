<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$upload_success = null;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $resume_filename = '';

    if (!empty($_FILES['resume']['name'])) {
        $target_dir = __DIR__ . "/uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $filename = basename($_FILES['resume']['name']);
        $resume_filename = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $target_file = $target_dir . $resume_filename;

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_file)) {
            // uploaded successfully
        } else {
            $error_message = 'Resume upload failed.';
        }
    }

    if (!$error_message) {
        $stmt = $pdo->prepare("INSERT INTO candidates (name, email, phone, resume_filename, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $resume_filename, $notes]);
        $upload_success = true;
    }
}
?>

<h2 class="mb-4">Upload Resume</h2>

<?php if ($upload_success): ?>
    <div class="alert alert-success">Resume uploaded successfully and candidate saved!</div>
<?php elseif ($error_message): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="mb-4">
    <div class="form-group">
        <label>Candidate Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Resume File (PDF or DOCX)</label>
        <input type="file" name="resume" class="form-control-file">
    </div>
    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
