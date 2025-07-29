<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// ==== CONFIGURATION ====
require_once __DIR__ . '/../../config/database.php';
$dbHost = $config['host'];
$dbName = $config['dbname'];
$dbUser = $config['user'];
$dbPass = $config['pass'];


$backupTime = date('Ymd-His');
$tmpDir = sys_get_temp_dir() . "/backup_$backupTime";
$zipName = "OpenTalentBackup-$backupTime.zip";
$zipPath = sys_get_temp_dir() . "/$zipName";

// ==== STEP 1: Create temp directory ====
if (!mkdir($tmpDir, 0777, true)) {
    die("❌ Failed to create temporary directory.");
}

// ==== STEP 2: Dump the database ====
$dumpFile = "$tmpDir/database.sql";
$command = [
    'mysqldump',
    "--user=$dbUser",
    "--password=$dbPass",
    "--host=$dbHost",
    $dbName
];
$cmdString = implode(' ', array_map('escapeshellarg', $command)) . " > " . escapeshellarg($dumpFile);
exec($cmdString, $output, $returnVar);
if ($returnVar !== 0 || !file_exists($dumpFile) || filesize($dumpFile) === 0) {
    rrmdir($tmpDir);
    die("❌ Database dump failed or is empty.");
}

// ==== STEP 3: Copy uploads folder ====
$uploadSource = realpath(__DIR__ . '/../uploads');
$uploadDest = "$tmpDir/uploads";
if (is_dir($uploadSource)) {
    recurseCopy($uploadSource, $uploadDest);
} else {
    file_put_contents("$tmpDir/warning.txt", "Uploads folder not found. Only database was backed up.\n");
}

// ==== STEP 4: Zip everything ====
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    rrmdir($tmpDir);
    die("❌ Failed to create zip file.");
}
zipFolder($tmpDir, $zip, strlen($tmpDir) + 1);
$zip->close();

// ==== STEP 5: Output to browser ====
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);

// ==== STEP 6: Cleanup ====
rrmdir($tmpDir);
unlink($zipPath);
exit;

// ==== UTILITIES ====

function recurseCopy($src, $dst) {
    if (!is_dir($src)) return;
    @mkdir($dst, 0777, true);
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $srcPath = "$src/$item";
        $dstPath = "$dst/$item";
        if (is_dir($srcPath)) {
            recurseCopy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = "$dir/$file";
        if (is_dir($path)) rrmdir($path);
        else unlink($path);
    }
    rmdir($dir);
}

function zipFolder($folder, &$zip, $exclusiveLength) {
    if (!is_dir($folder)) return;
    $handle = opendir($folder);
    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $filePath = "$folder/$file";
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
?>

