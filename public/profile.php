<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$email = $_SESSION['email'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile – OpenTalent</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-5">
    <h2 class="mb-4">My Profile</h2>

    <div class="card mb-3">
        <div class="card-body d-flex align-items-center">
            <img src="<?php echo $user['profile_picture'] ?: 'https://via.placeholder.com/80'; ?>"
                 alt="Profile Picture" class="rounded-circle me-4" width="80" height="80">

            <div>
                <h5 class="card-title mb-1"><?php echo htmlspecialchars($user['full_name'] ?? 'Admin User'); ?></h5>
                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p class="mb-1"><strong>Job Title:</strong> <?php echo htmlspecialchars($user['job_title'] ?? '—'); ?></p>
                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? '—'); ?></p>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="change_password.php" class="btn btn-outline-primary">Change Password</a>
        <a href="edit_profile.php" class="btn btn-outline-secondary">Edit Profile</a>
    </div>

    <div class="mt-4">
        <a href="index.php">← Back to Dashboard</a>
    </div>
</body>
</html>
