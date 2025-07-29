<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$email = $_SESSION['user']['email'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Role badge color
$role_class = match ($user['role']) {
    'admin' => 'danger',
    'recruiter' => 'primary',
    'viewer' => 'secondary',
    default => 'secondary'
};
?>

<div class="container-fluid px-4 mt-4">
    <h2 class="mb-4">My Profile</h2>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">Profile updated successfully.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-auto text-center mb-3 mb-md-0">
                    <img src="<?= htmlspecialchars($user['profile_picture'] ?: 'https://via.placeholder.com/80') ?>"
                         alt="Profile Picture"
                         class="rounded-circle"
                         style="width: 100px; height: 100px; object-fit: cover; border: 2px solid #dee2e6; box-shadow: 0 0 5px rgba(0,0,0,0.1);">
                </div>
                <div class="col">
                    <h5 class="card-title mb-2"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></h5>
                    <ul class="list-unstyled mb-0">
                        <li><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></li>
                        <li><strong>Job Title:</strong> <?= htmlspecialchars($user['job_title'] ?? '—') ?></li>
                        <li><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? '—') ?></li>
                        <li><strong>Role:</strong> <span class="badge bg-<?= $role_class ?> text-uppercase"><?= htmlspecialchars($user['role']) ?></span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <a href="change_password.php" class="btn btn-outline-primary">Change Password</a>
        <a href="edit_profile.php" class="btn btn-outline-secondary">Edit Profile</a>
    </div>

    <a href="index.php">← Back to Dashboard</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
