<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ✅ Dynamically calculate /public/ base path no matter what folder you're in
$basePath = explode('/public/', $_SERVER['SCRIPT_NAME'])[0] . '/public/';

// Admin-only SMTP status banner (uses centralized mailer status)
require_once __DIR__ . '/mailer.php';

// Allow dismissing the banner for this session
if (isset($_GET['hide_smtp_banner'])) {
    $_SESSION['hide_smtp_banner'] = true;
}

$showSmtpBanner = false;
$smtpReason = '';
if (
    isset($_SESSION['user']['role']) &&
    $_SESSION['user']['role'] === 'admin' &&
    empty($_SESSION['hide_smtp_banner'])
) {
    $status = ot_mailer_status();
    if (!$status['ok']) {
        $showSmtpBanner = true;
        $smtpReason = $status['reason'] ?? 'SMTP not configured.';
    }
}
?>
<!-- DEBUG: header.php loaded -->

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OpenTalent ATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container-fluid {
            max-width: 100%;
        }
        .table {
            width: 100%;
            table-layout: auto;
        }
        .nav-user-email {
            color: #bbb;
            font-weight: 500;
            margin-right: 1rem;
            display: flex;
            align-items: center;
        }
        .alert-tight {
            padding-top: .6rem;
            padding-bottom: .6rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $basePath ?>index.php">OpenTalent</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-between" id="mainNavbar">
            <!-- LEFT NAV -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>candidates.php">Candidates</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>jobs.php">Jobs</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>clients.php">Clients</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>contacts.php">Contacts</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>notes.php">Recent Activities</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>change_password.php">Change Password</a></li>
                <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>admin.php">Admin</a></li>
                <?php endif; ?>
            </ul>

            <!-- RIGHT NAV -->
            <form class="d-flex me-2" method="GET" action="<?= $basePath ?>search.php" role="search">
                <input class="form-control me-2" type="search" name="q" placeholder="Search..." aria-label="Search" required>
                <button class="btn btn-outline-light" type="submit">Go</button>
            </form>

            <ul class="navbar-nav align-items-center">
                <?php if (isset($_SESSION['user'])): ?>
                    <li class="nav-item nav-user-email">
                        <?= htmlspecialchars($_SESSION['user']['email']) ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-light" href="<?= $basePath ?>profile.php">My Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="<?= $basePath ?>logout.php">Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php if ($showSmtpBanner): ?>
    <div class="container-fluid px-4">
        <div class="alert alert-warning alert-tight d-flex align-items-center justify-content-between" role="alert">
            <div class="me-3">
                <strong>Outgoing email is not configured.</strong>
                <span class="ms-2"><?= htmlspecialchars($smtpReason) ?></span>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= $basePath ?>installer_smtp.php">Configure SMTP</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'hide_smtp_banner=1') ?>">Dismiss</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="container-fluid px-4">
