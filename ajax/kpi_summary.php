<?php
// /ajax/kpi_summary.php
// Returns KPI counts & goals for a selected timeframe + always-included "today" block.
// SALES: count EVERY event (no DISTINCT / no de-dupe). Final Sales KPIs:
//   leads_added, contact_attempts, conversations, agreements_signed, job_orders_received

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// ---- Timeframe parsing ----
$tf = strtolower(trim($_GET['tf'] ?? 'week')); // default: this week
$tz = new DateTimeZone('America/New_York');    // do not change tz handling
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
            $start->modify('-' . ($dow - 1) . ' days')->setTime(0,0,0);
            $end = (clone $start)->modify('+7 days');
            break;
        case 'month':
            $start->modify('first day of this month')->setTime(0,0,0);
            $end = (clone $start)->modify('+1 month');
            break;
        case 'qtr':
            $month = (int)$now->format('n');
            $qStartMonth = (int)(floor(($month - 1) / 3) * 3) + 1; // 1,4,7,10
            $start = new DateTime($now->format('Y') . '-' . $qStartMonth . '-01 00:00:00', $tz);
            $end = (clone $start)->modify('+3 months');
            break;
        case 'half':
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

// ---- Goals helpers ----
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

// ---- Utility / constants
const KPI_RECRUITER = ['contact_attempts','conversations','submittals','interviews','offers_made','hires'];

// FINAL Sales KPIs (no opportunities_identified, no meetings; include leads_added)
const KPI_SALES = ['leads_added','contact_attempts','conversations','agreements_signed','job_orders_received'];

function sql_ts_expr(): string { return "COALESCE(changed_at, created_at)"; }
function sql_distinct_key_recruiting(): string {
    // Prefer legacy pair if available, else fallback to canonical entity
    return "
        CASE
          WHEN candidate_id IS NOT NULL OR job_id IS NOT NULL
            THEN CONCAT(IFNULL(candidate_id,'?'), '-', IFNULL(job_id,'?'))
          ELSE CONCAT(entity_type,'-',entity_id)
        END
    ";
}

// ---- Recruiter counts (candidate/job): keep DISTINCT semantics for recruiting
function user_counts_recruiter(PDO $pdo, int $user_id, string $start, string $end): array {
    $out = array_fill_keys(KPI_RECRUITER, 0);
    $ts  = sql_ts_expr();
    $key = sql_distinct_key_recruiting();
    $in  = "'" . implode("','", KPI_RECRUITER) . "'";
    $sql = "
        SELECT kpi_bucket, COUNT(DISTINCT {$key}) AS cnt
        FROM status_history
        WHERE changed_by = :uid
          AND {$ts} >= :start AND {$ts} < :end
          AND (entity_type IN ('candidate','job') OR candidate_id IS NOT NULL OR job_id IS NOT NULL)
          AND kpi_bucket IN ({$in})
        GROUP BY kpi_bucket
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid'=>$user_id, ':start'=>$start, ':end'=>$end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bucket = $row['kpi_bucket'];
        if (isset($out[$bucket])) $out[$bucket] = (int)$row['cnt'];
    }
    return $out;
}

function agency_counts_recruiter(PDO $pdo, string $start, string $end): array {
    $out = array_fill_keys(KPI_RECRUITER, 0);
    $ts  = sql_ts_expr();
    $key = sql_distinct_key_recruiting();
    $in  = "'" . implode("','", KPI_RECRUITER) . "'";
    $sql = "
        SELECT kpi_bucket, COUNT(DISTINCT {$key}) AS cnt
        FROM status_history
        WHERE {$ts} >= :start AND {$ts} < :end
          AND (entity_type IN ('candidate','job') OR candidate_id IS NOT NULL OR job_id IS NOT NULL)
          AND kpi_bucket IN ({$in})
        GROUP BY kpi_bucket
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start'=>$start, ':end'=>$end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bucket = $row['kpi_bucket'];
        if (isset($out[$bucket])) $out[$bucket] = (int)$row['cnt'];
    }
    return $out;
}

/* ===================== SALES COUNTS (NO DE-DUPE) ===================== */

