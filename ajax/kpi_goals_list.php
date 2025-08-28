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

/* Inputs */
$module = strtolower(trim($_GET['module'] ?? 'sales'));
if (!in_array($module, ['sales','recruiting'], true)) $module = 'sales';

$user_id = $_GET['user_id'] ?? null; // 'null' or numeric or ''
$user_id = ($user_id === 'null' || $user_id === '') ? null : (int)$user_id;
$metric  = $_GET['metric']  ?? null;
$period  = $_GET['period']  ?? null;

/* Metric allowlist by module */
$salesMetrics = ['leads_added','contact_attempts','conversations','agreements_signed','job_orders_received'];
$recruitingMetrics = ['contact_attempts','conversations','submittals','interviews','offers_made','hires'];
$allowedMetrics = $module === 'recruiting' ? $recruitingMetrics : $salesMetrics;

/* Query goals (by module) */
$params = [$module];
$sql = "SELECT id, user_id, metric, period, goal
        FROM kpi_goals
        WHERE module = ?
          AND metric IN ('".implode("','",$allowedMetrics)."')";

if ($user_id !== null || isset($_GET['user_id'])) {
    $sql .= " AND (user_id <=> ?)";
    $params[] = $user_id;
}
if ($metric) { $sql .= " AND metric = ?"; $params[] = $metric; }
if ($period) { $sql .= " AND period = ?"; $params[] = $period; }

$sql .= " ORDER BY (user_id IS NULL) DESC, user_id, metric, period";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Users for dropdowns */
$u = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

/* Agency defaults map for this module */
$dstmt = $pdo->prepare("SELECT metric, period, goal FROM kpi_goals WHERE module=? AND user_id IS NULL");
$dstmt->execute([$module]);
$defaults = [];
foreach ($dstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $defaults[$r['metric'].'|'.$r['period']] = (int)$r['goal'];
}

/* Output */
echo json_encode(['items'=>$rows, 'users'=>$u, 'defaults'=>$defaults]);
