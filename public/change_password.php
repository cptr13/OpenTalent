<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === $confirmPassword && !empty($newPassword)) {
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$newPassword, $_SESSION['email']]);
        $message = "Password successfully updated.";
    } else {
        $message = "Passwords do not match or are empty.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password – OpenTalent</title>
</head>
<body>
    <h2>Change Password</h2>
    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>New Password:
            <input type="password" name="new_password" required>
        </label><br><br>
        <label>Confirm Password:
            <input type="password" name="confirm_password" required>
        </label><br><br>
        <button type="submit">Update Password</button>
    </form>
    <p><a href="index.php">Back to Dashboard</a></p>
</body>
</html>
