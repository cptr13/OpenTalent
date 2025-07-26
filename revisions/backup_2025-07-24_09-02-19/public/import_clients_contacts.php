<?php

session_start();

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$success = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Fix deprecated strtolower issue by filtering null values
    $headers = array_map(function ($h) {
        return $h !== null ? strtolower($h) : '';
    }, $rows[0]);

    for ($i = 1; $i < count($rows); $i++) {
        $row = array_combine($headers, $rows[$i]);

        $first_name = trim($row['first name'] ?? '');
        $last_name = trim($row['last name'] ?? '');
        $title = trim($row['title'] ?? '');
        $company = trim($row['company name'] ?? '');
        $company_phone = trim($row['company phone number'] ?? '');
        $company_url = trim($row['company url'] ?? '');
        $linkedin = trim($row['linkedin url'] ?? '');

        try {
            if ($company === '') {
                $errors[] = "Row $i error: Missing company name.";
                continue;
            }

            // Check or create client
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ?");
            $stmt->execute([$company]);
            $client = $stmt->fetch();

            if (!$client) {
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, website, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$company, $company_phone, $company_url]);
                $client_id = $pdo->lastInsertId();
                $success[] = "Client created: $company";
            } else {
                $client_id = $client['id'];
                $success[] = "Client exists: $company";
            }

            // Insert contact only if client_id is valid
            if ($client_id && ($first_name || $last_name || $linkedin)) {
                $full_name = trim("$first_name $last_name");
                $contact_owner = $_SESSION['user']['full_name'] ?? 'System';

                $stmt = $pdo->prepare("
                    INSERT INTO contacts (
                        full_name, title, linkedin, company, contact_owner, phone, client_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $full_name,
                    $title,
                    $linkedin,
                    $company,
                    $contact_owner,
                    $company_phone,
                    $client_id
                ]);

                $success[] = "Contact added: $full_name";
            } elseif (!$client_id) {
                $errors[] = "Row $i error: Could not determine client ID for $company.";
            }
        } catch (Exception $e) {
            $errors[] = "Row $i error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <h2>Import Clients & Contacts</h2>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="input-group">
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <button type="submit" class="btn btn-success">Upload and Import</button>
        </div>
    </form>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>Success:</strong>
            <ul><?php foreach ($success as $msg): ?><li><?= htmlspecialchars($msg) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <strong>Errors:</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
