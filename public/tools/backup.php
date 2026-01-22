<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// If you want this protected, uncomment these lines:
// require_once __DIR__ . '/../../includes/require_login.php';

require_once __DIR__ . '/../../config/database.php';

$dbHost = $config['host'];
$dbName = $config['dbname'];
$dbUser = $config['user'];
$dbPass = $config['pass'];

// Tables that should NOT be included in the "content-only" dump
// (schema.sql should be the source of truth for these seeds)
$excludeFromContentDump = [
    'scripts',
    'outreach_templates',
    'script_types',
    'tone_kits',
    'tone_phrases',
    'script_templates',
    'script_rules_stage',
    'script_rules_persona',
    'script_activity_log',
    'script_templates_unified',
    'script_templates_unified_bak_20251102',
    'outreach_objections',
    'outreach_responses',
];

// --------- Utilities ---------

function recurseCopy($src, $dst) {
    if (!is_dir($src)) return;
    @mkdir($dst, 0777, true);
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($srcPath)) {
            recurseCopy($srcPath, $dstPath);
        } else {
            @copy($srcPath, $dstPath);
        }
    }
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}

function zipFolder($folder, &$zip, $exclusiveLength) {
    if (!is_dir($folder)) return;
    $handle = opendir($folder);
    if ($handle === false) return;

    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $folder . DIRECTORY_SEPARATOR . $file;
        $localPath = substr($filePath, $exclusiveLength);

        if (is_dir($filePath)) {
            $zip->addEmptyDir($localPath);
            zipFolder($filePath, $zip, $exclusiveLength);
        } else {
            $zip->addFile($filePath, $localPath);
        }
    }
    closedir($handle);
}

/**
 * Run mysqldump using the same “old working code” approach:
 * - escapeshellarg each token
 * - redirect stdout to file
 * - redirect stderr to separate file for error display
 */
function run_mysqldump_to_file(array $commandTokens, string $outFile, string $errFile): array {
    $cmdString = implode(' ', array_map('escapeshellarg', $commandTokens))
        . " > " . escapeshellarg($outFile)
        . " 2> " . escapeshellarg($errFile);

    $output = [];
    $returnVar = 0;
    exec($cmdString, $output, $returnVar);

    $errText = '';
    if (file_exists($errFile)) {
        $errText = trim((string)file_get_contents($errFile));
    }

    return [$returnVar, $errText, $cmdString];
}

function findUploadsDir(): ?string {
    // Preferred: project root uploads (/uploads)
    $candidates = [
        realpath(__DIR__ . '/../../uploads'),
        realpath(__DIR__ . '/../uploads'), // legacy fallback
    ];

    foreach ($candidates as $cand) {
        if ($cand && is_dir($cand)) return $cand;
    }
    return null;
}

function renderBackupUI(): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>OpenTalent Backup</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background:#f8f9fa; }
            .container { max-width: 760px; }
            .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        </style>
    </head>
    <body>
    <div class="container py-5">
        <h2 class="mb-3">Backup</h2>
        <p class="text-muted">
            Choose the type of backup you want. Each button downloads a separate ZIP.
        </p>

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Full Backup (Clone)</h5>
                <p class="card-text">
                    Includes <span class="mono">schema + data</span> (full database dump) plus <span class="mono">uploads/</span>.
                    Use this to fully restore a system exactly as-is.
                </p>
                <a class="btn btn-primary" href="?download=full">Download Full Backup ZIP</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Content Backup (Data Only)</h5>
                <p class="card-text">
                    Includes <span class="mono">data-only</span> dump (excludes seeded/script tables that should come from <span class="mono">schema.sql</span>),
                    plus <span class="mono">uploads/</span>.
                    Use this after a fresh install so your <span class="mono">schema.sql</span> stays the source of truth.
                </p>
                <a class="btn btn-outline-primary" href="?download=content">Download Content Backup ZIP</a>
            </div>
        </div>

        <div class="alert alert-secondary mt-4 small">
            Content backup excludes tables like: scripts, outreach_templates, script_templates_unified, tone_kits, etc.
        </div>
    </div>
    </body>
    </html>
    <?php
}

// --------- Main ---------

$mode = $_GET['download'] ?? '';

if ($mode !== 'full' && $mode !== 'content') {
    renderBackupUI();
    exit;
}

$backupTime = date('Ymd-His');
$tmpDir = sys_get_temp_dir() . "/backup_" . $mode . "_" . $backupTime;

if (!mkdir($tmpDir, 0777, true)) {
    die("❌ Failed to create temporary directory.");
}

// Dump file names inside the zip
$dumpFileName = ($mode === 'full') ? "database_full.sql" : "database_content.sql";
$dumpFile = $tmpDir . "/" . $dumpFileName;
$errFile  = $tmpDir . "/" . $dumpFileName . ".err";

// Build mysqldump command tokens (keep it “old-style safe”)
if ($mode === 'full') {
    $command = [
        'mysqldump',
        "--user=$dbUser",
        "--password=$dbPass",
        "--host=$dbHost",
        $dbName
    ];
} else {
    // Content-only: data only, exclude seed/script tables
    $command = [
        'mysqldump',
        "--user=$dbUser",
        "--password=$dbPass",
        "--host=$dbHost",
        '--no-create-info',
        '--skip-triggers',
        $dbName
    ];

    foreach ($excludeFromContentDump as $tbl) {
        $command[] = "--ignore-table={$dbName}.{$tbl}";
    }
}

list($rc, $errText, $cmdString) = run_mysqldump_to_file($command, $dumpFile, $errFile);

if ($rc !== 0 || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
    $msg = "❌ " . (($mode === 'full') ? "Full" : "Content-only") . " database dump failed or is empty.";
    if (!empty($errText)) {
        $msg .= "\n\nmysqldump error:\n" . $errText;
    } else {
        $msg .= "\n\nmysqldump produced no stderr output.\nCommand:\n" . $cmdString;
    }
    rrmdir($tmpDir);
    die(nl2br(htmlspecialchars($msg)));
}

// Copy uploads folder into temp dir
$uploadsSource = findUploadsDir();
if ($uploadsSource && is_dir($uploadsSource)) {
    recurseCopy($uploadsSource, $tmpDir . "/uploads");
} else {
    file_put_contents($tmpDir . "/warning.txt", "Uploads folder not found. Only database dump was included.\n");
}

// Manifest
$manifest = [];
$manifest[] = "OpenTalent Backup";
$manifest[] = "Timestamp: $backupTime";
$manifest[] = "Database: $dbName";
$manifest[] = "Mode: " . strtoupper($mode);
$manifest[] = "";
$manifest[] = "Included:";
$manifest[] = "- " . $dumpFileName;
$manifest[] = "- uploads/ (if present)";
$manifest[] = "";
if ($mode === 'content') {
    $manifest[] = "Excluded from content dump:";
    foreach ($excludeFromContentDump as $tbl) {
        $manifest[] = "- $tbl";
    }
}
file_put_contents($tmpDir . "/manifest.txt", implode("\n", $manifest) . "\n");

// Create zip
$zipName = ($mode === 'full')
    ? "OpenTalentBackup-FULL-$backupTime.zip"
    : "OpenTalentBackup-CONTENT-$backupTime.zip";

$zipPath = sys_get_temp_dir() . "/" . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    rrmdir($tmpDir);
    die("❌ Failed to create zip file.");
}

zipFolder($tmpDir, $zip, strlen($tmpDir) + 1);
$zip->close();

// Stream zip to browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
readfile($zipPath);

// Cleanup
rrmdir($tmpDir);
@unlink($zipPath);
exit;
