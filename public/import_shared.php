<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../vendor/autoload.php';
require_once '../config/database.php';
require_once '../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$importType = $_GET['type'] ?? 'contacts';
$successCount = 0;
$errorCount = 0;
$errors = [];

$expectedColumns = [
    'contacts' => 'Full Name, Email, Phone, Job Title, Client ID',
    'clients'  => 'Name, Industry, Location, Website, Company Size, Account Manager, Status',
    'candidates' => 'Name, Email, Phone, Resume Filename, Status'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $fileType = $_FILES['excel_file']['type'];

    $allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv'
    ];

    if (!in_array($fileType, $allowedTypes)) {
        echo "<div class='alert alert-danger'>Unsupported file type.</div>";
        require_once '../includes/footer.php';
        exit;
    }

    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;

        try {
            if ($importType === 'contacts') {
                $full_name = trim($row[0]);
                $email     = trim($row[1]);
                $phone     = trim($row[2]);
                $job_title = trim($row[3]);
                $client_id = trim($row[4]);

                if (empty($full_name) || empty($email)) {
                    throw new Exception("Missing full name or email.");
                }

                $stmt = $pdo->prepare("INSERT INTO contacts (full_name, email, phone, job_title, client_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $email, $phone, $job_title, $client_id]);
            }

            elseif ($importType === 'clients') {
                $name            = trim($row[0]);
                $industry        = trim($row[1]);
                $location        = trim($row[2]);
                $website         = trim($row[3]);
                $company_size    = trim($row[4]);
                $account_manager = trim($row[5]);
                $status          = trim($row[6]);

                if (empty($name)) {
                    throw new Exception("Missing client name.");
                }

                $stmt = $pdo->prepare("INSERT INTO clients (name, industry, location, website, company_size, account_manager, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $industry, $location, $website, $company_size, $account_manager, $status]);
            }

            elseif ($importType === 'candidates') {
                $name            = trim($row[0]);
                $email           = trim($row[1]);
                $phone           = trim($row[2]);
                $resume_filename = trim($row[3]);
                $status          = trim($row[4]);

                if (empty($name) || empty($email)) {
                    throw new Exception("Missing name or email.");
                }

                $stmt = $pdo->prepare("INSERT INTO candidates (name, email, phone, resume_filename, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $resume_filename, $status]);
            }

            else {
                throw new Exception("Invalid import type.");
            }

            $successCount++;
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Row $i error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <h2>Import <?= ucfirst($importType) ?> via Excel</h2>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="input-group">
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <button type="submit" class="btn btn-primary">Upload</button>
        </div>
        <p class="text-muted mt-2">Expected columns: <code><?= $expectedColumns[$importType] ?? 'Unknown format' ?></code></p>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert alert-success">✅ Imported: <?= $successCount ?> records</div>
        <div class="alert alert-warning">⚠️ Skipped: <?= $errorCount ?> rows</div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Details:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