function user_counts_sales(PDO $pdo, int $user_id, string $start, string $end): array {
    $out = array_fill_keys(KPI_SALES, 0);
    $ts  = sql_ts_expr();
    $in  = "'" . implode("','", KPI_SALES) . "'";

    // Count every event (no DISTINCT)
    $sql = "
        SELECT kpi_bucket, COUNT(*) AS cnt
        FROM status_history
        WHERE changed_by = :uid
          AND {$ts} >= :start AND {$ts} < :end
          AND entity_type = 'contact'
          AND kpi_bucket IN ({$in})
        GROUP BY kpi_bucket
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid'=>$user_id, ':start'=>$start, ':end'=>$end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bucket = $row['kpi_bucket'];
        if (isset($out[$bucket])) $out[$bucket] = (int)$row['cnt'];
    }
    return $out;
}

function agency_counts_sales(PDO $pdo, string $start, string $end): array {
    $out = array_fill_keys(KPI_SALES, 0);
    $ts  = sql_ts_expr();
    $in  = "'" . implode("','", KPI_SALES) . "'";

    // Count every event (no DISTINCT)
    $sql = "
        SELECT kpi_bucket, COUNT(*) AS cnt
        FROM status_history
        WHERE {$ts} >= :start AND {$ts} < :end
          AND entity_type = 'contact'
          AND kpi_bucket IN ({$in})
        GROUP BY kpi_bucket
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':start'=>$start, ':end'=>$end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bucket = $row['kpi_bucket'];
        if (isset($out[$bucket])) $out[$bucket] = (int)$row['cnt'];
    }
    return $out;
}

// ---- Metric order
$recruiter_metrics = KPI_RECRUITER;
$sales_metrics     = KPI_SALES;

// ---- Build main timeframe response
$period = period_for_tf($tf);
$out = ['timeframe'=>$tf,'start'=>$start,'end'=>$end,'metrics'=>[]];

// recruiter metrics (kept under original keys so the UI keeps working)
$you_rec    = user_counts_recruiter($pdo, (int)$user_id, $start, $end);
$agency_rec = agency_counts_recruiter($pdo, $start, $end);
foreach ($recruiter_metrics as $m) {
    $out['metrics'][$m] = [
        'you' => [
            'count' => $you_rec[$m] ?? 0,
            'goal'  => user_goal($pdo, (int)$user_id, $m, $period)
        ],
        'agency' => [
            'count' => $agency_rec[$m] ?? 0,
            'goal'  => agency_goal($pdo, $m, $period)
        ]
    ];
}

// sales metrics go to a separate object to avoid overwriting
$you_sales    = user_counts_sales($pdo, (int)$user_id, $start, $end);
$agency_sales = agency_counts_sales($pdo, $start, $end);
$out['sales_metrics'] = [];
foreach ($sales_metrics as $m) {
    $goal_you  = user_goal($pdo, (int)$user_id, $m, $period);
    $count_you = $you_sales[$m] ?? 0;
    $out['sales_metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_sales[$m] ?? 0,
            'goal'  => agency_goal($pdo, $m, $period)
        ]
    ];
}

// ---- Add today's block (recruiting under original keys; sales separated)
$you_today_rec      = user_counts_recruiter($pdo, (int)$user_id, $today_start, $today_end);
$agency_today_rec   = agency_counts_recruiter($pdo, $today_start, $today_end);
$you_today_sales    = user_counts_sales($pdo, (int)$user_id, $today_start, $today_end);
$agency_today_sales = agency_counts_sales($pdo, $today_start, $today_end);

$today_period = 'daily';
$out['today'] = ['start'=>$today_start, 'end'=>$today_end, 'metrics'=>[], 'sales_metrics'=>[]];

foreach ($recruiter_metrics as $m) {
    $goal_you  = user_goal($pdo, (int)$user_id, $m, $today_period);
    $count_you = $you_today_rec[$m] ?? 0;
    $out['today']['metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_today_rec[$m] ?? 0,
            'goal'  => agency_goal($pdo, $m, $today_period)
        ]
    ];
}

foreach ($sales_metrics as $m) {
    $goal_you  = user_goal($pdo, (int)$user_id, $m, $today_period);
    $count_you = $you_today_sales[$m] ?? 0;
    $out['today']['sales_metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_today_sales[$m] ?? 0,
            'goal'  => agency_goal($pdo, $m, $today_period)
        ]
    ];
}

echo json_encode($out);
