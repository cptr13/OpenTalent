<?php
// public/edit_user.php

// --- Turn on error reporting early ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Ensure PDO throws exceptions so DB errors aren't swallowed
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Throwable $e) {
        // If this somehow fails, continue; we'll still have display_errors on
    }
}

// Helpers
function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Admins only
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied. Admins only.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Validate user_id
$user_id = $_GET['id'] ?? null;
if (!$user_id || !is_numeric($user_id)) {
    echo "<div class='alert alert-danger'>Invalid user ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
$user_id = (int)$user_id;

$success = '';
$error   = '';

// Fetch user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo "<div class='alert alert-warning'>User not found.</div>";
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>DB error (loading user): " . h($e->getMessage()) . "</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $role      = $_POST['role'] ?? 'recruiter';

    if ($full_name === '') {
        $error = "Full name is required.";
    } else {
        try {
            $update = $pdo->prepare(
                "UPDATE users
                 SET full_name = ?, job_title = ?, phone = ?, role = ?
                 WHERE id = ?"
            );
            $update->execute([$full_name, $job_title, $phone, $role, $user_id]);

            $success = "User updated successfully.";

            // Refresh user info
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // Surface DB errors (e.g., unknown column 'job_title')
            $error = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid px-4 mt-4">
    <h2 class="mb-3">Edit User</h2>

    <a href="users.php" class="btn btn-link mb-3">&larr; Back to User List</a>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card p-4 shadow-sm">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Job Title</label>
            <input type="text" name="job_title" class="form-control" value="<?= h($user['job_title'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= h($user['phone'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="admin"     <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="recruiter" <?= ($user['role'] ?? '') === 'recruiter' ? 'selected' : '' ?>>Recruiter</option>
                <option value="viewer"    <?= ($user['role'] ?? '') === 'viewer' ? 'selected' : '' ?>>Viewer</option>
            </select>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="users.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
