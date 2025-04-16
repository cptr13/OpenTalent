<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Restrict access to admin only
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied. Admins only.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Get counts
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_candidates = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$total_jobs = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_applications = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
?>

<div class="container-fluid px-4 mt-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Only visible to admin users.</p>

    <!-- System Stats -->
    <div class="row mb-4 g-3 justify-content-start">
        <div class="col-6 col-sm-4 col-md-2">
            <div class="card text-bg-light text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title mb-1"><?= $total_users ?></h5>
                    <p class="card-text small text-nowrap">Users</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <div class="card text-bg-light text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title mb-1"><?= $total_candidates ?></h5>
                    <p class="card-text small text-nowrap">Candidates</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <div class="card text-bg-light text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title mb-1"><?= $total_jobs ?></h5>
                    <p class="card-text small text-nowrap">Jobs</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <div class="card text-bg-light text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title mb-1"><?= $total_clients ?></h5>
                    <p class="card-text small text-nowrap">Clients</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-4 col-md-2">
            <div class="card text-bg-light text-center shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h5 class="card-title mb-1"><?= $total_applications ?></h5>
                    <p class="card-text small text-nowrap">Applications</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Tools -->
    <div class="row g-3">
        <!-- User Management Card -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">User Management</h5>
                        <p class="card-text">Add, edit, or reset user accounts and assign roles.</p>
                    </div>
                    <div>
                        <a href="users.php" class="btn btn-primary me-2">Manage Users</a>
                        <a href="add_user.php" class="btn btn-outline-success">Add User</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Settings Card -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">System Settings (Coming Soon)</h5>
                        <p class="card-text">Backup, SMTP config, logs, and more.</p>
                    </div>
                    <div>
                        <button class="btn btn-secondary" disabled>Coming Soon</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
