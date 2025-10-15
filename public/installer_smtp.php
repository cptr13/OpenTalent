<?php
// public/installer_smtp.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Guards & prerequisites ---
$lockFile          = __DIR__ . '/../INSTALL_LOCKED';
$dbConfigPath      = __DIR__ . '/../config/database.php';
$emailConfigPath   = __DIR__ . '/../config/email.php';
$emailExamplePath  = __DIR__ . '/../config/email.example.php';

$installLocked = file_exists($lockFile);

// If installer is finalized:
//   - Allow access ONLY to logged-in admins (so it can be used as an admin SMTP editor)
//   - If not logged in, bounce to login
// If not finalized (installation flow), allow anonymous access exactly as before.
if ($installLocked) {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
    if (empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        echo "<div style='max-width:760px;margin:2rem auto;font-family:sans-serif'>";
        echo "<h3>Access denied</h3>";
        echo "<p>Only administrators can edit SMTP settings after installation is locked.</p>";
        echo "<p class='small' style='opacity:.8'>Logged in role: " . htmlspecialchars($_SESSION['user']['role'] ?? 'undefined') . "</p>";
        echo "</div>";
        exit;
    }
}

// Ensure DB config exists before this step.
// - During install flow (unlocked): redirect to DB step if missing
// - After install (locked): show blocking error instead of redirecting
if (!file_exists($dbConfigPath)) {
    if ($installLocked) {
        $blockingError = "Database configuration not found at <code>config/database.php</code>. Please restore it or re-run setup.";
    } else {
        header('Location: installer_db.php');
        exit;
    }
} else {
    $blockingError = '';
}

// Invalidate opcache in case database.php was just rewritten
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($dbConfigPath, true);
}

// --- Helpers ---
/**
 * Safely load DB config even if the file doesn't return an array
 */
function load_db_config_safely(string $path): array {
    ob_start();
    $ret = @include $path;
    ob_end_clean();
    if (is_array($ret)) {
        return $ret;
    }
    $cfg = (static function ($p) {
        $config = null;
        ob_start();
        @include $p;
        ob_end_clean();
        return is_array($config) ? $config : [];
    })($path);
    return $cfg;
}

/**
 * If $target is missing and $example exists, copy example → target with restrictive perms.
 * Returns true if the target exists at the end (created or already present).
 */
function ensure_config_from_example(string $target, string $example, int $mode = 0600): bool {
    if (is_file($target)) return true;
    if (!is_file($example)) return false;
    if (!@copy($example, $target)) return false;
    @chmod($target, $mode);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
    }
    return true;
}

$configDb = file_exists($dbConfigPath) ? load_db_config_safely($dbConfigPath) : [];

$errors = [];
$notice = '';
$saved  = false;
$tested = false;
$test_result = null;
$test_detail = '';

$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
$pdo = null;
$currentDb = '(unknown)';

