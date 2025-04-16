<?php
require_once '../vendor/autoload.php';
require_once '../config/database.php';
require_once '../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$successCount = 0;
$errorCount = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $fileType = $_FILES['excel_file']['type'];

    // Validate file type
    $allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'text/csv',
        'application/vnd.ms-excel' // .xls
    ];

    if (!in_array($fileType, $allowedTypes)) {
        echo "<div class='alert alert-danger'>Unsupported file type.</div>";
        require_once '../includes/footer.php';
        exit;
    }

    $spreadsheet = IOFactory::load($file);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Assumes first row is header
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        $name = trim($row[0]);
        $email = trim($row[1]);
        $phone = trim($row[2]);
        $resume_filename = trim($row[3]);
        $status = trim($row[4]);

        if (empty($name) || empty($email)) {
            $errorCount++;
            $errors[] = "Row $i skipped: Missing name or email.";
            continue;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO candidates (name, email, phone, resume_filename, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $resume_filename, $status]);
            $successCount++;
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = "Row $i error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <h2>Import Candidates via Excel</h2>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="input-group">
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <button type="submit" class="btn btn-primary">Upload</button>
        </div>
        <p class="text-muted mt-2">Expected columns (in order): <code>Name, Email, Phone, Resume Filename, Status</code></p>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="alert alert-success">✅ Imported: <?= $successCount ?> candidates</div>
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