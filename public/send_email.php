<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

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

$pdo->beginTransaction();
try {
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
    $mail->Subject = $subject;
    $mail->Body    = $body_html;
    $mail->AltBody = strip_tags($body_html);

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
        'subject'         => $subject,
        'body_html'       => $body_html,
        'body_text'       => strip_tags($body_html),
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
        $plain = trim(strip_tags($body_html));
        if (mb_strlen($plain, 'UTF-8') > $previewMax) {
            $plain = mb_substr($plain, 0, $previewMax, 'UTF-8') . '…';
        }

        $noteLines = [];
        $noteLines[] = "Email sent to " . ($to_name ? "{$to_name} <{$to_email}>" : $to_email);
        $noteLines[] = "Subject: {$subject}";
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
        echo "<pre style='white-space:pre-wrap;max-height:400px;overflow:auto;border:1px solid #ddd;padding:.75rem;'>" . h($error) . "</pre>";
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
