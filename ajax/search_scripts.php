<?php
// ajax/search_scripts.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    /** @var PDO $pdo */
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Database unavailable');
    }

    // ✅ Make PDO throw exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inputs
    $q        = trim($_GET['q'] ?? '');
    $context  = trim($_GET['context'] ?? 'sales');  // default to sales
    $channel  = trim($_GET['channel'] ?? '');       // phone|email|linkedin|... or '' for all
    $stageStr = $_GET['stage'] ?? '';
    $stage    = ($stageStr !== '' ? (int)$stageStr : null);
    $limit    = max(1, min(50, (int)($_GET['limit'] ?? 20)));

    // Base query
    $base  = "SELECT id, title, context, channel, subject, stage, category, type, tags, content, is_active, updated_at
              FROM scripts
              WHERE is_active = 1";
    $where = [];
    $params = [];

    if ($context !== '') {
        $where[] = "context = :context";
        $params[':context'] = $context;
    }
    if ($channel !== '') {
        $where[] = "channel = :channel";
        $params[':channel'] = $channel;
    }

    $search = '';
    if ($q !== '') {
        $search = " AND (title LIKE :like OR tags LIKE :like OR content LIKE :like)";
        $params[':like'] = "%{$q}%";
    }

    // If stage provided, prefer exact stage then fall back to NULL-stage
    if (!is_null($stage)) {
        $sqlExact = $base
                  . (count($where) ? " AND " . implode(' AND ', $where) : '')
                  . " AND stage = :stage" . $search
                  . " ORDER BY updated_at DESC LIMIT " . (int)$limit;

        $sqlNull  = $base
                  . (count($where) ? " AND " . implode(' AND ', $where) : '')
                  . " AND stage IS NULL" . $search
                  . " ORDER BY updated_at DESC LIMIT " . (int)$limit;

        $pExact = $params;
        $pExact[':stage'] = $stage;

        $stmt1 = $pdo->prepare($sqlExact);
        $stmt1->execute($pExact);
        $rows1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $remaining = $limit - count($rows1);
        $rows2 = [];
        if ($remaining > 0) {
            $stmt2 = $pdo->prepare($sqlNull);
            $stmt2->execute($params);
            $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows2) > $remaining) {
                $rows2 = array_slice($rows2, 0, $remaining);
            }
        }

        echo json_encode(['ok' => true, 'items' => array_values(array_merge($rows1, $rows2))], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // No stage preference
    $sql = $base
         . (count($where) ? " AND " . implode(' AND ', $where) : '')
         . $search
         . " ORDER BY updated_at DESC LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // ✅ Return actual DB error message for debugging
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'DB error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
