<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Restrict access to admin only
if (!isset($_SESSION['user'])) {
    echo "<div class='alert alert-danger'>Access denied. No user session found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access denied. Admins only.</div>";
    echo "<div class='text-muted'>Logged in as role: " . htmlspecialchars($_SESSION['user']['role'] ?? 'undefined') . "</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Get counts
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_candidates = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$total_jobs = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_associations = $pdo->query("SELECT COUNT(*) FROM associations")->fetchColumn();
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
                    <h5 class="card-title mb-1"><?= $total_associations ?></h5>
                    <p class="card-text small text-nowrap">Associations</p>
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
<!-- KPI Goals Card -->
<div class="col-md-6">
  <div class="card shadow-sm h-100">
    <div class="card-body d-flex flex-column justify-content-between">
      <div>
        <h5 class="card-title">KPI Goals (Sales)</h5>
        <p class="card-text">Set agency defaults and user overrides. Bulk push and copy. View audit history.</p>
      </div>
      <div>
        <a href="admin_kpi_goals.php" class="btn btn-primary">Open KPI Goals</a>
      </div>
    </div>
  </div>
</div>

        <!-- System Settings Card -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title">System Settings</h5>
                        <p class="card-text">Perform backup, restore, or factory reset operations.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="../public/tools/backup.php" class="btn btn-outline-primary">Create Backup</a>
                        <a href="../public/tools/restore.php" class="btn btn-outline-success">Restore Backup</a>
                        <a href="../public/tools/factory_reset.php" class="btn btn-outline-danger">Factory Reset</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
