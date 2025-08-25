<?php
// public/installer_finish.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lockFile         = __DIR__ . '/../INSTALL_LOCKED';
$dbConfigPath     = __DIR__ . '/../config/database.php';
$emailConfigPath  = __DIR__ . '/../config/email.php';

$errors   = [];
$notices  = [];
$warnings = [];

// If user clicked "Skip" on SMTP step, remember it
if (isset($_GET['skip_smtp'])) {
    $_SESSION['installer_smtp_skipped'] = true;
}

// Lock gate: if already finalized, go straight to login
if (file_exists($lockFile)) {
    header('Location: login.php');
    exit;
}

// --- Prerequisites: DB config must exist ---
if (!file_exists($dbConfigPath)) {
    header('Location: installer_db.php');
    exit;
}

// Helpers
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mask_fixed() { return '••••••••'; } // fixed dots (don’t leak pw length)

// --- Load DB config (for summary) ---
$dbConfig = require $dbConfigPath;

// --- Verify schema + admin prerequisites ---
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Must have 'users' table
    $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users' LIMIT 1");
    $hasUsersTable = (bool)$stmt->fetchColumn();
    if (!$hasUsersTable) {
        header('Location: installer_schema.php');
        exit;
    }

    // Must have at least one admin
    $stmt = $pdo->query("SELECT id, full_name, email FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1");
    $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$adminRow) {
        header('Location: installer_admin.php');
        exit;
    }
} catch (Throwable $t) {
    // If DB fails now, send back to DB step to correct
    header('Location: installer_db.php');
    exit;
}

// --- Discover Admin (email & display name) ---
$adminEmail = $_SESSION['installer_admin_email'] ?? ($adminRow['email'] ?? '');
$adminName  = $_SESSION['installer_admin_name']  ?? ($adminRow['full_name'] ?? 'Administrator');
if (!$adminName)  $adminName  = 'Administrator';
if (!$adminEmail) $adminEmail = '';

// --- Load SMTP config (for summary only; optional) ---
$emailCfg = null;
if (file_exists($emailConfigPath)) {
    $loaded = @require $emailConfigPath;
    if (is_array($loaded)) {
        $emailCfg = $loaded;
    } else {
        $warnings[] = "Email configuration file exists but could not be parsed.";
    }
}

// Determine SMTP status (summary)
$smtpEnabled   = (bool)($emailCfg['smtp_enabled'] ?? false);
$smtpHost      = $emailCfg['smtp_host']      ?? '';
$smtpPort      = $emailCfg['smtp_port']      ?? '';
$smtpEncRaw    = $emailCfg['encryption']     ?? '';
$smtpEnc       = strtoupper((string)$smtpEncRaw);
$smtpUser      = $emailCfg['username']       ?? '';
$smtpPass      = $emailCfg['password']       ?? '';
$smtpFromEmail = $emailCfg['from_email']     ?? '';
$smtpFromName  = $emailCfg['from_name']      ?? '';

$skippedSmtp = !empty($_SESSION['installer_smtp_skipped']);

// Non-blocking warnings
if (!$emailCfg || !$smtpEnabled) {
    $warnings[] = "Email sending is currently disabled. You can configure SMTP later in Settings → Email.";
}
if ($skippedSmtp) {
    $warnings[] = "You skipped SMTP testing. Consider configuring and testing SMTP before going live.";
}

// Attempt to tighten permissions on email.php when finalizing
$permTightened = null;

