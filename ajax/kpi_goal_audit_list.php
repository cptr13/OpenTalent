<?php
ini_set('display_errors',1); error_reporting(E_ALL);
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'admin') {
    http_response_code(403); echo json_encode(['error'=>'Admins only']); exit;
}
$pdo = $pdo ?? null; if (!$pdo) { http_response_code(500); echo json_encode(['error'=>'DB']); exit; }

$limit = max(1, min(500, (int)($_GET['limit'] ?? 200)));

$sql = "SELECT a.changed_at, a.changed_by, u.full_name AS changed_by_name,
               a.action, a.target_user_id, tu.full_name AS target_user_name,
               a.metric, a.period, a.old_goal, a.new_goal, a.note
        FROM kpi_goal_audit a
        LEFT JOIN users u  ON a.changed_by = u.id
        LEFT JOIN users tu ON a.target_user_id = tu.id
        ORDER BY a.changed_at DESC
        LIMIT ?";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
