<?php
// TEMP: show errors while we’re wiring this up
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$email = $_SESSION['user']['email'] ?? null;

if (!$email) {
    header('Location: login.php');
    exit;
}

// Get current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    require_once __DIR__ . '/../includes/header.php';
    echo "<div class='container py-5'><div class='alert alert-danger'>User not found.</div></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$uploadError = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    // Start with whatever is already in the DB
    $profile_picture_url = $user['profile_picture'] ?? null;

    // Handle profile picture upload (optional)
    if (isset($_FILES['profile_picture_file']) && $_FILES['profile_picture_file']['error'] !== UPLOAD_ERR_NO_FILE) {

        $fileError = $_FILES['profile_picture_file']['error'];

        if ($fileError !== UPLOAD_ERR_OK) {
            // Something went wrong with the upload itself
            $uploadError = "Upload error (code {$fileError}). "
                         . "Check PHP limits like upload_max_filesize (" . ini_get('upload_max_filesize') . ") "
                         . "and post_max_size (" . ini_get('post_max_size') . ").";
        } else {
            // Physical upload directory: project-root/uploads/
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Derive extension from original filename (fallback to jpg)
            $originalName = $_FILES['profile_picture_file']['name'] ?? '';
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $ext = 'jpg';
            }
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }

            $filename    = uniqid('profile_') . '.' . $ext;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['profile_picture_file']['tmp_name'], $target_file)) {
                // Store a simple relative path in the DB: "uploads/filename"
                $profile_picture_url = 'uploads/' . $filename;
            } else {
                $uploadError = "Failed to move uploaded file into /uploads/. Check folder permissions.";
            }
        }
    }

    // Update DB (including profile_picture column)
    $update = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, job_title = ?, phone = ?, profile_picture = ?
        WHERE email = ?
        LIMIT 1
    ");
    $update->execute([$full_name, $job_title, $phone, $profile_picture_url, $email]);

    // Update session
    $_SESSION['user']['full_name']        = $full_name;
    $_SESSION['user']['job_title']        = $job_title;
    $_SESSION['user']['phone']            = $phone;
    $_SESSION['user']['profile_picture']  = $profile_picture_url;

    // Only redirect if there was NO upload error
    if ($uploadError === null) {
        header("Location: profile.php?updated=1");
        exit;
    }

    // If there was an upload error, fall through to re-render the form
    // with $uploadError shown below.
}

// Only render HTML after POST handling
require_once __DIR__ . '/../includes/header.php';

// Compute preview path for current picture (if any)
$rawPic = $user['profile_picture'] ?? '';
if (!empty($rawPic)) {
    // From /public/edit_profile.php, "../uploads/..." hits /OpenTalent-main/uploads/...
    if ($rawPic[0] === '/') {
        $editPicSrc = $rawPic;
    } else {
        $editPicSrc = '../' . ltrim($rawPic, '/');
    }
} else {
    $editPicSrc = 'https://via.placeholder.com/80';
}
?>

<div class="container py-5">
    <h2 class="mb-4">Edit Profile</h2>

    <?php if ($uploadError): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($uploadError) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control"
                   value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Job Title</label>
            <input type="text" name="job_title" class="form-control"
                   value="<?= htmlspecialchars($user['job_title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control"
                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Current Profile Picture</label><br>
            <img src="<?= htmlspecialchars($editPicSrc) ?>" alt="Current Picture"
                 class="rounded-circle" width="80" height="80"
                 style="object-fit: cover; border: 2px solid #ccc;">
        </div>

        <div class="mb-3">
            <label class="form-label">Upload New Profile Picture</label>
            <input type="file" name="profile_picture_file" class="form-control" accept="image/*">
            <div class="form-text">JPEG or PNG only. Max size is limited by PHP's upload_max_filesize/post_max_size.</div>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="profile.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
