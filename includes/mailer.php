<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Build and return a configured PHPMailer instance.
 *
 * Expects /config/email.php to return:
 * [
 *   'host'       => 'smtp.example.com',
 *   'port'       => 587,
 *   'encryption' => 'tls', // 'tls' | 'ssl' | '' (none)
 *   'username'   => 'user@example.com',
 *   'password'   => 'secret',
 *   'from_email' => 'user@example.com',
 *   'from_name'  => 'Your Name',
 *   'reply_to'   => 'reply@example.com', // optional
 *   'timeout'    => 30
 * ]
 */
function ot_build_mailer(PDO $pdo): PHPMailer {
    $cfg = require __DIR__ . '/../config/email.php';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->Port       = (int)$cfg['port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    if (!empty($cfg['encryption'])) {
        $mail->SMTPSecure = $cfg['encryption']; // 'tls', 'ssl', or '' for none
    }
    $mail->Timeout    = isset($cfg['timeout']) ? (int)$cfg['timeout'] : 30;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    // Default sender
    $fromEmail = $cfg['from_email'] ?? $cfg['username'];
    $fromName  = $cfg['from_name']  ?? $fromEmail;
    $mail->setFrom($fromEmail, $fromName);

    // Default reply-to
    if (!empty($cfg['reply_to'])) {
        $mail->addReplyTo($cfg['reply_to']);
    }

    return $mail;
}

/**
 * Log an email event using canonical columns.
 * Accepts both canonical and legacy keys; stores only canonical fields.
 *
 * Canonical fields stored:
 *   direction, related_module, related_id,
 *   from_name, from_email, to_emails, cc_emails, bcc_emails,
 *   subject, body_html, body_text, attachments, headers_json,
 *   smtp_account, status, error_message, message_id, provider_message_id
 *
 * @return int Inserted row id
 */
function ot_log_email(PDO $pdo, array $data): int {
    // Canonical pulls (fallback to legacy keys if given)
    $direction       = $data['direction']        ?? 'outbound';
    $related_module  = $data['related_module']   ?? ($data['related_type'] ?? null);
    $related_id      = array_key_exists('related_id', $data) ? (int)$data['related_id'] : null;

    $from_name       = $data['from_name']        ?? null;
    $from_email      = $data['from_email']       ?? null;

    // Canonical uses to_emails (comma-separated). Accept single legacy to_email.
    $to_emails       = $data['to_emails']        ?? ($data['to_email'] ?? null);
    $cc_emails       = $data['cc_emails']        ?? null;
    $bcc_emails      = $data['bcc_emails']       ?? null;

    $subject         = $data['subject']          ?? '';
    $body_html       = $data['body_html']        ?? null;
    $body_text       = $data['body_text']        ?? null;
    $attachments     = $data['attachments']      ?? null;

    // headers_json may come in as array or JSON string; store as JSON string
    $headers_json    = $data['headers_json']     ?? null;
    if (is_array($headers_json)) {
        $headers_json = json_encode($headers_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $smtp_account    = $data['smtp_account']     ?? null;
    $status          = $data['status']           ?? 'sent';
    $error_message   = $data['error_message']    ?? ($data['error'] ?? null); // accept legacy 'error'
    $message_id      = $data['message_id']       ?? null;
    $provider_msg_id = $data['provider_message_id'] ?? null;

    $sql = "
        INSERT INTO email_logs
        (direction, related_module, related_id,
         from_name, from_email, to_emails, cc_emails, bcc_emails,
         subject, body_html, body_text, attachments, headers_json,
         smtp_account, status, error_message, message_id, provider_message_id, created_at)
        VALUES
        (:direction, :related_module, :related_id,
         :from_name, :from_email, :to_emails, :cc_emails, :bcc_emails,
         :subject, :body_html, :body_text, :attachments, :headers_json,
         :smtp_account, :status, :error_message, :message_id, :provider_message_id, NOW())
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':direction'           => $direction,
        ':related_module'      => $related_module,
        ':related_id'          => $related_id,
        ':from_name'           => $from_name,
        ':from_email'          => $from_email,
        ':to_emails'           => $to_emails,
        ':cc_emails'           => $cc_emails,
        ':bcc_emails'          => $bcc_emails,
        ':subject'             => $subject,
        ':body_html'           => $body_html,
        ':body_text'           => $body_text,
        ':attachments'         => $attachments,
        ':headers_json'        => $headers_json,
        ':smtp_account'        => $smtp_account,
        ':status'              => $status,
        ':error_message'       => $error_message,
        ':message_id'          => $message_id,
        ':provider_message_id' => $provider_msg_id,
    ]);

    return (int)$pdo->lastInsertId();
}
