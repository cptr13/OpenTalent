<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Dynamically calculate /public/ base path no matter what folder you're in
$basePath = explode('/public/', $_SERVER['SCRIPT_NAME'])[0] . '/public/';
// NEW: Project-root URL (parallel to /public), e.g. "/OpenTalent-main/"
$projectBase = rtrim(dirname($basePath), '/') . '/';

// Admin-only SMTP status banner (uses centralized mailer status)
require_once __DIR__ . '/mailer.php';

// --- Company Branding (company name + logo) ---
$companyName  = 'OpenTalent';
$logoRelPath  = null; // stored path from DB (e.g., 'uploads/logo/file.png')
$logoUrl      = null; // final URL to render
$brandingErr  = null;

try {
    // Attempt to include DB (safe even if already loaded elsewhere)
    $dbPath = __DIR__ . '/../config/database.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
    }

    if (isset($pdo) && $pdo instanceof PDO) {
        // Ensure table exists before selecting
        $checkStmt   = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        $tableExists = (bool)$checkStmt->fetchColumn();

        if ($tableExists) {
            $row = $pdo->query("SELECT company_name, logo_path FROM system_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!empty($row['company_name'])) {
                    $companyName = $row['company_name'];
                }
                if (!empty($row['logo_path'])) {
                    $logoRelPath = (string)$row['logo_path'];
                }
            }
        }
    }
} catch (Throwable $e) {
    // Non-fatal; keep defaults
    $brandingErr = $e->getMessage();
}

/**
 * Normalize logo path into a usable URL.
 * Accepts:
 *  - 'uploads/logo/foo.png' (preferred)
 *  - '/uploads/logo/foo.png'
 *  - 'public/uploads/logo/foo.png'  (we'll strip 'public/')
 *  - '/public/uploads/logo/foo.png' (we'll strip 'public/')
 *  - full URL 'https://...'
 * Result should point to project root (parallel to /public), e.g.:
 *  /OpenTalent-main/uploads/logo/foo.png
 */
if (!empty($logoRelPath)) {
    $raw = trim($logoRelPath);

    // Full URL? Use as-is.
    if (preg_match('~^https?://~i', $raw)) {
        $logoUrl = $raw;
    } else {
        // Strip leading slash
        $raw = ltrim($raw, '/');

        // Remove accidental "public/" prefix if present
        if (strpos($raw, 'public/') === 0) {
            $raw = substr($raw, strlen('public/'));
        }

        // Build from project root (NOT from /public)
        // Example: $projectBase . 'uploads/logo/file.png'
        $logoUrl = $projectBase . $raw;
    }
}

// Allow dismissing the SMTP banner for this session
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
    <title><?= htmlspecialchars($companyName) ?> ATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container-fluid { max-width: 100%; }
        .table { width: 100%; table-layout: auto; }
        .nav-user-email {
            color: #bbb;
            font-weight: 500;
            margin-right: 1rem;
            display: flex;
            align-items: center;
        }
        .alert-tight { padding-top: .6rem; padding-bottom: .6rem; }
        .brand-logo {
            height: 36px; /* header height target */
            width: auto;
            margin-right: .5rem;
            object-fit: contain;
            vertical-align: middle;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $basePath ?>index.php" title="<?= htmlspecialchars($companyName) ?>">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="brand-logo">
            <?php endif; ?>
            <span><?= htmlspecialchars($companyName) ?></span>
        </a>
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
                <!-- NEW: Scripts tab (v1 open to all users) -->
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>scripts.php">Scripts</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= $basePath ?>notes.php">Recent Activities</a></li>
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
