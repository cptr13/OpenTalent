<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Email configuration loader (single source of truth).
 *
 * Expected file: /config/email.php returning an array like:
 * [
 *   'smtp_enabled'   => bool,
 *   'from_email'     => 'sender@example.com',
 *   'from_name'      => 'OpenTalent',
 *   'smtp_host'      => 'smtp.example.com',
 *   'smtp_port'      => 587,
 *   'encryption'     => 'starttls', // 'none' | 'starttls' | 'smtps'
 *   'username'       => 'user@example.com',
 *   'password'       => 'secret',
 *   'reply_to_email' => 'reply@example.com',   // optional
 *   'reply_to_name'  => 'Support',             // optional
 *   'timeout'        => 25                     // seconds (optional)
 * ]
 */
function ot_get_email_config(): array {
    $path = __DIR__ . '/../config/email.php';
    if (!file_exists($path)) {
        return [];
    }
    $cfg = require $path;
    return is_array($cfg) ? $cfg : [];
}

/**
 * Quick health check used by UI/pages to decide whether to attempt sending.
 * Returns ['ok' => bool, 'reason' => string|null]
 */
function ot_mailer_status(): array {
    $cfg = ot_get_email_config();

    if (empty($cfg)) {
        return ['ok' => false, 'reason' => 'SMTP configuration file not found.'];
    }
    if (empty($cfg['smtp_enabled'])) {
        return ['ok' => false, 'reason' => 'SMTP is disabled.'];
    }
    $required = ['from_email','smtp_host','smtp_port','encryption','username','password'];
    foreach ($required as $k) {
        if (!isset($cfg[$k]) || $cfg[$k] === '' || $cfg[$k] === null) {
            return ['ok' => false, 'reason' => "SMTP setting '{$k}' is missing."];
        }
    }
    // Basic sanity checks
    if (!filter_var($cfg['from_email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'reason' => 'From Email is not a valid email address.'];
    }
    if (!in_array($cfg['encryption'], ['none','starttls','smtps'], true)) {
        return ['ok' => false, 'reason' => 'Encryption must be one of: none, starttls, smtps.'];
    }
    return ['ok' => true, 'reason' => null];
}

/**
 * Build and return a configured PHPMailer instance.
 * Throws Exception with a safe message if SMTP is not configured.
 *
 * NOTE: We keep the PDO parameter for backward compatibility even if not used here.
 */
function ot_build_mailer(PDO $pdo = null): PHPMailer {
    $status = ot_mailer_status();
    if (!$status['ok']) {
        // Clear, non-secret reason; callers can catch and decide what to show.
        throw new Exception('SMTP not configured: ' . $status['reason']);
    }

    $cfg  = ot_get_email_config();
    $mail = new PHPMailer(true);

    // Core transport
    $mail->isSMTP();
    $mail->Host       = (string)$cfg['smtp_host'];
    $mail->Port       = (int)$cfg['smtp_port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = (string)$cfg['username'];
    $mail->Password   = (string)$cfg['password'];

    // Encryption mapping
    // PHPMailer accepts constants; keep it explicit to avoid auto-TLS surprises.
    $enc = (string)$cfg['encryption'];
    if ($enc === 'starttls') {
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;  // allow opportunistic TLS upgrade on 587
    } elseif ($enc === 'smtps') {
        $mail->SMTPSecure  = PHPMailer::ENCRYPTION_SMTPS; // implicit TLS on 465
        $mail->SMTPAutoTLS = false;
    } else {
        // 'none'
        $mail->SMTPSecure  = false;
        $mail->SMTPAutoTLS = false; // do not auto-upgrade if user chose none
    }

    // Timeouts & charset
    $mail->Timeout = isset($cfg['timeout']) ? max(5, (int)$cfg['timeout']) : 25;
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    // Default sender
    $fromEmail = (string)($cfg['from_email'] ?? $cfg['username']);
    $fromName  = (string)($cfg['from_name']  ?? $fromEmail);
    $mail->setFrom($fromEmail, $fromName);

    // Default reply-to
    if (!empty($cfg['reply_to_email'])) {
        $mail->addReplyTo((string)$cfg['reply_to_email'], (string)($cfg['reply_to_name'] ?? ''));
    }

    // Sensible SSL options (verify peers by default)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ]
    ];

    return $mail;
}

/**
 * Optional convenience: send and log in one go.
 * Returns ['ok' => bool, 'error' => string|null, 'message_id' => string|null]
 *
 * Usage example:
 *   $res = ot_send_email($pdo, [
 *     'to' => ['alice@example.com' => 'Alice'],
 *     'subject' => 'Hello',
 *     'html' => '<p>Hi</p>',
 *     'text' => 'Hi'
 *   ]);
 */
function ot_send_email(PDO $pdo, array $args): array {
    try {
        $mail = ot_build_mailer($pdo);
    } catch (Exception $e) {
        // Not configured or invalid config
        $logId = ot_log_email($pdo, [
            'status'        => 'failed',
            'error_message' => 'Mailer init failed: ' . $e->getMessage(),
            'subject'       => $args['subject'] ?? '',
            'to_emails'     => isset($args['to']) ? implode(',', array_keys((array)$args['to'])) : null,
        ]);
        return ['ok' => false, 'error' => $e->getMessage(), 'message_id' => null];
    }

    try {
        // Recipients
        $to = $args['to'] ?? [];
        if (is_string($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($to);
        } elseif (is_array($to)) {
            foreach ($to as $email => $name) {
                // allow numeric-indexed arrays of plain emails as well
                if (is_int($email)) {
                    $mail->addAddress($name);
                } else {
                    $mail->addAddress((string)$email, (string)$name);
                }
            }
        }

        // CC/BCC (optional)
        foreach (($args['cc'] ?? []) as $email => $name) {
            if (is_int($email)) { $mail->addCC($name); } else { $mail->addCC((string)$email, (string)$name); }
        }
        foreach (($args['bcc'] ?? []) as $email => $name) {
            if (is_int($email)) { $mail->addBCC($name); } else { $mail->addBCC((string)$email, (string)$name); }
        }

        // Subject & body
        $mail->Subject = (string)($args['subject'] ?? '');
        $html = $args['html'] ?? null;
        $text = $args['text'] ?? null;

        if ($html !== null) {
            $mail->Body    = (string)$html;
            $mail->AltBody = (string)($text ?? strip_tags((string)$html));
        } else {
            $mail->isHTML(false);
            $mail->Body    = (string)($text ?? '');
        }

        // Attachments (optional)
        if (!empty($args['attachments']) && is_array($args['attachments'])) {
            foreach ($args['attachments'] as $path => $name) {
                if (is_int($path)) {
                    $mail->addAttachment($name);
                } else {
                    $mail->addAttachment((string)$path, (string)$name);
                }
            }
        }

        // Send
        $ok = $mail->send();
        $msgId = $mail->getLastMessageID();

        // Log success
        ot_log_email($pdo, [
            'status'        => $ok ? 'sent' : 'failed',
            'error_message' => $ok ? null : 'Unknown error while sending.',
            'from_email'    => $mail->From ?? null,
            'from_name'     => $mail->FromName ?? null,
            'to_emails'     => isset($args['to']) ? (is_array($args['to']) ? implode(',', array_keys($args['to'])) : (string)$args['to']) : null,
            'subject'       => $mail->Subject,
            'body_html'     => $html ?? null,
            'body_text'     => $text ?? null,
            'smtp_account'  => $mail->Host . ':' . $mail->Port,
            'message_id'    => $msgId ?: null,
        ]);

        return ['ok' => $ok, 'error' => $ok ? null : 'Send failed', 'message_id' => $msgId ?: null];

    } catch (Exception $e) {
        // Log failure without secrets
        ot_log_email($pdo, [
            'status'        => 'failed',
            'error_message' => 'PHPMailer exception: ' . $e->getMessage(),
            'subject'       => $args['subject'] ?? '',
            'to_emails'     => isset($args['to']) ? (is_array($args['to']) ? implode(',', array_keys($args['to'])) : (string)$args['to']) : null,
        ]);
        return ['ok' => false, 'error' => 'Send failed: ' . $e->getMessage(), 'message_id' => null];
    }
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
