<?php
// public/users.php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Restrict access to admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied. Admins only.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch all users with full columns
$stmt = $pdo->query("SELECT id, full_name, email, job_title, phone, role, created_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>

<div class="container-fluid px-4 mt-4">
    <h2 class="mb-3">All Users</h2>

    <a href="admin.php" class="btn btn-link mb-3">&larr; Back to Admin Dashboard</a>

    <table class="table table-bordered table-hover align-middle shadow-sm">
        <thead class="table-light">
            <tr>
                <th style="width: 50px;">ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Job Title</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Created</th>
                <th style="width: 220px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users): ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['job_title'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? '') ?></td>
                        <td>
                            <span class="badge bg-<?= 
                                $user['role'] === 'admin' ? 'danger' : 
                                ($user['role'] === 'recruiter' ? 'primary' : 'secondary') ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($user['created_at'] ?? ''))) ?></td>
                        <td>
                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-secondary me-1">
                                Edit
                            </a>
                            <a href="reset_user_password.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">
                                Reset Password
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
