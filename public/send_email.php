<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/status.php';       // getStatusList('contact')
require_once __DIR__ . '/../includes/kpi_logger.php'; // kpi_log_sales_status_change

use PHPMailer\PHPMailer\Exception;

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: compose_email.php');
    exit;
}

// ---- Inputs ----
$to_email     = trim($_POST['to_email']     ?? '');
$to_name      = trim($_POST['to_name']      ?? '');
$subject      = trim($_POST['subject']      ?? '');
$body_html    = (string)($_POST['body_html'] ?? '');
$related_type = $_POST['related_type']      ?? 'none'; // 'contact' | 'candidate' | 'none'
$related_id   = (int)($_POST['related_id']  ?? 0);
$return_to    = $_POST['return_to']         ?? '';
$force_debug  = !empty($_POST['__debug']);

// Optional: status hint from compose_email.php for contacts
$log_contact_status = trim($_POST['log_contact_status'] ?? '');

// Basic validation
if (!$to_email || !$subject || $body_html === '') {
    echo "<div class='alert alert-danger'>Missing required fields.</div>";
    echo '<p><a href="compose_email.php">Back</a></p>';
    exit;
}
if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
    echo "<div class='alert alert-danger'>Invalid recipient email address.</div>";
    echo '<p><a href="compose_email.php">Back</a></p>';
    exit;
}

// Attachment size guard (10MB)
$max_attach_bytes = 10 * 1024 * 1024;
if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
    if (filesize($_FILES['attachment']['tmp_name']) > $max_attach_bytes) {
        echo "<div class='alert alert-danger'>Attachment exceeds 10MB limit.</div>";
        echo '<p><a href="compose_email.php">Back</a></p>';
        exit;
    }
}

// Load (non-secret) email config values via central loader
$cfg = ot_get_email_config();

if (!$pdo instanceof PDO) {
    echo "<div class='alert alert-danger'>Database connection not available.</div>";
    echo '<p><a href="compose_email.php">Back</a></p>';
    exit;
}

/**
 * Fetch merge variables for placeholders based on related entity.
 */
function ot_build_merge_vars(PDO $pdo, string $related_type, int $related_id, string $to_name): array {
    $vars = [
        // defaults
        'first_name'   => '',
        'last_name'    => '',
        'full_name'    => trim($to_name) ?: '',
        'company'      => '',
        'company_name' => '',
        'job_title'    => '',
        'my_name'      => isset($_SESSION['user']['full_name']) ? (string)$_SESSION['user']['full_name'] : '',
        'my_title'     => isset($_SESSION['user']['role']) ? (string)$_SESSION['user']['role'] : '',
        'my_email'     => isset($_SESSION['user']['email']) ? (string)$_SESSION['user']['email'] : '',
        'region'       => '',
        // seed/back-compat aliases; will get mapped later
        'your_name'    => null,
        'your_agency'  => null,
    ];

    if ($related_type === 'contact' && $related_id > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, cl.name AS client_name
            FROM contacts c
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.id = :id
        ");
        $stmt->execute([':id' => $related_id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $first = trim((string)($row['first_name'] ?? ''));
            $last  = trim((string)($row['last_name'] ?? ''));
            $full  = trim((string)($row['full_name'] ?? ($first . ' ' . $last)));
            $company = trim((string)($row['client_name'] ?? ''));

            $vars['first_name']   = $first;
            $vars['last_name']    = $last;
            $vars['full_name']    = $full ?: $vars['full_name'];
            $vars['job_title']    = (string)($row['title'] ?? '');
            $vars['company']      = $company;
            $vars['company_name'] = $company;
        }
    } elseif ($related_type === 'candidate' && $related_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = :id");
        $stmt->execute([':id' => $related_id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $first = trim((string)($row['first_name'] ?? ''));
            $last  = trim((string)($row['last_name'] ?? ''));
            $full  = trim($first . ' ' . $last);

            $vars['first_name']   = $first;
            $vars['last_name']    = $last;
            $vars['full_name']    = $full ?: $vars['full_name'];
            $vars['job_title']    = (string)($row['current_job'] ?? '');
            $company = trim((string)($row['current_employer'] ?? ''));
            $vars['company']      = $company;
            $vars['company_name'] = $company;
        }
    }

    // Back-compat: map your_* to my_* where sensible
    $vars['your_name']   = $vars['my_name'];
    // If you later store an org/agency name in config, map here.
    $vars['your_agency'] = ''; // intentionally blank until you add org config

    return $vars;
}

/**
 * Render {{placeholders}} using merge vars (case-insensitive keys).
 */
function ot_render_placeholders(string $text, array $vars): string {
    if ($text === '') return $text;

    // Normalize keys to lowercase for simple lookup
    $map = [];
    foreach ($vars as $k => $v) {
        $map[strtolower($k)] = (string)($v ?? '');
    }

    // Replace {{ token }} ignoring surrounding spaces; leave unknown tokens as-is
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u', function($m) use ($map) {
        $key = strtolower($m[1]);
        return array_key_exists($key, $map) ? $map[$key] : $m[0];
    }, $text);
}

