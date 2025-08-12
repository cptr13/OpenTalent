<?php
// /ajax/kpi_summary.php
// Returns KPI counts & goals for a selected timeframe + always-included "today" block.

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
if (!$user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// ---- Timeframe parsing (Asia/Manila, Monday–Sunday weeks) ----
$tf = strtolower(trim($_GET['tf'] ?? 'week')); // default: this week

$tz = new DateTimeZone('Asia/Manila'); // your locale
$now = new DateTime('now', $tz);

function range_for_timeframe(string $tf, DateTime $now, DateTimeZone $tz): array {
    $start = clone $now;
    $end   = clone $now;

    switch ($tf) {
        case 'today':
            $start->setTime(0,0,0);
            $end = (clone $start)->modify('+1 day');
            break;

        case 'week': // Monday–Sunday
            $dow = (int)$now->format('N'); // 1=Mon..7=Sun
            $start->modify('-' . ($dow - 1) . ' days')->setTime(0,0,0); // Monday
            $end = (clone $start)->modify('+7 days');
            break;

        case 'month':
            $start->modify('first day of this month')->setTime(0,0,0);
            $end = (clone $start)->modify('+1 month');
            break;

        case 'qtr':
            // 3 months from start of current quarter
            $month = (int)$now->format('n');
            $qStartMonth = (int)(floor(($month - 1) / 3) * 3) + 1; // 1,4,7,10
            $start = new DateTime($now->format('Y') . '-' . $qStartMonth . '-01 00:00:00', $tz);
            $end = (clone $start)->modify('+3 months');
            break;

        case 'half':
            // Half-year: H1 = Jan–Jun, H2 = Jul–Dec
            $month = (int)$now->format('n');
            $hStartMonth = ($month <= 6) ? 1 : 7;
            $start = new DateTime($now->format('Y') . '-' . $hStartMonth . '-01 00:00:00', $tz);
            $end = (clone $start)->modify('+6 months');
            break;

        case 'year':
            $start->setDate((int)$now->format('Y'), 1, 1)->setTime(0,0,0);
            $end = (clone $start)->modify('+1 year');
            break;

        default:
            // Fallback to week
            $dow = (int)$now->format('N');
            $start->modify('-' . ($dow - 1) . ' days')->setTime(0,0,0);
            $end = (clone $start)->modify('+7 days');
            break;
    }

    return [$start, $end];
}

[$startDT, $endDT] = range_for_timeframe($tf, $now, $tz);
$start = $startDT->format('Y-m-d H:i:s');
$end   = $endDT->format('Y-m-d H:i:s');

// Also compute "today" range (always)
$todayStartDT = (clone $now)->setTime(0,0,0);
$todayEndDT   = (clone $todayStartDT)->modify('+1 day');
$today_start  = $todayStartDT->format('Y-m-d H:i:s');
$today_end    = $todayEndDT->format('Y-m-d H:i:s');

// ---- Helpers: goals (per-user & agency) ----
function period_for_tf(string $tf): string {
    return match ($tf) {
        'today' => 'daily',
        'week'  => 'weekly',
        'month' => 'monthly',
        'qtr'   => 'quarterly',
        'half'  => 'half_year',
        'year'  => 'yearly',
        default => 'weekly',
    };
}

function user_goal(PDO $pdo, int $user_id, string $metric, string $period): int {
    $sql = "SELECT goal FROM kpi_goals WHERE user_id = ? AND metric = ? AND period = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $metric, $period]);
    $g = $stmt->fetchColumn();
    if ($g !== false) return (int)$g;

    $sql = "SELECT goal FROM kpi_goals WHERE user_id IS NULL AND metric = ? AND period = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$metric, $period]);
    $g = $stmt->fetchColumn();
    return ($g !== false) ? (int)$g : 0;
}

function agency_goal(PDO $pdo, string $metric, string $period): int {
    $sql = "
        SELECT SUM(COALESCE(ug.goal, dg.goal)) AS total_goal
        FROM users u
        LEFT JOIN kpi_goals ug
               ON ug.user_id = u.id
              AND ug.metric = ?
              AND ug.period = ?
        LEFT JOIN kpi_goals dg
               ON dg.user_id IS NULL
              AND dg.metric = ?
              AND dg.period = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$metric, $period, $metric, $period]);
    $total = $stmt->fetchColumn();
    return ($total !== false) ? (int)$total : 0;
}

