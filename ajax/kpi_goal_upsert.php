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
$data = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];

$module  = strtolower(trim($data['module'] ?? 'sales'));
if (!in_array($module, ['sales','recruiting'], true)) $module = 'sales';

$id      = isset($data['id']) ? (int)$data['id'] : null;
$user_id = array_key_exists('user_id',$data)
            ? ($data['user_id'] === null ? null : (int)$data['user_id'])
            : null;
$metric  = trim($data['metric'] ?? '');
$period  = trim($data['period'] ?? '');
$goal    = (int)($data['goal'] ?? 0);

$salesMetrics = ['leads_added','contact_attempts','conversations','agreements_signed','job_orders_received'];
$recruitingMetrics = ['contact_attempts','conversations','submittals','interviews','offers_made','hires'];
$validMetrics = $module === 'recruiting' ? $recruitingMetrics : $salesMetrics;

$validPeriods = ['daily','weekly','monthly','quarterly','half_year','yearly'];
if (!in_array($metric,$validMetrics,true) || !in_array($period,$validPeriods,true) || $goal < 0) {
    http_response_code(400); echo json_encode(['error'=>'Invalid input']); exit;
}

$actor_id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);

/* UPDATE by id */
if ($id) {
    $old = $pdo->prepare("SELECT * FROM kpi_goals WHERE id=?");
    $old->execute([$id]);
    $prev = $old->fetch(PDO::FETCH_ASSOC);
    if (!$prev) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }

    // Prevent unique-key collision on update
    $dup = $pdo->prepare("
        SELECT id FROM kpi_goals
         WHERE module = ? AND metric = ? AND period = ? AND (user_id <=> ?)
           AND id <> ?
         LIMIT 1
    ");
    $dup->execute([$module, $metric, $period, $user_id, $id]);
    if ($dup->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode(['error'=>'A goal for this module/user/metric/period already exists.']); exit;
    }

    $stmt = $pdo->prepare("
        UPDATE kpi_goals
           SET user_id = :uid,
               module  = :mod,
               metric  = :m,
               period  = :p,
               goal    = :g
         WHERE id = :id
    ");
    try {
        $stmt->execute([':uid'=>$user_id, ':mod'=>$module, ':m'=>$metric, ':p'=>$period, ':g'=>$goal, ':id'=>$id]);
    } catch (PDOException $e) {
        if ($e->getCode()==='23000') { // unique constraint
            http_response_code(409);
            echo json_encode(['error'=>'Duplicate goal exists for this module/user/metric/period.']); exit;
        }
        throw $e;
    }

    // Audit (if table exists)
    if ($pdo->query("SHOW TABLES LIKE 'kpi_goal_audit'")->fetchColumn()) {
        $a = $pdo->prepare("
            INSERT INTO kpi_goal_audit
                (goal_id, target_user_id, metric, period, old_goal, new_goal, changed_by, action, note)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $a->execute([$id, $user_id, $metric, $period, (int)$prev['goal'], $goal, $actor_id, 'update', null]);
    }

    echo json_encode(['ok'=>true, 'id'=>$id]); exit;
}

/* UPSERT by (module, metric, period, user_id) */
$chk = $pdo->prepare("
    SELECT id, goal
      FROM kpi_goals
     WHERE module = ?
       AND metric = ?
       AND period = ?
       AND (user_id <=> ?)
     LIMIT 1
");
$chk->execute([$module, $metric, $period, $user_id]);
$row = $chk->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $stmt = $pdo->prepare("UPDATE kpi_goals SET goal = ? WHERE id = ?");
    $stmt->execute([$goal, (int)$row['id']]);

    if ($pdo->query("SHOW TABLES LIKE 'kpi_goal_audit'")->fetchColumn()) {
        $a = $pdo->prepare("
            INSERT INTO kpi_goal_audit
                (goal_id, target_user_id, metric, period, old_goal, new_goal, changed_by, action, note)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $a->execute([(int)$row['id'], $user_id, $metric, $period, (int)$row['goal'], $goal, $actor_id, 'update', 'upsert-update']);
    }

    echo json_encode(['ok'=>true, 'id'=>(int)$row['id']]); exit;
}

/* INSERT new */
$stmt = $pdo->prepare("
    INSERT INTO kpi_goals (user_id, module, metric, period, goal)
    VALUES (?,?,?,?,?)
");
try {
    $stmt->execute([$user_id, $module, $metric, $period, $goal]);
} catch (PDOException $e) {
    if ($e->getCode()==='23000') {
        http_response_code(409);
        echo json_encode(['error'=>'Duplicate goal exists for this module/user/metric/period.']); exit;
    }
    throw $e;
}
$newId = (int)$pdo->lastInsertId();

if ($pdo->query("SHOW TABLES LIKE 'kpi_goal_audit'")->fetchColumn()) {
    $a = $pdo->prepare("
        INSERT INTO kpi_goal_audit
            (goal_id, target_user_id, metric, period, old_goal, new_goal, changed_by, action, note)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $a->execute([$newId, $user_id, $metric, $period, null, $goal, $actor_id, 'insert', null]);
}

echo json_encode(['ok'=>true, 'id'=>$newId]);
