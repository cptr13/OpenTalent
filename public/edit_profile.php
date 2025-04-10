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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $job_title = $_POST['job_title'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $profile_picture = $_POST['profile_picture'] ?? '';

    $update = $pdo->prepare("UPDATE users SET full_name = ?, job_title = ?, phone = ?, profile_picture = ? WHERE email = ?");
    $update->execute([$full_name, $job_title, $phone, $profile_picture, $email]);

    header("Location: profile.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile – OpenTalent</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
        <a class="navbar-brand" href="index.php">OpenTalent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="candidates.php">Candidates</a></li>
                <li class="nav-item"><a class="nav-link" href="jobs.php">Jobs</a></li>
                <li class="nav-item"><a class="nav-link" href="applications.php">Applications</a></li>
                <li class="nav-item"><a class="nav-link" href="notes.php">Logs</a></li>
                <li class="nav-item"><a class="nav-link" href="clients.php">Clients</a></li>
                <li class="nav-item"><a class="nav-link" href="contacts.php">Contacts</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">My Profile</a></li>
            </ul>
            <span class="navbar-text text-light me-3">
                Logged in as: <?php echo htmlspecialchars($_SESSION['email']); ?>
            </span>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <h2 class="mb-4">Edit Profile</h2>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Job Title</label>
                <input type="text" name="job_title" class="form-control" value="<?php echo htmlspecialchars($user['job_title'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Profile Picture URL</label>
                <input type="text" name="profile_picture" class="form-control" value="<?php echo htmlspecialchars($user['profile_picture'] ?? '') ?>">
                <div class="form-text">Upload support coming soon</div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="profile.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
