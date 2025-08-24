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
$to_email     = trim(isset($_POST['to_email']) ? $_POST['to_email'] : '');
$to_name      = trim(isset($_POST['to_name']) ? $_POST['to_name'] : '');
$subject      = trim(isset($_POST['subject']) ? $_POST['subject'] : '');
$body_html    = (string)(isset($_POST['body_html']) ? $_POST['body_html'] : '');
$related_type = isset($_POST['related_type']) ? $_POST['related_type'] : 'none'; // legacy name; expected 'contact' | 'candidate' | 'none'
$related_id   = (int)(isset($_POST['related_id']) ? $_POST['related_id'] : 0);
$return_to    = isset($_POST['return_to']) ? $_POST['return_to'] : '';
$force_debug  = isset($_POST['__debug']) && $_POST['__debug'] !== '';

// Basic validation
if (!$to_email || !$subject || !$body_html) {
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

$pdo->beginTransaction();
try {
    $mail = ot_build_mailer($pdo);
    $cfg  = require __DIR__ . '/../config/email.php';

    // ---- SMTP DEBUG CAPTURE ----
    $smtp_debug_log = '';
    $smtpDebug = isset($cfg['smtp_debug']) ? (int)$cfg['smtp_debug'] : 0;
    if ($force_debug) { $smtpDebug = max($smtpDebug, 2); }

    if ($smtpDebug > 0) {
        $mail->SMTPDebug  = $smtpDebug;  // 1..4
        // Capture debug output instead of echoing
        $mail->Debugoutput = function($str, $level) use (&$smtp_debug_log) {
            $smtp_debug_log .= "[L" . $level . "] " . $str . "\n";
        };
    }

    // Optional TLS workaround if your host has odd certs (toggle in config/email.php)
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
    $mail->addAddress($to_email, $to_name ? $to_name : $to_email);
    $mail->Subject = $subject;
    $mail->Body    = $body_html;
    $mail->AltBody = strip_tags($body_html);

    // Optional attachment
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
        $mail->addAttachment($_FILES['attachment']['tmp_name'], $_FILES['attachment']['name']);
    }

    $status = 'sent';
    $error  = null;

    try {
        if (!$mail->send()) {
            $status = 'failed';
            $error  = $mail->ErrorInfo ? $mail->ErrorInfo : 'Unknown send error';
        }
    } catch (Exception $e) {
        $status = 'failed';
        $error  = $e->getMessage();
    }

    // If failed and we have SMTP debug details, append them
    if ($status === 'failed' && $smtp_debug_log) {
        $error .= "\n\n--- SMTP Debug ---\n" . $smtp_debug_log;
    }

    // Build log payload
    // Keep legacy keys that your current ot_log_email() likely expects,
    // and ALSO include canonical keys so we can later switch to them cleanly.
    $logPayload = array(
        // Canonical fields we want to end up using everywhere:
        'direction'       => 'outbound',
        'related_module'  => $related_type ?: 'none',   // canonical (mirrors related_type)
        'related_id'      => $related_id ? $related_id : null,
        'from_name'       => isset($cfg['from_name']) ? $cfg['from_name'] : (isset($cfg['from_email']) ? $cfg['from_email'] : null),
        'from_email'      => isset($cfg['from_email']) ? $cfg['from_email'] : null,
        'to_emails'       => $to_email,                 // canonical (even single addr ok)
        'cc_emails'       => null,
        'bcc_emails'      => null,
        'subject'         => $subject,
        'body_html'       => $body_html,
        'body_text'       => strip_tags($body_html),
        'status'          => $status,
        'error_message'   => $error,                    // canonical
        'message_id'      => null,
        'provider_message_id' => null,
        'headers_json'    => array('note' => 'PHPMailer over SMTP'),

        // Legacy/compat fields your current INSERT has used:
        'related_type'    => $related_type ?: 'none',
        'to_name'         => $to_name ? $to_name : null,
        'to_email'        => $to_email,
        'error'           => $error
    );

    // Log it
    ot_log_email($pdo, $logPayload);

    // ---- Auto-create a Note on the target Contact/Candidate (ONLY on successful send) ----
    if ($status === 'sent' && $related_id > 0 && in_array($related_type, ['contact','candidate'], true)) {
        // Plain-text preview of the message body (trim to keep notes readable)
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

        // Map legacy shim columns for compatibility across views
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
    $pdo->rollBack();
    echo "<div class='alert alert-danger'>Unexpected error: " . h($t->getMessage()) . "</div>";
    echo '<p><a href="compose_email.php">Back</a></p>';
}
