<?php
ini_set('display_errors',1); error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

/* Admin-only gate */
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'admin') {
    http_response_code(403); echo json_encode(['error'=>'Admins only']); exit;
}
if (!isset($pdo) || !$pdo) { http_response_code(500); echo json_encode(['error'=>'DB']); exit; }

/* Input */
$payload   = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$metric    = $payload['metric'] ?? '';
$period    = $payload['period'] ?? '';
$mode      = $payload['mode']   ?? 'full';            // 'full' | 'even' | 'manual'
$user_ids  = $payload['user_ids'] ?? [];
$manual    = $payload['manual']   ?? [];
$module    = 'sales';                                   // sales-only UI

$validMetrics = ['leads_added','contact_attempts','conversations','agreements_signed','job_orders_received'];
$validPeriods = ['daily','weekly','monthly','quarterly','half_year','yearly'];
if (!in_array($metric,$validMetrics,true) || !in_array($period,$validPeriods,true) || !in_array($mode,['full','even','manual'],true)) {
    http_response_code(400); echo json_encode(['error'=>'Invalid input']); exit;
}
if (!is_array($user_ids) || count($user_ids)===0) {
    http_response_code(400); echo json_encode(['error'=>'Select at least one user']); exit;
}

/* Get agency default to push */
$stmt = $pdo->prepare("SELECT id, goal FROM kpi_goals WHERE module=? AND user_id IS NULL AND metric=? AND period=?");
$stmt->execute([$module,$metric,$period]);
$agency = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$agency) { http_response_code(400); echo json_encode(['error'=>'No agency default goal to push']); exit; }
$agency_goal = (int)$agency['goal'];

$actor_id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);

/* Compute per-user goal values */
$valuesByUser = [];
if ($mode === 'full') {
    foreach ($user_ids as $uid) $valuesByUser[(int)$uid] = $agency_goal;
} elseif ($mode === 'even') {
    $n = count($user_ids);
    $base = intdiv($agency_goal, $n);
    $rem  = $agency_goal % $n;
    $i=0;
    foreach ($user_ids as $uid) {
        $valuesByUser[(int)$uid] = $base + ($i < $rem ? 1 : 0);
        $i++;
    }
} else { // manual
    foreach ($user_ids as $uid) {
        $uid = (int)$uid;
        $v = (int)($manual[$uid] ?? 0);
        if ($v < 0) $v = 0;
        $valuesByUser[$uid] = $v;
    }
}

/* Upsert + audit */
$upsert = $pdo->prepare("
    INSERT INTO kpi_goals (user_id, module, metric, period, goal)
    VALUES (?,?,?,?,?)
    ON DUPLICATE KEY UPDATE goal = VALUES(goal)
");
$get    = $pdo->prepare("SELECT id, goal FROM kpi_goals WHERE user_id=? AND module=? AND metric=? AND period=?");
$audit  = $pdo->prepare("
    INSERT INTO kpi_goal_audit
        (goal_id, target_user_id, metric, period, old_goal, new_goal, changed_by, action, note)
    VALUES (?,?,?,?,?,?,?,?,?)
");

$did = 0;
foreach ($valuesByUser as $uid=>$val) {
    $get->execute([$uid,$module,$metric,$period]); $prev = $get->fetch(PDO::FETCH_ASSOC);

    $upsert->execute([$uid,$module,$metric,$period,$val]);

    $get->execute([$uid,$module,$metric,$period]); $cur = $get->fetch(PDO::FETCH_ASSOC);
    $goalId = $cur ? (int)$cur['id'] : null;

    if ($pdo->query("SHOW TABLES LIKE 'kpi_goal_audit'")->fetchColumn()) {
        $audit->execute([$goalId, $uid, $metric, $period, $prev ? (int)$prev['goal'] : null, $val, $actor_id, $prev?'update':'insert', 'push: '.$mode]);
    }
    $did++;
}

echo json_encode(['ok'=>true, 'pushed'=>$did, 'mode'=>$mode]);