$pdo->beginTransaction();
try {
    // Build merge vars from the related record (if any)
    $mergeVars = ot_build_merge_vars($pdo, $related_type, $related_id, $to_name);

    // Render placeholders before sending/logging
    $rendered_subject   = ot_render_placeholders($subject, $mergeVars);
    $rendered_body_html = ot_render_placeholders($body_html, $mergeVars);
    $rendered_alt       = strip_tags($rendered_body_html);

    // Early check: if SMTP not configured, ot_build_mailer will throw with a safe message.
    $mail = ot_build_mailer($pdo);

    // ---- SMTP DEBUG CAPTURE (optional) ----
    $smtp_debug_log = '';
    $smtpDebug = isset($cfg['smtp_debug']) ? (int)$cfg['smtp_debug'] : 0;
    if ($force_debug) { $smtpDebug = max($smtpDebug, 2); }

    if ($smtpDebug > 0) {
        $mail->SMTPDebug  = $smtpDebug;  // 1..4
        $mail->Debugoutput = function($str, $level) use (&$smtp_debug_log) {
            $smtp_debug_log .= "[L{$level}] {$str}\n";
        };
    }

    // Optional TLS relaxation only if explicitly configured (compat)
    if (!empty($cfg['allow_self_signed'])) {
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );
    }

    // Prepare message
    $mail->clearAllRecipients();
    $mail->addAddress($to_email, $to_name ?: $to_email);
    $mail->Subject = $rendered_subject;
    $mail->Body    = $rendered_body_html;
    $mail->AltBody = $rendered_alt;

    // Optional attachment
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
        $mail->addAttachment($_FILES['attachment']['tmp_name'], $_FILES['attachment']['name']);
    }

    $status = 'sent';
    $error  = null;
    $messageId = null;

    try {
        $ok = $mail->send();
        if (!$ok) {
            $status = 'failed';
            $error  = $mail->ErrorInfo ? $mail->ErrorInfo : 'Unknown send error';
        }
        $messageId = $mail->getLastMessageID() ?: null;
    } catch (Exception $e) {
        $status = 'failed';
        $error  = $e->getMessage();
    }

    // If failed and we have SMTP debug details, append them (still no secrets)
    if ($status === 'failed' && $smtp_debug_log) {
        $error .= "\n\n--- SMTP Debug ---\n" . $smtp_debug_log;
    }

    // Build log payload (canonical + legacy shims) — use actual From from PHPMailer
    $logPayload = array(
        // Canonical fields:
        'direction'       => 'outbound',
        'related_module'  => $related_type ?: 'none',
        'related_id'      => $related_id ?: null,
        'from_name'       => $mail->FromName ?? ($cfg['from_name']  ?? null),
        'from_email'      => $mail->From     ?? ($cfg['from_email'] ?? null),
        'to_emails'       => $to_email,
        'cc_emails'       => null,
        'bcc_emails'      => null,
        'subject'         => $rendered_subject,
        'body_html'       => $rendered_body_html,
        'body_text'       => $rendered_alt,
        'status'          => $status,
        'error_message'   => $error,
        'message_id'      => $messageId,
        'provider_message_id' => null,
        'headers_json'    => array('note' => 'PHPMailer over SMTP'),
        'smtp_account'    => ($mail->Host ?? '-') . ':' . (string)($mail->Port ?? ''),

        // Legacy/compat fields:
        'related_type'    => $related_type ?: 'none',
        'to_name'         => $to_name ?: null,
        'to_email'        => $to_email,
        'error'           => $error
    );

    // Log it
    ot_log_email($pdo, $logPayload);

    // ---- Auto-create a Note on the target Contact/Candidate (only on successful send) ----
    if ($status === 'sent' && $related_id > 0 && in_array($related_type, ['contact','candidate'], true)) {
        $previewMax = 1500;
        $plain = trim($rendered_alt);
        if (mb_strlen($plain, 'UTF-8') > $previewMax) {
            $plain = mb_substr($plain, 0, $previewMax, 'UTF-8') . '…';
        }

        $noteLines = [];
        $noteLines[] = "Email sent to " . ($to_name ? "{$to_name} <{$to_email}>" : $to_email);
        $noteLines[] = "Subject: {$rendered_subject}";
        $noteLines[] = "";
        $noteLines[] = "--- Message Preview ---";
        $noteLines[] = $plain;
        $noteContent = implode("\n", $noteLines);

        $candidate_id = ($related_type === 'candidate') ? $related_id : null;
        $contact_id   = ($related_type === 'contact')   ? $related_id : null;

        $stmt = $pdo->prepare("
            INSERT INTO notes
                (module_type, module_id, candidate_id, contact_id, job_id, client_id, content, created_at)
            VALUES
                (:module_type, :module_id, :candidate_id, :contact_id, NULL, NULL, :content, NOW())
        ");
        $stmt->execute([
            ':module_type'  => $related_type,
            ':module_id'    => $related_id,
            ':candidate_id' => $candidate_id,
            ':contact_id'   => $contact_id,
            ':content'      => $noteContent
        ]);
    }

    // ---- NEW: If this was a contact email + we have a log_contact_status, update contact status + KPI ----
    if ($status === 'sent'
        && $related_type === 'contact'
        && $related_id > 0
        && $log_contact_status !== '') {

        // Validate the requested status against the contact status list
        $isValidStatus = false;
        try {
            $contactStatusList = getStatusList('contact'); // ['Category' => ['Sub', ...]]
            foreach ($contactStatusList as $cat => $subs) {
                if (in_array($log_contact_status, $subs, true)) {
                    $isValidStatus = true;
                    break;
                }
            }
        } catch (Throwable $e) {
            $isValidStatus = false;
        }

        if ($isValidStatus) {
            // Lock + fetch current status
            $cur = $pdo->prepare("SELECT contact_status FROM contacts WHERE id = :id FOR UPDATE");
            $cur->execute([':id' => $related_id]);
            $row = $cur->fetch(PDO::FETCH_ASSOC);
            $old_status = $row ? (string)($row['contact_status'] ?? '') : '';

            // Update status to the new value (even if it's the same, to keep consistent)
            $upd = $pdo->prepare("UPDATE contacts SET contact_status = :status WHERE id = :id");
            $upd->execute([
                ':status' => $log_contact_status,
                ':id'     => $related_id,
            ]);

            // Auto note on the contact to record this touch
            $autoNote = $pdo->prepare("
                INSERT INTO notes (module_type, module_id, content, created_at)
                VALUES ('contact', :module_id, :content, NOW())
            ");
            $autoNote->execute([
                ':module_id' => $related_id,
                ':content'   => "Status logged: {$log_contact_status} (via outbound email send)",
            ]);

            // KPI: treat this exactly like a contact status change/log
            $user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
            $user_id = (is_numeric($user_id) && (int)$user_id > 0) ? (int)$user_id : 1;

            try {
                kpi_log_sales_status_change($pdo, $related_id, $log_contact_status, $old_status, $user_id);
            } catch (Throwable $e) {
                // Optional: error_log('KPI sales log (email) failed: ' . $e->getMessage());
            }
        }
    }

    $pdo->commit();

    // Redirect back to the record page when provided
    if ($return_to) {
        $qs = http_build_query(array('msg' => $status === 'sent' ? 'email_sent' : 'email_failed'));
        header('Location: ' . $return_to . (strpos($return_to, '?') !== false ? '&' : '?') . $qs);
        exit;
    }

    if ($status === 'sent') {
        echo "<div class='alert alert-success'>Email sent.</div>";
    } else {
        echo "<div class='alert alert-danger'><strong>Send failed.</strong></div>";
        echo "<pre style='white-space:pre-wrap;max-height:400px;overflow:auto;border:1px solid #ddd;padding=.75rem;'>" . h($error) . "</pre>";
    }
    echo '<p><a href="compose_email.php">Send another</a></p>';

} catch (Throwable $t) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }

    // Log the unexpected error (without secrets)
    ot_log_email($pdo, [
        'direction'       => 'outbound',
        'related_module'  => $related_type ?: 'none',
        'related_id'      => $related_id ?: null,
        'to_emails'       => $to_email ?: null,
        'subject'         => $subject ?: '',
        'status'          => 'failed',
        'error_message'   => 'Unexpected error: ' . $t->getMessage(),
        // legacy shims
        'related_type'    => $related_type ?: 'none',
        'to_email'        => $to_email ?: null,
        'error'           => 'Unexpected error: ' . $t->getMessage(),
    ]);

    echo "<div class='alert alert-danger'>Unexpected error: " . h($t->getMessage()) . "</div>";
    echo '<p><a href="compose_email.php">Back</a></p>';
}
