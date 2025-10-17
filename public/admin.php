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

/** SMTP status (for the card badge) */
$smtpCfgPath = __DIR__ . '/../config/email.php';
$smtpStatus = [
    'present' => file_exists($smtpCfgPath),
    'enabled' => false,
    'from_email' => null,
    'from_name' => null,
    'host' => null,
    'port' => null,
    'encryption' => null,
];

if ($smtpStatus['present']) {
    // email.php should return an array; guard in case of custom structures
    $cfg = include $smtpCfgPath;
    if (is_array($cfg)) {
        $smtpStatus['enabled']    = !empty($cfg['smtp_enabled']);
        $smtpStatus['from_email'] = $cfg['from_email'] ?? null;
        $smtpStatus['from_name']  = $cfg['from_name'] ?? null;
        $smtpStatus['host']       = $cfg['smtp_host'] ?? null;
        $smtpStatus['port']       = $cfg['smtp_port'] ?? null;
        $smtpStatus['encryption'] = $cfg['encryption'] ?? null;
    }
}

/** Company Branding (system_settings) */
$branding = [
    'company_name' => 'OpenTalent',
    'logo_path'    => null,
];
$brandingLoadErr = null;

try {
    // system_settings: id, company_name, logo_path
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    $tableExists = (bool)$stmt->fetchColumn();

    if ($tableExists) {
        $row = $pdo->query("SELECT company_name, logo_path FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $branding['company_name'] = $row['company_name'] ?: $branding['company_name'];
            $branding['logo_path']    = $row['logo_path'] ?: null;
        }
    }
} catch (Throwable $e) {
    // Don't block the page if table doesn't exist yet
    $brandingLoadErr = $e->getMessage();
}

$flash = null;
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $flash = ['type' => 'success', 'msg' => 'Company settings saved.'];
} elseif (isset($_GET['saved']) && $_GET['saved'] === '0') {
    $flash = ['type' => 'danger', 'msg' => 'Unable to save company settings.'];
}
?>
<div class="container-fluid px-4 mt-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">Only visible to admin users.</p>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

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

        <!-- Email (SMTP) Settings Card -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">Email (SMTP) Settings</h5>
                            <?php
                              $badgeClass = $smtpStatus['enabled'] ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';
                              $badgeText  = $smtpStatus['present'] ? ($smtpStatus['enabled'] ? 'Enabled' : 'Disabled') : 'Not Configured';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span>
                        </div>
                        <p class="card-text mt-2">
                            Configure sender details and SMTP server used for outbound emails.
                        </p>
                        <?php if ($smtpStatus['present']): ?>
                            <div class="small text-muted">
                                <div><strong>From:</strong> <?= htmlspecialchars($smtpStatus['from_name'] ?? '—') ?> &lt;<?= htmlspecialchars($smtpStatus['from_email'] ?? '—') ?>&gt;</div>
                                <div><strong>Server:</strong> <?= htmlspecialchars($smtpStatus['host'] ?? '—') ?><?= $smtpStatus['port'] ? ':' . htmlspecialchars((string)$smtpStatus['port']) : '' ?> <?= $smtpStatus['encryption'] ? '(' . htmlspecialchars(strtoupper((string)$smtpStatus['encryption'])) . ')' : '' ?></div>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted">No email.php found in <code>/config</code>. Use the button below to set it up.</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="installer_smtp.php" class="btn btn-primary">Configure SMTP</a>
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

        <!-- Company Branding Card -->
        <div class="col-md-12">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="card-title mb-0">Company Branding</h5>
                        <?php if ($brandingLoadErr): ?>
                            <span class="badge bg-warning text-dark">Table not ready</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted mb-3">
                        Set your company name and upload a logo. The name will replace “OpenTalent” in the header, and the logo will appear in the top bar.
                    </p>

                    <form action="save_company_settings.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row g-3 align-items-start">
                            <div class="col-md-6">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($branding['company_name'] ?? '') ?>" required>
                                <div class="form-text">Example: Oakway Group</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Logo (PNG/JPG/SVG)</label>
                                <input type="file" name="logo" accept="image/*" class="form-control">
                                <div class="form-text">Recommended height ~40px for header display.</div>
                            </div>
                        </div>

                        <?php if (!empty($branding['logo_path'])): ?>
                            <div class="mt-3">
                                <div class="form-label mb-1">Current Logo</div>
                                <img src="<?= htmlspecialchars($branding['logo_path']) ?>" alt="Company Logo" style="max-height:60px;">
                            </div>
                        <?php endif; ?>

                        <?php if ($brandingLoadErr): ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <strong>Note:</strong> Could not load <code>system_settings</code> (<?= htmlspecialchars($brandingLoadErr) ?>).
                                You can still submit — the saver can create the table automatically.
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Company Settings</button>
                            <a href="admin.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /.row -->
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
