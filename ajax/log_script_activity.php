<?php
// ajax/log_script_activity.php
// Background analytics logging for Scripts panel.

ini_set('display_errors', 0); // never pollute JSON with HTML errors
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Auth: use session directly (avoid require_login to keep output JSON-only)
session_start();
if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $userId      = (int)$_SESSION['user']['id'];

    // Sanitize inputs
    $action      = isset($_POST['action']) ? strtolower(trim((string)$_POST['action'])) : '';
    $script      = isset($_POST['script_type']) ? trim((string)$_POST['script_type']) : '';
    $tone        = isset($_POST['tone_used']) ? trim((string)$_POST['tone_used']) : '';

    $contactId   = (isset($_POST['contact_id'])   && $_POST['contact_id']   !== '') ? (int)$_POST['contact_id']   : null;
    $candidateId = (isset($_POST['candidate_id']) && $_POST['candidate_id'] !== '') ? (int)$_POST['candidate_id'] : null;
    $clientId    = (isset($_POST['client_id'])    && $_POST['client_id']    !== '') ? (int)$_POST['client_id']    : null;
    $jobId       = (isset($_POST['job_id'])       && $_POST['job_id']       !== '') ? (int)$_POST['job_id']       : null;

    $flagsJson   = isset($_POST['flags_json']) ? (string)$_POST['flags_json'] : null;

    if (!in_array($action, ['copy','print','render'], true)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid action']);
        exit;
    }

    if ($script === '') {
        echo json_encode(['ok' => false, 'message' => 'Missing script_type']);
        exit;
    }

    // Debounce: avoid duplicates within 60 seconds for same user/entity/script/action
    $stmt = $pdo->prepare("
        SELECT id FROM script_activity_log
        WHERE user_id = :uid
          AND COALESCE(contact_id,0)   = COALESCE(:cid,0)
          AND COALESCE(candidate_id,0) = COALESCE(:cand,0)
          AND COALESCE(client_id,0)    = COALESCE(:clid,0)
          AND COALESCE(job_id,0)       = COALESCE(:jid,0)
          AND script_type_slug = :script
          AND action = :action
          AND created_at >= (NOW() - INTERVAL 60 SECOND)
        LIMIT 1
    ");
    $stmt->execute([
        ':uid'    => $userId,
        ':cid'    => $contactId ?: 0,
        ':cand'   => $candidateId ?: 0,
        ':clid'   => $clientId ?: 0,
        ':jid'    => $jobId ?: 0,
        ':script' => $script,
        ':action' => $action,
    ]);
    $dupe = $stmt->fetchColumn();

    if ($dupe) {
        echo json_encode(['ok' => true, 'deduped' => true]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO script_activity_log
            (user_id, contact_id, candidate_id, client_id, job_id, script_type_slug, tone_used_slug, action, flags_json, created_at)
        VALUES
            (:uid, :cid, :cand, :clid, :jid, :script, :tone, :action, :flags, NOW())
    ");
    $stmt->execute([
        ':uid'    => $userId,
        ':cid'    => $contactId,
        ':cand'   => $candidateId,
        ':clid'   => $clientId,
        ':jid'    => $jobId,
        ':script' => $script,
        ':tone'   => $tone ?: null,
        ':action' => $action,
        ':flags'  => $flagsJson,
    ]);

    echo json_encode(['ok' => true]);

} catch (Throwable $e) {
    // Never break UX for logging; return a JSON failure (no HTML)
    echo json_encode(['ok' => false, 'message' => 'log failed']);
}
