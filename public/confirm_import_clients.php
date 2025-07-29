<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$successCount = 0;
$errorCount = 0;
$errors = [];
$successLog = [];

$tempFile = $_POST['temp_file'] ?? '';
$dupeAction = $_POST['dupe_action'] ?? 'skip';

if (!file_exists($tempFile)) {
    echo "<div class='alert alert-danger'>Temporary file not found. Please re-upload.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

try {
    $spreadsheet = IOFactory::load($tempFile);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;

        $name = isset($row[0]) ? trim($row[0]) : '';
        $location = isset($row[1]) ? trim($row[1]) : '';
        $industry = isset($row[2]) ? trim($row[2]) : '';
        $url = isset($row[3]) ? trim($row[3]) : '';
        $phone = isset($row[4]) ? trim($row[4]) : '';
        $status = isset($row[5]) ? trim($row[5]) : '';

        if ($name === '') {
            $errorCount++;
            $errors[] = "Row $i skipped: Missing client name.";
            continue;
        }

        $stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($dupeAction === 'skip') {
                $errorCount++;
                $errors[] = "Row $i skipped: Duplicate client '$name'.";
                continue;
            } elseif ($dupeAction === 'overwrite') {
                $update = $pdo->prepare("
                    UPDATE clients 
                    SET location = ?, industry = ?, url = ?, phone = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $update->execute([$location, $industry, $url, $phone, $status, $existing['id']]);
                $successCount++;
                $successLog[] = "🔄 Overwrote: $name";
                continue;
            }
        }

        try {
            $insert = $pdo->prepare("
                INSERT INTO clients (name, location, industry, url, phone, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([$name, $location, $industry, $url, $phone, $status]);
            $successCount++;
            $successLog[] = "✅ Imported: $name";
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Row $i error: " . $e->getMessage();
        }
    }

    unlink($tempFile);

} catch (Exception $e) {
    $errorCount++;
    $errors[] = "File error: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <h2>Client Import Results</h2>

    <div class="alert alert-success">✅ Imported/Updated: <?= $successCount ?> clients</div>
    <div class="alert alert-warning">⚠️ Skipped/Failed: <?= $errorCount ?> rows</div>

    <?php if (!empty($successLog)): ?>
        <div class="alert alert-info">
            <strong>Details:</strong>
            <ul>
                <?php foreach ($successLog as $success): ?>
                    <li><?= htmlspecialchars($success) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Issues:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