// --- DB connection & admin prerequisite (no blind redirects; show blocking errors) ---
try {
    if (!$blockingError) {
        if (empty($configDb) || !isset($configDb['host'],$configDb['dbname'],$configDb['user'])) {
            throw new RuntimeException('Could not load DB credentials from config/database.php');
        }

        // Use 127.0.0.1 for local to avoid socket/auth oddities
        $host = ($configDb['host'] === 'localhost') ? '127.0.0.1' : $configDb['host'];
        $dsn  = "mysql:host={$host};dbname={$configDb['dbname']};charset=utf8mb4";

        $pdo = new PDO($dsn, $configDb['user'], $configDb['pass'] ?? '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $currentDb = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

        // Check for users table without relying on information_schema permissions
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $hasUsersTable = (bool)$stmt->fetchColumn();
        if (!$hasUsersTable) {
            $blockingError = "Required table 'users' not found in database '{$currentDb}'. Run Step 3 (Load Schema) on this database, then return here.";
        }
    }
} catch (Throwable $e) {
    $blockingError = "Database connection/check failed: " . $e->getMessage();
}

// Prefill admin email/name from session or DB (only if no blocking error)
$adminEmail = $_SESSION['installer_admin_email'] ?? '';
$adminName  = $_SESSION['installer_admin_name']  ?? '';

if (!$blockingError) {
    if ($adminEmail === '' || $adminName === '') {
        try {
            $stmt = $pdo->query("SELECT full_name, email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $adminEmail = $adminEmail ?: ($row['email'] ?? '');
                $adminName  = $adminName  ?: ($row['full_name'] ?? 'Administrator');
            } else {
                $blockingError = "No admin user found. Please complete Step 4 (Admin Setup) first.";
            }
        } catch (Throwable $e) {
            $blockingError = "Failed to read admin user: " . $e->getMessage();
        }
    }
}

// --- Auto-seed config/email.php from example on fresh installs ---
if (!$blockingError && !file_exists($emailConfigPath) && file_exists($emailExamplePath)) {
    if (ensure_config_from_example($emailConfigPath, $emailExamplePath)) {
        $notice = "Created <code>config/email.php</code> from <code>config/email.example.php</code>. Please review and save.";
    }
}

// Load existing email config if present
$existing = [
    'smtp_enabled'   => false,
    'from_email'     => $adminEmail,
    'from_name'      => ($adminName !== '' ? $adminName : 'OpenTalent'),
    'smtp_host'      => '',
    'smtp_port'      => 587,
    'encryption'     => 'starttls', // none|starttls|smtps
    'username'       => '',
    'password'       => '',
    'reply_to_email' => '',
    'reply_to_name'  => '',
    'timeout'        => 25,
];
if (file_exists($emailConfigPath)) {
    ob_start();
    $loaded = @include $emailConfigPath;
    ob_end_clean();
    if (is_array($loaded)) {
        $existing = array_merge($existing, $loaded);
    }
}

// Form values (prefill from POST or existing)
$postedPassword = $_POST['password'] ?? '';
$val = [
    'smtp_enabled'   => isset($_POST['smtp_enabled'])
        ? (bool)($_POST['smtp_enabled'])
        : (bool)$existing['smtp_enabled'],
    'from_email'     => $_POST['from_email']     ?? $existing['from_email'],
    'from_name'      => $_POST['from_name']      ?? $existing['from_name'],
    'smtp_host'      => $_POST['smtp_host']      ?? $existing['smtp_host'],
    'smtp_port'      => (int)($_POST['smtp_port'] ?? $existing['smtp_port']),
    'encryption'     => $_POST['encryption']     ?? $existing['encryption'],
    'username'       => $_POST['username']       ?? $existing['username'],
    // IMPORTANT: never echo secrets; use an effective password internally, keep field blank in UI
    'password'       => $postedPassword, // only for round-trip; we won't echo it
    'reply_to_email' => $_POST['reply_to_email'] ?? $existing['reply_to_email'],
    'reply_to_name'  => $_POST['reply_to_name']  ?? $existing['reply_to_name'],
    'timeout'        => (int)($_POST['timeout']  ?? $existing['timeout']),
];

// Compute effective password (posted or existing)
$effectivePassword = ($postedPassword !== '') ? $postedPassword : ($existing['password'] ?? '');

// Helpers
function valid_email($e) { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }

// Validation if saving or testing
$action = $_POST['__action'] ?? null;
if (!$blockingError && ($action === 'save' || $action === 'test')) {
    if (!valid_email($val['from_email'])) {
        $errors[] = "From Email must be a valid email address.";
    }
    if ($val['reply_to_email'] !== '' && !valid_email($val['reply_to_email'])) {
        $errors[] = "Reply-To Email must be a valid email address.";
    }
    $allowedEnc = ['none','starttls','smtps'];
    if (!in_array($val['encryption'], $allowedEnc, true)) {
        $errors[] = "Encryption must be one of: none, starttls, smtps.";
    }
    if ($val['timeout'] < 5 || $val['timeout'] > 120) {
        $errors[] = "Timeout should be between 5 and 120 seconds.";
    }
    if ($val['smtp_enabled']) {
        if ($val['smtp_host'] === '') $errors[] = "SMTP Host is required when SMTP is enabled.";
        if ($val['smtp_port'] < 1 || $val['smtp_port'] > 65535) $errors[] = "SMTP Port must be 1–65535.";
        if ($val['username'] === '') $errors[] = "Username is required when SMTP is enabled.";
        if ($effectivePassword === '') $errors[] = "Password is required when SMTP is enabled.";
    }
}

// Save config
if (!$blockingError && $action === 'save' && !$errors) {
    $configDir = dirname($emailConfigPath);
    if (!is_writable($configDir)) {
        $errors[] = "Cannot write email.php. Make sure the /config directory is writable.";
    } else {
        $cfg = [
            'smtp_enabled'   => (bool)$val['smtp_enabled'],
            'from_email'     => $val['from_email'],
            'from_name'      => $val['from_name'],
            'smtp_host'      => $val['smtp_host'],
            'smtp_port'      => (int)$val['smtp_port'],
            'encryption'     => $val['encryption'],
            'username'       => $val['username'],
            'password'       => $effectivePassword, // write posted or keep existing
            'reply_to_email' => $val['reply_to_email'],
            'reply_to_name'  => $val['reply_to_name'],
            'timeout'        => (int)$val['timeout'],
        ];
        $php = "<?php\nreturn " . var_export($cfg, true) . ";\n";
        $written = @file_put_contents($emailConfigPath, $php);
        if ($written === false) {
            $errors[] = "Failed to write config/email.php. Check permissions.";
        } else {
            @chmod($emailConfigPath, 0600);
            $saved = true;
            $notice = $notice ? $notice . ' ' : '';
            $notice .= "SMTP settings saved.";
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($emailConfigPath, true);
            }
        }
    }
}

