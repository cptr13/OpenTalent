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

        $name = trim($row[0]);
        $email = trim($row[1]);
        $phone = trim($row[2] ?? '');
        $city = trim($row[3] ?? '');
        $skills = trim($row[4] ?? '');
        $source = trim($row[5] ?? '');
        $status = trim($row[6] ?? '');

        if (empty($name) || empty($email)) {
            $errorCount++;
            $errors[] = "Row $i skipped: Missing name or email.";
            continue;
        }

        $existing = null;

        $stmt = $pdo->prepare("SELECT id FROM candidates WHERE email = ? OR (phone != '' AND phone = ?)");
        $stmt->execute([$email, $phone]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($dupeAction === 'skip') {
                $errorCount++;
                $errors[] = "Row $i skipped: Duplicate found ($email or $phone).";
                continue;
            } elseif ($dupeAction === 'overwrite') {
                $updateStmt = $pdo->prepare("
                    UPDATE candidates SET 
                        name = ?, phone = ?, city = ?, skills = ?, source = ?, status = ?, created_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$name, $phone, $city, $skills, $source, $status, $existing['id']]);
                $successCount++;
                $successLog[] = "🔄 Overwrote: $name ($email)";
                continue;
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO candidates (name, email, phone, city, skills, source, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $email, $phone, $city, $skills, $source, $status]);
            $successCount++;
            $successLog[] = "✅ Imported: $name ($email)";
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Row $i error: " . $e->getMessage();
        }
    }

    // Optional: Clean up file after import
    unlink($tempFile);

} catch (Exception $e) {
    $errorCount++;
    $errors[] = "File error: " . $e->getMessage();
}
?>

<div class="container mt-4">
    <h2>Import Results</h2>

    <div class="alert alert-success">✅ Imported/Updated: <?= $successCount ?> candidates</div>
    <div class="alert alert-warning">⚠️ Skipped or failed: <?= $errorCount ?> rows</div>

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
