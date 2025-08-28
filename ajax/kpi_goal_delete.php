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
$body = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$id = (int)($body['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); exit; }

$actor_id = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);

/* Load row (for audit + to ensure it exists) */
$sel = $pdo->prepare("SELECT * FROM kpi_goals WHERE id = ?");
$sel->execute([$id]);
$row = $sel->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }

/* Delete */
$del = $pdo->prepare("DELETE FROM kpi_goals WHERE id = ?");
$del->execute([$id]);

/* Audit (if table exists) */
if ($pdo->query("SHOW TABLES LIKE 'kpi_goal_audit'")->fetchColumn()) {
    $a = $pdo->prepare("
        INSERT INTO kpi_goal_audit
            (goal_id, target_user_id, metric, period, old_goal, new_goal, changed_by, action, note)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $a->execute([$id, $row['user_id'], $row['metric'], $row['period'], (int)$row['goal'], null, $actor_id, 'delete', null]);
}

echo json_encode(['ok'=>true]);