// Test email (basic SMTP client against current form values)
if (!$blockingError && $action === 'test' && !$errors) {
    $tested = true;

    if (!$val['smtp_enabled']) {
        $test_result = false;
        $test_detail = "SMTP is disabled. Enable it and save settings before testing.";
    } else {
        $recipient = $adminEmail ?: $val['from_email'];
        $test = smtp_send_test(
            $val['smtp_host'],
            (int)$val['smtp_port'],
            $val['encryption'],    // none|starttls|smtps
            $val['timeout'],
            $val['username'],
            $effectivePassword,    // do not echo this anywhere
            $val['from_email'],
            $recipient,
            $val['from_name']
        );
        $test_result = $test['ok'];
        $test_detail = $test['msg'];
    }
}

/**
 * Minimal SMTP test: connect, (optionally STARTTLS/SMTPS), AUTH LOGIN, MAIL FROM, RCPT TO, DATA, QUIT.
 * Returns ['ok' => bool, 'msg' => string]
 */
function smtp_send_test($host, $port, $enc, $timeout, $username, $password, $from, $to, $fromName = 'OpenTalent') {
    $contextOptions = [];
    $transport = '';
    if ($enc === 'smtps') {
        $transport = 'ssl://';
        $contextOptions['ssl'] = [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ];
    }
    $ctx = stream_context_create($contextOptions);
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    if (!$fp) {
        return ['ok' => false, 'msg' => "Connection failed: $errstr (code $errno). Check host/port or outbound firewall."];
    }
    stream_set_timeout($fp, $timeout);

    $r = smtp_expect($fp, [220]);
    if (!$r['ok']) { fclose($fp); return $r; }

    $hostname = gethostname() ?: 'localhost';
    if (!smtp_send($fp, "EHLO $hostname", [250])) { fclose($fp); return ['ok'=>false,'msg'=>'EHLO failed.']; }

    // STARTTLS upgrade if requested
    if ($enc === 'starttls') {
        if (!smtp_send($fp, "STARTTLS", [220])) { fclose($fp); return ['ok'=>false,'msg'=>'STARTTLS not accepted.']; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); return ['ok'=>false,'msg'=>'TLS negotiation failed. Check encryption/port.'];
        }
        // Re-EHLO after STARTTLS
        if (!smtp_send($fp, "EHLO $hostname", [250])) { fclose($fp); return ['ok'=>false,'msg'=>'EHLO after STARTTLS failed.']; }
    }

    // AUTH LOGIN
    if (!smtp_send($fp, "AUTH LOGIN", [334])) { fclose($fp); return ['ok'=>false,'msg'=>'AUTH LOGIN not accepted. Check server supports SMTP AUTH.']; }
    if (!smtp_send($fp, base64_encode($username), [334])) { fclose($fp); return ['ok'=>false,'msg'=>'Username not accepted. Check credentials.']; }
    if (!smtp_send($fp, base64_encode($password), [235])) { fclose($fp); return ['ok'=>false,'msg'=>'Authentication failed. Check username/password and security settings.']; }

    // Envelope
    if (!smtp_send($fp, "MAIL FROM:<$from>", [250])) { fclose($fp); return ['ok'=>false,'msg'=>'MAIL FROM rejected (sender not allowed).']; }
    if (!smtp_send($fp, "RCPT TO:<$to>", [250,251])) { fclose($fp); return ['ok'=>false,'msg'=>'RCPT TO rejected (recipient not accepted).']; }

    // DATA
    if (!smtp_send($fp, "DATA", [354])) { fclose($fp); return ['ok'=>false,'msg'=>'DATA not accepted.']; }

    $date = date('r');
    $msgId = bin2hex(random_bytes(8)) . '@' . ($hostname ?: 'local');
    $fromHeader = $fromName !== '' ? "{$fromName} <{$from}>" : $from;
    $headers = [
        "Date: $date",
        "From: $fromHeader",
        "To: <$to>",
        "Subject: OpenTalent SMTP Test",
        "Message-ID: <$msgId>",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
    ];
    $body = "This is a test email from the OpenTalent installer.\r\nIf you received this, SMTP is configured correctly.\r\n";

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    fwrite($fp, $data . "\r\n");
    $r = smtp_expect($fp, [250]);
    if (!$r['ok']) { fclose($fp); return $r; }

    smtp_send($fp, "QUIT", [221]);
    fclose($fp);
    return ['ok' => true, 'msg' => "Test email accepted by server. Check the inbox for <$to>."];
}

