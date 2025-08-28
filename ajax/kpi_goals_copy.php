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
$payload = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$source_user  = (int)($payload['source_user'] ?? 0);
$target_users = $payload['target_users'] ?? [];
$module = 'sales'; // sales-only for now

if ($source_user <= 0 || !is_array($target_users) || count($target_users) === 0) {
    http_response_code(400); echo json_encode(['error'=>'Select source and target users']); exit;
}

/* Load source user's sales goals */
$src = $pdo->prepare("SELECT metric, period, goal FROM kpi_goals WHERE module=? AND user_id=?");
$src->execute([$module, $source_user]);
$rows = $src->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) { http_response_code(400); echo json_encode(['error'=>'Source user has no goals']); exit; }

/* Prep statements */
$upsert = $pdo->prepare("
    INSERT INTO kpi_goals (user_id, module, metric, period, goal)
    VALUES (?,?,?,?,?)
    ON DUPLICATE KEY UPDATE goal = VALUES(goal)
");
$getId  = $pdo->prepare("SELECT id, goal FROM kpi_goals WHERE user_id=? AND module=? AND metric=? AND period=?");
$audit  = $pdo->prepare("
    INSERT INTO kpi_goal_audit
        (goal_id, target_user_id, metric, period, old_goal, new_goal, changed_by, action, note)
    VALUES (?,?,?,?,?,?,?,?,?)
");

$actor_id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);
$cnt = 0;

foreach ($target_users as $uid) {
    $uid = (int)$uid;
    foreach ($rows as $r) {
        $metric = $r['metric']; $period = $r['period']; $goal = (int)$r['goal'];

        $getId->execute([$uid,$module,$metric,$period]); $prev = $getId->fetch(PDO::FETCH_ASSOC);

        $upsert->execute([$uid,$module,$metric,$period,$goal]);

        $getId->execute([$uid,$module,$metric,$period]); $cur = $getId->fetch(PDO::FETCH_ASSOC);
        $goalId = $cur ? (int)$cur['id'] : null;

        if ($pdo->query("SHOW TABLES LIKE 'kpi_goal_audit'")->fetchColumn()) {
            $audit->execute([$goalId, $uid, $metric, $period, $prev ? (int)$prev['goal'] : null, $goal, $actor_id, $prev?'update':'insert', 'copy from user '.$source_user]);
        }
        $cnt++;
    }
}

echo json_encode(['ok'=>true, 'updated'=>$cnt]);
