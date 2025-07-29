<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$email = $_SESSION['user']['email'];

// Get current user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $profile_picture_url = $user['profile_picture']; // default to existing

    // Handle profile picture upload
    if (!empty($_FILES['profile_picture_file']['tmp_name'])) {
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = uniqid('profile_') . '.jpg';
        $target_file = $upload_dir . $filename;

        $image_type = mime_content_type($_FILES['profile_picture_file']['tmp_name']);
        if (in_array($image_type, ['image/jpeg', 'image/png'])) {
            move_uploaded_file($_FILES['profile_picture_file']['tmp_name'], $target_file);
            $profile_picture_url = 'uploads/' . $filename;
        }
    }

    // Update DB
    $update = $pdo->prepare("UPDATE users SET full_name = ?, job_title = ?, phone = ?, profile_picture = ? WHERE email = ?");
    $update->execute([$full_name, $job_title, $phone, $profile_picture_url, $email]);

    // Update session to reflect changes
    $_SESSION['user']['full_name'] = $full_name;
    $_SESSION['user']['job_title'] = $job_title;
    $_SESSION['user']['phone'] = $phone;
    $_SESSION['user']['profile_picture'] = $profile_picture_url;

    header("Location: profile.php?updated=1");
    exit;
}
?>

<div class="container py-5">
    <h2 class="mb-4">Edit Profile</h2>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Job Title</label>
            <input type="text" name="job_title" class="form-control" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>

        <?php if (!empty($user['profile_picture'])): ?>
            <div class="mb-3">
                <label class="form-label">Current Profile Picture</label><br>
                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Current Picture"
                     class="rounded-circle" width="80" height="80" style="object-fit: cover; border: 2px solid #ccc;">
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label">Upload New Profile Picture</label>
            <input type="file" name="profile_picture_file" class="form-control" accept="image/*">
            <div class="form-text">JPEG or PNG only. Max size: 2MB.</div>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="profile.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