function smtp_send($fp, $cmd, array $expect) {
    fwrite($fp, $cmd . "\r\n");
    $resp = smtp_read($fp);
    if ($resp === false) return false;
    $code = (int)substr($resp['line'], 0, 3);
    return in_array($code, $expect, true);
}

function smtp_expect($fp, array $expect) {
    $resp = smtp_read($fp);
    if ($resp === false) return ['ok'=>false,'msg'=>'No response from server.'];
    $code = (int)substr($resp['line'], 0, 3);
    if (!in_array($code, $expect, true)) {
        return ['ok'=>false,'msg'=>"Unexpected response: {$resp['line']}"];
    }
    return ['ok'=>true,'msg'=>$resp['line']];
}

function smtp_read($fp) {
    $lines = '';
    while (($line = fgets($fp, 512)) !== false) {
        $lines .= $line; // string concatenation
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    if ($lines === '') return false;
    return ['line' => rtrim($lines)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OpenTalent Installer - SMTP Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 760px; }
        .muted { opacity: .8; font-size: .9rem; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace; }
        .small { font-size: .875rem; }
    </style>
</head>
<body>
<div class="container py-5">
    <h2 class="mb-3">
        <?= $installLocked ? 'SMTP Settings' : 'OpenTalent Installation - Step 5: SMTP Settings' ?>
    </h2>
    <p class="text-muted">
        Configure the sender and SMTP server used for all emails. You can test delivery before finishing<?= $installLocked ? '' : ' the install' ?>.
    </p>

    <?php if ($debug): ?>
        <div class="alert alert-info small">
            <div><strong>DEBUG</strong> DB config: host=<span class="mono"><?= htmlspecialchars($configDb['host'] ?? '(missing)') ?></span>,
                db=<span class="mono"><?= htmlspecialchars($configDb['dbname'] ?? '(missing)') ?></span>,
                user=<span class="mono"><?= htmlspecialchars($configDb['user'] ?? '(missing)') ?></span></div>
            <div>Connected DB: <span class="mono"><?= htmlspecialchars($currentDb) ?></span></div>
            <div>email.php path: <span class="mono"><?= htmlspecialchars($emailConfigPath) ?></span> (<?= file_exists($emailConfigPath) ? 'exists' : 'missing' ?>)</div>
            <div>INSTALL_LOCKED: <span class="mono"><?= $installLocked ? 'yes' : 'no' ?></span></div>
        </div>
    <?php endif; ?>

    <?php if ($blockingError): ?>
        <div class="alert alert-warning"><?= $blockingError ?></div>
        <div class="d-flex justify-content-between mt-3">
            <?php if (!$installLocked): ?>
                <a href="installer_schema.php" class="btn btn-primary">Go to Step 3 (Load Schema) →</a>
                <a href="installer_admin.php" class="btn btn-outline-secondary">Step 4 (Admin)</a>
                <a href="installer_db.php" class="btn btn-outline-secondary">DB Settings</a>
            <?php else: ?>
                <a href="installer_db.php" class="btn btn-outline-secondary">Open DB Settings</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($notice): ?>
            <div class="alert alert-success"><?= $notice ?></div>
        <?php endif; ?>

        <?php if ($tested): ?>
            <?php if ($test_result): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($test_detail) ?></div>
            <?php else: ?>
                <div class="alert alert-warning">⚠️ <?= htmlspecialchars($test_detail) ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="smtp_enabled" name="smtp_enabled" <?= $val['smtp_enabled'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="smtp_enabled">Enable SMTP</label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="from_email" class="form-label">From Email</label>
                            <input type="email" class="form-control" id="from_email" name="from_email" value="<?= htmlspecialchars($val['from_email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="from_name" class="form-label">From Name</label>
                            <input type="text" class="form-control" id="from_name" name="from_name" value="<?= htmlspecialchars($val['from_name']) ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="smtp_host" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($val['smtp_host']) ?>" placeholder="smtp.example.com">
                        </div>
                        <div class="col-md-3">
                            <label for="smtp_port" class="form-label">Port</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars((string)$val['smtp_port']) ?>" min="1" max="65535">
                        </div>
                        <div class="col-md-3">
                            <label for="encryption" class="form-label">Encryption</label>
                            <select class="form-select" id="encryption" name="encryption">
                                <option value="none"     <?= $val['encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                                <option value="starttls" <?= $val['encryption'] === 'starttls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                                <option value="smtps"    <?= $val['encryption'] === 'smtps' ? 'selected' : '' ?>>SMTPS (465)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="username" class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($val['username']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">SMTP Password</label>
                            <div class="input-group">
                                <!-- Never echo the actual password -->
                                <input type="password" class="form-control" id="password" name="password" value="">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw()">Show</button>
                            </div>
                            <?php if (!empty($existing['password']) && empty($postedPassword)): ?>
                                <div class="form-text">A saved password exists. Leave blank to keep it.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="reply_to_email" class="form-label">Reply-To Email (optional)</label>
                            <input type="email" class="form-control" id="reply_to_email" name="reply_to_email" value="<?= htmlspecialchars($val['reply_to_email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="reply_to_name" class="form-label">Reply-To Name (optional)</label>
                            <input type="text" class="form-control" id="reply_to_name" name="reply_to_name" value="<?= htmlspecialchars($val['reply_to_name']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <label for="timeout" class="form-label">Timeout (sec)</label>
                            <input type="number" class="form-control" id="timeout" name="timeout" min="5" max="120" value="<?= htmlspecialchars((string)$val['timeout']) ?>">
                        </div>
                        <div class="col-md-9">
                            <div class="muted mt-4">
                                <strong>Provider hints:</strong>
                                Gmail/Google Workspace: STARTTLS 587, app password if 2FA. Microsoft 365: STARTTLS 587 with SMTP AUTH enabled.
                                Port 25 without encryption is discouraged on shared hosting.
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex justify-content-between">
                <?php if ($installLocked): ?>
                    <a href="admin.php" class="btn btn-secondary">← Back to Admin</a>
                <?php else: ?>
                    <a href="installer_admin.php" class="btn btn-secondary">← Back</a>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" name="__action" value="save" class="btn btn-primary">Save Settings</button>
                    <button type="submit" name="__action" value="test" class="btn btn-outline-primary">Send Test Email</button>
                    <?php if (!$installLocked): ?>
                        <a href="installer_finish.php" class="btn btn-success">Next: Finish →</a>
                        <a href="installer_finish.php?skip_smtp=1" class="btn btn-outline-secondary">Skip for now →</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function togglePw() {
    const input = document.getElementById('password');
    const btn   = event.target;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        btn.textContent = 'Show';
    }
}
</script>
</body>
</html>