// ---- Helpers: counts from status_history ----
function user_counts(PDO $pdo, int $user_id, string $start, string $end): array {
    $sqlDistinct = "
        SELECT kpi_bucket, COUNT(DISTINCT CONCAT(candidate_id,'-',job_id)) AS cnt
        FROM status_history
        WHERE changed_by = :uid
          AND changed_at >= :start AND changed_at < :end
          AND kpi_bucket IN ('contact_attempt','conversation','submittal','placement')
        GROUP BY kpi_bucket
    ";
    $stmt = $pdo->prepare($sqlDistinct);
    $stmt->execute([':uid'=>$user_id, ':start'=>$start, ':end'=>$end]);
    $distinct = ['contact_attempt'=>0,'conversation'=>0,'submittal'=>0,'placement'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $distinct[$row['kpi_bucket']] = (int)$row['cnt'];
    }

    $sqlInterview = "
        SELECT COUNT(*) AS cnt
        FROM status_history
        WHERE changed_by = :uid
          AND changed_at >= :start AND changed_at < :end
          AND kpi_bucket = 'interview'
    ";
    $stmt = $pdo->prepare($sqlInterview);
    $stmt->execute([':uid'=>$user_id, ':start'=>$start, ':end'=>$end]);
    $interviews = (int)$stmt->fetchColumn();

    return [
        'contact_attempt' => $distinct['contact_attempt'],
        'conversation'    => $distinct['conversation'],
        'submittal'       => $distinct['submittal'],
        'interview'       => $interviews,
        'placement'       => $distinct['placement'],
    ];
}

function agency_counts(PDO $pdo, string $start, string $end): array {
    $sqlDistinct = "
        SELECT kpi_bucket, COUNT(DISTINCT CONCAT(candidate_id,'-',job_id)) AS cnt
        FROM status_history
        WHERE changed_at >= :start AND changed_at < :end
          AND kpi_bucket IN ('contact_attempt','conversation','submittal','placement')
        GROUP BY kpi_bucket
    ";
    $stmt = $pdo->prepare($sqlDistinct);
    $stmt->execute([':start'=>$start, ':end'=>$end]);
    $distinct = ['contact_attempt'=>0,'conversation'=>0,'submittal'=>0,'placement'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $distinct[$row['kpi_bucket']] = (int)$row['cnt'];
    }

    $sqlInterview = "
        SELECT COUNT(*) AS cnt
        FROM status_history
        WHERE changed_at >= :start AND changed_at < :end
          AND kpi_bucket = 'interview'
    ";
    $stmt = $pdo->prepare($sqlInterview);
    $stmt->execute([':start'=>$start, ':end'=>$end]);
    $interviews = (int)$stmt->fetchColumn();

    return [
        'contact_attempt' => $distinct['contact_attempt'],
        'conversation'    => $distinct['conversation'],
        'submittal'       => $distinct['submittal'],
        'interview'       => $interviews,
        'placement'       => $distinct['placement'],
    ];
}

// ---- Build main timeframe response ----
$period = period_for_tf($tf);
$you    = user_counts($pdo, (int)$user_id, $start, $end);
$agency = agency_counts($pdo, $start, $end);

$metrics = ['contact_attempt','conversation','submittal','interview','placement'];
$out = [
    'timeframe' => $tf,
    'start'     => $start,
    'end'       => $end,
    'metrics'   => []
];

foreach ($metrics as $m) {
    $out['metrics'][$m] = [
        'you' => [
            'count' => $you[$m] ?? 0,
            'goal'  => user_goal($pdo, (int)$user_id, $m, $period),
        ],
        'agency' => [
            'count' => $agency[$m] ?? 0,
            'goal'  => agency_goal($pdo, $m, $period),
        ]
    ];
}

// ---- Add today's block (always) ----
$you_today    = user_counts($pdo, (int)$user_id, $today_start, $today_end);
$agency_today = agency_counts($pdo, $today_start, $today_end);
$today_period = 'daily';

$out['today'] = [
    'start'   => $today_start,
    'end'     => $today_end,
    'metrics' => []
];

foreach ($metrics as $m) {
    $goal_you = user_goal($pdo, (int)$user_id, $m, $today_period);
    $count_you = $you_today[$m] ?? 0;
    $out['today']['metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_today[$m] ?? 0,
            'goal'  => agency_goal($pdo, $m, $today_period)
        ]
    ];
}

echo json_encode($out);
