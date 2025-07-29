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

        $full_name = trim($row[0] ?? '');
        $email = trim($row[1] ?? '');
        $phone = trim($row[2] ?? '');
        $title = trim($row[3] ?? '');
        $client_id = trim($row[4] ?? '');
        $client_name = trim($row[5] ?? '');

        if (empty($full_name) || empty($email)) {
            $errorCount++;
            $errors[] = "Row $i skipped: Missing full_name or email.";
            continue;
        }

        // Resolve client_id if missing
        if ($client_id === '' && $client_name !== '') {
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(name) LIKE LOWER(CONCAT('%', ?, '%')) LIMIT 1");
            $stmt->execute([$client_name]);
            $client = $stmt->fetch();
            $client_id = $client ? $client['id'] : null;

            if (!$client) {
                $errorCount++;
                $errors[] = "Row $i skipped: No matching client for name '$client_name'.";
                continue;
            }
        }

        if (empty($client_id)) {
            $errorCount++;
            $errors[] = "Row $i skipped: Missing client ID and no match by name.";
            continue;
        }

        // Duplicate detection
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE email = ? OR (phone != '' AND phone = ?)");
        $stmt->execute([$email, $phone]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($dupeAction === 'skip') {
                $errorCount++;
                $errors[] = "Row $i skipped: Duplicate contact ($email or $phone).";
                continue;
            } elseif ($dupeAction === 'overwrite') {
                $update = $pdo->prepare("
                    UPDATE contacts 
                    SET full_name = ?, phone = ?, title = ?, client_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $update->execute([$full_name, $phone, $title, $client_id, $existing['id']]);
                $successCount++;
                $successLog[] = "🔄 Overwrote: $full_name ($email)";
                continue;
            }
        }

        try {
            $insert = $pdo->prepare("
                INSERT INTO contacts (full_name, email, phone, title, client_id, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insert->execute([$full_name, $email, $phone, $title, $client_id]);
            $successCount++;
            $successLog[] = "✅ Imported: $full_name ($email)";
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
    <h2>Contact Import Results</h2>

    <div class="alert alert-success">✅ Imported/Updated: <?= $successCount ?> contacts</div>
    <div class="alert alert-warning">⚠️ Skipped/Failed: <?= $errorCount ?> rows</div>

    <?php if (!empty($successLog)): ?>
        <div class="alert alert-info">
            <strong>Details:</strong>
            <ul>
                <?php foreach ($successLog as $log): ?>
                    <li><?= htmlspecialchars($log) ?></li>
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
