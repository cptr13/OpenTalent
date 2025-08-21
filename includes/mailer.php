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
 * Insert an email log row into email_logs.
 * Only writes to the standalone email_logs table (no FKs).
 *
 * @param PDO   $pdo
 * @param array $log
 *   Keys (all optional except to_email/subject/body if you care about content):
 *     direction, related_type, related_id, from_name, from_email,
 *     to_name, to_email, subject, body_html, body_text,
 *     status, error, provider_message_id, headers_json (array|string)
 *
 * @return int Inserted log ID
 */
function ot_log_email(PDO $pdo, array $log): int {
    $sql = "INSERT INTO email_logs
        (direction, related_type, related_id, from_name, from_email, to_name, to_email, subject, body_html, body_text, status, error, provider_message_id, headers_json)
        VALUES (:direction, :related_type, :related_id, :from_name, :from_email, :to_name, :to_email, :subject, :body_html, :body_text, :status, :error, :provider_message_id, :headers_json)";

    $headers = $log['headers_json'] ?? null;
    if (is_array($headers)) {
        $headers = json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':direction'           => $log['direction']           ?? 'outbound',
        ':related_type'        => $log['related_type']        ?? 'none',
        ':related_id'          => $log['related_id']          ?? null,
        ':from_name'           => $log['from_name']           ?? null,
        ':from_email'          => $log['from_email']          ?? null,
        ':to_name'             => $log['to_name']             ?? null,
        ':to_email'            => $log['to_email']            ?? null,
        ':subject'             => $log['subject']             ?? null,
        ':body_html'           => $log['body_html']           ?? null,
        ':body_text'           => $log['body_text']           ?? null,
        ':status'              => $log['status']              ?? 'sent',
        ':error'               => $log['error']               ?? null,
        ':provider_message_id' => $log['provider_message_id'] ?? null,
        ':headers_json'        => $headers,
    ]);

    return (int)$pdo->lastInsertId();
}
