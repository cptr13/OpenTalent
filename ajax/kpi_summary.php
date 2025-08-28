<?php
// /ajax/kpi_summary.php  (module-aware goals + business-day scaling fallbacks)
// Returns KPI counts & goals for a selected timeframe + "today" block.

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
$tz = new DateTimeZone('America/New_York');
$now = new DateTime('now', $tz);

function range_for_timeframe(string $tf, DateTime $now, DateTimeZone $tz): array {
    $start = clone $now;
    $end   = clone $now;
    switch ($tf) {
        case 'today':
            $start->setTime(0,0,0);
            $end = (clone $start)->modify('+1 day');
            break;
        case 'week': // Monday–Sunday (goals use business-day scaling elsewhere)
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

// Today
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

// ---- Fixed business-day/month scaling (no proration; short months same target)
const BDAYS_PER_WEEK  = 5;
const BDAYS_PER_MONTH = 21;
const WEEKS_PER_MONTH = 4;

// ---- Metrics
const KPI_RECRUITER = ['contact_attempts','conversations','submittals','interviews','offers_made','hires'];
const KPI_SALES     = ['leads_added','contact_attempts','conversations','agreements_signed','job_orders_received'];

/** ================= GOAL LOOKUP + SCALING (module-aware) ================== */

function fetch_goal_exact(PDO $pdo, ?int $user_id, string $module, string $metric, string $period): ?int {
    if ($user_id === null) {
        $sql = "SELECT goal FROM kpi_goals WHERE user_id IS NULL AND module=? AND metric=? AND period=? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$module, $metric, $period]);
    } else {
        $sql = "SELECT goal FROM kpi_goals WHERE user_id=? AND module=? AND metric=? AND period=? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $module, $metric, $period]);
    }
    $g = $stmt->fetchColumn();
    return ($g !== false) ? (int)$g : null;
}

function scale_goal(int $base, string $basePeriod, string $targetPeriod): int {
    if ($basePeriod === $targetPeriod) return $base;

    // Daily → broader
    if ($basePeriod === 'daily') {
        return match ($targetPeriod) {
            'weekly'    => $base * BDAYS_PER_WEEK,
            'monthly'   => $base * BDAYS_PER_MONTH,
            'quarterly' => $base * BDAYS_PER_MONTH * 3,
            'half_year' => $base * BDAYS_PER_MONTH * 6,
            'yearly'    => $base * BDAYS_PER_MONTH * 12,
            default     => 0,
        };
    }

    // Weekly → broader (fixed 4 weeks per month)
    if ($basePeriod === 'weekly') {
        $perMonth = $base * WEEKS_PER_MONTH;
        return match ($targetPeriod) {
            'monthly'   => $perMonth,
            'quarterly' => $perMonth * 3,
            'half_year' => $perMonth * 6,
            'yearly'    => $perMonth * 12,
            default     => 0,
        };
    }

    // No downscaling
    return 0;
}

/**
 * User goal with fallback:
 * 1) Exact user period
 * 2) Exact agency default period
 * 3) If target != daily: derive from user daily → scale; else from agency daily → scale
 * 4) If target in {monthly,quarterly,half_year,yearly}: derive from user weekly → scale; else from agency weekly → scale
 */
function user_goal(PDO $pdo, int $user_id, string $module, string $metric, string $period): int {
    // Exact (user)
    $g = fetch_goal_exact($pdo, $user_id, $module, $metric, $period);
    if ($g !== null) return $g;

    // Exact (agency default)
    $g = fetch_goal_exact($pdo, null, $module, $metric, $period);
    if ($g !== null) return $g;

    // Derive from daily (only upscale)
    if ($period !== 'daily') {
        $dailyUser = fetch_goal_exact($pdo, $user_id, $module, $metric, 'daily');
        if ($dailyUser !== null) return scale_goal($dailyUser, 'daily', $period);

        $dailyDefault = fetch_goal_exact($pdo, null, $module, $metric, 'daily');
        if ($dailyDefault !== null) return scale_goal($dailyDefault, 'daily', $period);
    }

    // Derive from weekly for monthly+
    if (in_array($period, ['monthly','quarterly','half_year','yearly'], true)) {
        $weeklyUser = fetch_goal_exact($pdo, $user_id, $module, $metric, 'weekly');
        if ($weeklyUser !== null) return scale_goal($weeklyUser, 'weekly', $period);

        $weeklyDefault = fetch_goal_exact($pdo, null, $module, $metric, 'weekly');
        if ($weeklyDefault !== null) return scale_goal($weeklyDefault, 'weekly', $period);
    }

    return 0;
}

/**
 * Agency goal with fallback: sum of each active user's user_goal (so user overrides
 * and daily/weekly scaling apply per-user). Matches "agency = totality of everyone".
 */
function agency_goal(PDO $pdo, string $module, string $metric, string $period): int {
    $ids = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $sum = 0;
    foreach ($ids as $uid) {
        $sum += user_goal($pdo, (int)$uid, $module, $metric, $period);
    }
    return $sum;
}

/** ========================= COUNTS (from status_history) ========================= */

function sql_ts_expr(): string { return "COALESCE(changed_at, created_at)"; }

function sql_distinct_key_recruiting(): string {
    return "
        CASE
          WHEN candidate_id IS NOT NULL OR job_id IS NOT NULL
            THEN CONCAT(IFNULL(candidate_id,'?'), '-', IFNULL(job_id,'?'))
          ELSE CONCAT(entity_type,'-',entity_id)
        END
    ";
}

// ---- Recruiter counts (distinct per association)
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

// Recruiter metrics (module = recruiting)
$you_rec    = user_counts_recruiter($pdo, (int)$user_id, $start, $end);
$agency_rec = agency_counts_recruiter($pdo, $start, $end);
foreach ($recruiter_metrics as $m) {
    $out['metrics'][$m] = [
        'you' => [
            'count' => $you_rec[$m] ?? 0,
            'goal'  => user_goal($pdo, (int)$user_id, 'recruiting', $m, $period)
        ],
        'agency' => [
            'count' => $agency_rec[$m] ?? 0,
            'goal'  => agency_goal($pdo, 'recruiting', $m, $period)
        ]
    ];
}

// Sales metrics (module = sales)
$you_sales    = user_counts_sales($pdo, (int)$user_id, $start, $end);
$agency_sales = agency_counts_sales($pdo, $start, $end);
$out['sales_metrics'] = [];
foreach ($sales_metrics as $m) {
    $goal_you  = user_goal($pdo, (int)$user_id, 'sales', $m, $period);
    $count_you = $you_sales[$m] ?? 0;
    $out['sales_metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_sales[$m] ?? 0,
            'goal'  => agency_goal($pdo, 'sales', $m, $period)
        ]
    ];
}

// ---- Add today's block
$you_today_rec      = user_counts_recruiter($pdo, (int)$user_id, $today_start, $today_end);
$agency_today_rec   = agency_counts_recruiter($pdo, $today_start, $today_end);
$you_today_sales    = user_counts_sales($pdo, (int)$user_id, $today_start, $today_end);
$agency_today_sales = agency_counts_sales($pdo, $today_start, $today_end);

$today_period = 'daily';
$out['today'] = ['start'=>$today_start, 'end'=>$today_end, 'metrics'=>[], 'sales_metrics'=>[]];

foreach ($recruiter_metrics as $m) {
    $goal_you  = user_goal($pdo, (int)$user_id, 'recruiting', $m, $today_period);
    $count_you = $you_today_rec[$m] ?? 0;
    $out['today']['metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_today_rec[$m] ?? 0,
            'goal'  => agency_goal($pdo, 'recruiting', $m, $today_period)
        ]
    ];
}
foreach ($sales_metrics as $m) {
    $goal_you  = user_goal($pdo, (int)$user_id, 'sales', $m, $today_period);
    $count_you = $you_today_sales[$m] ?? 0;
    $out['today']['sales_metrics'][$m] = [
        'you' => [
            'count'     => $count_you,
            'goal'      => $goal_you,
            'remaining' => max(0, $goal_you - $count_you)
        ],
        'agency' => [
            'count' => $agency_today_sales[$m] ?? 0,
            'goal'  => agency_goal($pdo, 'sales', $m, $today_period)
        ]
    ];
}

echo json_encode($out);
