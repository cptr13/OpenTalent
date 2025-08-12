<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('ROOT_PATH', dirname(__DIR__, 2)); // /public/tools/ → project root
require_once ROOT_PATH . '/includes/require_login.php';
require_once ROOT_PATH . '/config/database.php';

$confirmation = $_POST['confirm'] ?? null;
$action = $_POST['action'] ?? 'reset';
$message = '';

function split_sql_statements(string $sql): array {
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $statements = []; $buffer = '';
    $inSingle=false; $inDouble=false; $inBacktick=false; $inLineComment=false; $inBlockComment=false;
    $len = strlen($sql);
    for ($i=0; $i<$len; $i++) {
        $ch = $sql[$i]; $next = $i+1<$len ? $sql[$i+1] : '';
        if ($inLineComment){ if($ch === "\n") $inLineComment=false; continue; }
        if ($inBlockComment){ if($ch==='*'&&$next=== '/'){$inBlockComment=false;$i++;} continue; }
        if(!$inSingle && !$inDouble && !$inBacktick){
            if($ch==='-'&&$next==='-'){ $inLineComment=true; $i++; continue; }
            if($ch==='#'){ $inLineComment=true; continue; }
            if($ch==='/'&&$next==='*'){ $inBlockComment=true; $i++; continue; }
            if($ch==="'" ){ $inSingle=true;  $buffer.=$ch; continue; }
            if($ch=='"'){ $inDouble=true;  $buffer.=$ch; continue; }
            if($ch=='`'){ $inBacktick=true; $buffer.=$ch; continue; }
            if($ch===';'){ $stmt=trim($buffer); if($stmt!=='') $statements[]=$stmt; $buffer=''; continue; }
            $buffer.=$ch; continue;
        }
        $buffer.=$ch;
        if($inSingle){ if($ch==='\\' && $next!==''){ $buffer.=$next; $i++; continue; } if($ch==="'" && $sql[$i-1] !== '\\'){ $inSingle=false; } }
        elseif($inDouble){ if($ch==='\\' && $next!==''){ $buffer.=$next; $i++; continue; } if($ch=='"' && $sql[$i-1] !== '\\'){ $inDouble=false; } }
        else{ if($ch=='`'){ $inBacktick=false; } }
    }
    $tail=trim($buffer); if($tail!=='') $statements[]=$tail;
    return $statements;
}

function run_schema(PDO $pdo, string $path): int {
    if (!file_exists($path)) throw new Exception("Missing schema.sql — cannot reset without a valid schema file.");
    $sql = file_get_contents($path);
    if ($sql === false || $sql === '') throw new Exception("schema.sql is empty or unreadable.");
    $stmts = split_sql_statements($sql);
    if (empty($stmts)) throw new Exception("No executable SQL statements found in schema.sql.");
    $count = 0;
    foreach ($stmts as $stmt) { $pdo->exec($stmt); $count++; }
    return $count;
}