// Handle finalize: write lock and redirect to login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize'])) {
    // Best-effort permission tightening on config/email.php
    if (file_exists($emailConfigPath)) {
        // Try 0440 then 0400; ignore failures (we warn below)
        $try440 = @chmod($emailConfigPath, 0440);
        if ($try440) {
            $permTightened = '440';
        } else {
            $try400 = @chmod($emailConfigPath, 0400);
            if ($try400) $permTightened = '400';
        }
        if (!$permTightened) {
            $warnings[] = "Could not tighten permissions on config/email.php. You may want to run: chmod 440 config/email.php";
        }
    }

    // Write the lock file (only here)
    $lockMsg = "Install completed at " . date('Y-m-d H:i:s') . " by {$adminEmail}\n";
    $written = @file_put_contents($lockFile, $lockMsg);
    if ($written === false) {
        $errors[] = "Failed to write INSTALL_LOCKED in the project root. Check file permissions.";
    } else {
        // Success → go to login
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OpenTalent Installer — Finish</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container-narrow { max-width: 960px; }
        .step-chip { font-size: .875rem; padding: .25rem .5rem; background: #eef2f6; border-radius: .5rem; }
        .kv { display:flex; justify-content:space-between; gap: 1rem; padding: .375rem 0; align-items: flex-start; }
        .kv + .kv { border-top: 1px solid #f1f3f5; }
        .kv .k { color: #6c757d; min-width: 120px; }
        .kv .v { flex: 1 1 auto; text-align: right; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .wrap { white-space: normal; word-break: break-word; }
        .card-eq .card { height: 100%; }
        .card-header .header-actions { float: right; }
        .subtle { color:#6c757d; }
        .helper { font-size: .875rem; color:#6c757d; }
    </style>
</head>
<body>
<div class="container container-narrow py-5">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="mb-0">OpenTalent Installer — Finish</h2>
        <span class="step-chip">Step 6 of 6</span>
    </div>
    <p class="subtle mb-4">Review your settings, then finalize the installation.</p>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($warnings): ?>
        <div class="alert alert-warning">
            <ul class="mb-0">
                <?php foreach ($warnings as $w): ?>
                    <li><?= h($w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($notices): ?>
        <div class="alert alert-success">
            <ul class="mb-0">
                <?php foreach ($notices as $n): ?>
                    <li><?= h($n) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 g-4 card-eq mb-4">
        <!-- Database summary -->
        <div class="col">
            <div class="card">
                <div class="card-header fw-bold">Database</div>
                <div class="card-body">
                    <div class="kv">
                        <span class="k">Host</span>
                        <span class="v mono wrap"><?= h($dbConfig['host'] ?? '-') ?></span>
                    </div>
                    <div class="kv">
                        <span class="k">Database</span>
                        <span class="v mono wrap"><?= h($dbConfig['dbname'] ?? '-') ?></span>
                    </div>
                    <div class="kv">
                        <span class="k">Username</span>
                        <span class="v mono wrap"><?= h($dbConfig['user'] ?? '-') ?></span>
                    </div>
                    <div class="kv">
                        <span class="k">Password</span>
                        <span class="v mono wrap"><?= h($dbConfig['pass'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin summary -->
        <div class="col">
            <div class="card">
                <div class="card-header fw-bold">Admin</div>
                <div class="card-body">
                    <div class="kv">
                        <span class="k">Email</span>
                        <span class="v mono wrap"><?= h($adminEmail ?: '-') ?></span>
                    </div>
                    <div class="kv">
                        <span class="k">Display Name</span>
                        <span class="v wrap"><?= h($adminName ?: '-') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SMTP summary (stacked vertically like Database) -->
    <div class="card mb-4">
        <div class="card-header fw-bold">
            Email Delivery
            <div class="header-actions">
                <a href="installer_smtp.php" class="btn btn-sm btn-outline-primary">Edit SMTP Settings</a>
            </div>
        </div>
        <div class="card-body">
            <div class="kv">
                <span class="k">Configured</span>
                <span class="v"><?= $emailCfg ? 'Yes' : 'No' ?></span>
            </div>
            <div class="kv">
                <span class="k">Enabled</span>
                <span class="v"><?= $smtpEnabled ? 'Yes' : 'No' ?></span>
            </div>
            <div class="kv">
                <span class="k">From</span>
                <span class="v mono wrap"><?= h(($smtpFromName ? $smtpFromName.' ' : '') . "<$smtpFromEmail>") ?></span>
            </div>
            <div class="kv">
                <span class="k">Host</span>
                <span class="v mono wrap"><?= h($smtpHost ?: '-') ?></span>
            </div>
            <div class="kv">
                <span class="k">Port</span>
                <span class="v mono"><?= h((string)$smtpPort ?: '-') ?></span>
            </div>
            <div class="kv">
                <span class="k">Encryption</span>
                <span class="v mono"><?= h($smtpEnc ?: '-') ?></span>
            </div>
            <div class="kv">
                <span class="k">Username</span>
                <span class="v mono wrap"><?= h($smtpUser ?: '-') ?></span>
            </div>
            <div class="kv">
                <span class="k">Password</span>
                <span class="v mono"><?= mask_fixed() ?></span>
            </div>
        </div>
    </div>

    <form method="POST" class="mt-3">
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
            <a href="installer_smtp.php" class="btn btn-secondary">← Back</a>
            <div class="text-sm-end">
                <button type="submit" name="finalize" value="1" class="btn btn-success px-4">Write Lock &amp; Continue to Login</button>
                <div class="helper mt-1">Creates the lock file and redirects to the login page.</div>
            </div>
        </div>
    </form>
</div>
</body>
</html>