function reseed_kpi(PDO $pdo): void {
    // Idempotent seeds
    $pdo->exec("
        INSERT IGNORE INTO kpi_status_map (module, status_name, kpi_bucket, event_type) VALUES
        ('recruiting','New','none',NULL),
        ('recruiting','Associated to Job','none',NULL),
        ('recruiting','Attempted to Contact','contact_attempt',NULL),
        ('recruiting','Contacted','contact_attempt',NULL),
        ('recruiting','Screening / Conversation','conversation',NULL),
        ('recruiting','No-Show','none',NULL),
        ('recruiting','Interview to be Scheduled','none',NULL),
        ('recruiting','Interview Scheduled','interview','interview_scheduled'),
        ('recruiting','Waiting on Client Feedback','none',NULL),
        ('recruiting','Second Interview to be Scheduled','none',NULL),
        ('recruiting','Second Interview Scheduled','interview','second_interview_scheduled'),
        ('recruiting','Submitted to Client','submittal',NULL),
        ('recruiting','Approved by Client','none',NULL),
        ('recruiting','To be Offered','none',NULL),
        ('recruiting','Offer Made','none',NULL),
        ('recruiting','Offer Accepted','none',NULL),
        ('recruiting','Offer Declined','none',NULL),
        ('recruiting','Offer Withdrawn','none',NULL),
        ('recruiting','Hired','placement',NULL),
        ('recruiting','On Hold','none',NULL),
        ('recruiting','Position Closed','none',NULL),
        ('recruiting','Contact in Future','none',NULL),
        ('recruiting','Rejected','none',NULL),
        ('recruiting','Rejected – By Client','none',NULL),
        ('recruiting','Rejected – For Interview','none',NULL),
        ('recruiting','Rejected – Hirable','none',NULL),
        ('recruiting','Unqualified','none',NULL),
        ('recruiting','Not Interested','none',NULL),
        ('recruiting','Ghosted','none',NULL),
        ('recruiting','Paused by Candidate','none',NULL),
        ('recruiting','Withdrawn by Candidate','none',NULL);
    ");
    $pdo->exec("
        INSERT IGNORE INTO kpi_goals (user_id, metric, period, goal) VALUES
        (NULL,'contact_attempt','daily',50),
        (NULL,'contact_attempt','weekly',250),
        (NULL,'contact_attempt','monthly',1000),
        (NULL,'contact_attempt','quarterly',3000),
        (NULL,'contact_attempt','half_year',6000),
        (NULL,'contact_attempt','yearly',12000),
        (NULL,'conversation','daily',5),
        (NULL,'conversation','weekly',25),
        (NULL,'conversation','monthly',100),
        (NULL,'conversation','quarterly',300),
        (NULL,'conversation','half_year',600),
        (NULL,'conversation','yearly',1200),
        (NULL,'submittal','daily',1),
        (NULL,'submittal','weekly',5),
        (NULL,'submittal','monthly',20),
        (NULL,'submittal','quarterly',60),
        (NULL,'submittal','half_year',120),
        (NULL,'submittal','yearly',240),
        (NULL,'interview','daily',0),
        (NULL,'interview','weekly',2),
        (NULL,'interview','monthly',8),
        (NULL,'interview','quarterly',24),
        (NULL,'interview','half_year',48),
        (NULL,'interview','yearly',96),
        (NULL,'placement','daily',0),
        (NULL,'placement','weekly',0),
        (NULL,'placement','monthly',1),
        (NULL,'placement','quarterly',3),
        (NULL,'placement','half_year',6),
        (NULL,'placement','yearly',12);
    ");
}

function post_reset_smoke_checks(PDO $pdo): array {
    $issues = [];

    // Tables
    $needTables = ['kpi_status_map','status_history','kpi_goals','users','candidates','jobs','app_meta'];
    foreach ($needTables as $t) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$t]);
        if (!$stmt->fetchColumn()) $issues[] = "Missing table: {$t}";
    }

    // Critical status labels
    $critical = [
        'Submitted to Client','Interview Scheduled','Second Interview Scheduled','Hired',
        'Screening / Conversation','Attempted to Contact','Contacted','No-Show'
    ];
    $placeholders = implode(',', array_fill(0, count($critical), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kpi_status_map WHERE module='recruiting' AND status_name IN ($placeholders)");
    $stmt->execute($critical);
    $found = (int)$stmt->fetchColumn();
    if ($found < count($critical)) {
        $issues[] = "kpi_status_map missing one or more critical statuses (found {$found}/".count($critical).")";
    }

    // KPI goals seeded
    $goals = (int)$pdo->query("SELECT COUNT(*) FROM kpi_goals")->fetchColumn();
    if ($goals < 30) $issues[] = "kpi_goals has only {$goals} rows; expected >= 30";

    // Foreign keys present
    $fkNames = ['fk_hist_user','fk_hist_candidate','fk_hist_job','fk_goals_user'];
    $in = implode(',', array_fill(0, count($fkNames), '?'));
    $q = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME IN ($in)
    ");
    $q->execute($fkNames);
    $fkCount = (int)$q->fetchColumn();
    if ($fkCount < count($fkNames)) $issues[] = "Foreign keys not fully in place (found {$fkCount}/".count($fkNames).")";

    // Schema version present
    $ver = $pdo->query("SELECT `value` FROM app_meta WHERE `key`='schema_version'")->fetchColumn();
    if (!$ver) $issues[] = "app_meta.schema_version not set";

    return $issues;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reseed') {
        reseed_kpi($pdo);
        $message = "<div class='alert alert-success'>KPI mappings & goals reseeded successfully.</div>";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset' && $confirmation === 'YES') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        @file_put_contents(ROOT_PATH . '/debug_reset_tables.txt', implode("\n", $tables));
        foreach ($tables as $table) { $pdo->exec("DROP TABLE IF EXISTS `$table`"); }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        $executed = run_schema($pdo, ROOT_PATH . '/config/schema.sql');

        // Smoke checks
        $issues = post_reset_smoke_checks($pdo);
        if (!empty($issues)) {
            throw new Exception("Post-reset checks failed:\n - " . implode("\n - ", $issues));
        }

        // Clean uploads
        $uploadDirs = [ ROOT_PATH . '/uploads/resumes/', ROOT_PATH . '/uploads/attachments/' ];
        foreach ($uploadDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                if ($files) foreach ($files as $file) { if (is_file($file)) @unlink($file); }
            }
        }
        @file_put_contents(ROOT_PATH . '/debug_reset_exec.txt', "Executed statements: {$executed}\n", FILE_APPEND);
        $message = "<div class='alert alert-success'>Factory reset completed successfully. Executed {$executed} statements.</div>";
    }
} catch (Exception $e) {
    $message = "<div class='alert alert-danger'>Error during reset: " . nl2br(htmlspecialchars($e->getMessage())) . "</div>";
}

require_once ROOT_PATH . '/includes/header.php';
?>
<div class="container mt-5">
  <h2 class="mb-4 text-danger">⚠️ Factory Reset</h2>
  <?= $message ?>
  <div class="card border-danger mb-4">
    <div class="card-body">
      <p>This will permanently delete <strong>all database records</strong> and <strong>uploaded files</strong>.</p>
      <p><strong>This action cannot be undone.</strong></p>
      <form method="post">
        <input type="hidden" name="action" value="reset">
        <div class="form-group">
          <label for="confirm">Type <strong>YES</strong> to confirm:</label>
          <input type="text" name="confirm" id="confirm" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-danger">Run Factory Reset</button>
      </form>
    </div>
  </div>

  <div class="card border-secondary">
    <div class="card-body">
      <h5 class="card-title">Reseed KPI Mappings & Goals</h5>
      <p class="card-text">If mappings or goals are missing or edited, reseed them without dropping any tables.</p>
      <form method="post" onsubmit="return confirm('Reseed KPI mappings & goals now?');">
        <input type="hidden" name="action" value="reseed">
        <button type="submit" class="btn btn-secondary">Reseed KPI Data</button>
      </form>
    </div>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
