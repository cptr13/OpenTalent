<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$previewData = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    $fileType = $_FILES['excel_file']['type'];

    $allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'application/vnd.ms-excel'
    ];

    if (!in_array($fileType, $allowedTypes)) {
        echo "<div class='alert alert-danger'>Unsupported file type.</div>";
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $tempPath = __DIR__ . '/../uploads/candidates_preview_' . time() . '.xlsx';
        move_uploaded_file($_FILES['excel_file']['tmp_name'], $tempPath);

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty(array_filter($row))) continue;

            $name = isset($row[0]) ? trim($row[0]) : '';
            $email = isset($row[1]) ? trim($row[1]) : '';
            $phone = isset($row[2]) ? trim($row[2]) : '';
            $city = isset($row[3]) ? trim($row[3]) : '';
            $skills = isset($row[4]) ? trim($row[4]) : '';
            $source = isset($row[5]) ? trim($row[5]) : '';
            $status = isset($row[6]) ? trim($row[6]) : '';

            $isDuplicate = false;

            if (!empty($email) || !empty($phone)) {
                $stmt = $pdo->prepare("SELECT id FROM candidates WHERE email = ? OR (phone != '' AND phone = ?)");
                $stmt->execute([$email, $phone]);
                $isDuplicate = $stmt->fetch() ? true : false;
            }

            $previewData[] = [
                'row' => [$name, $email, $phone, $city, $skills, $source, $status],
                'duplicate' => $isDuplicate
            ];
        }
    } catch (Exception $e) {
        $errors[] = "Error reading file: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2>Candidate Import Preview</h2>

    <form method="POST" enctype="multipart/form-data" class="mb-4" action="">
        <div class="input-group mb-3">
            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            <button type="submit" class="btn btn-primary">Upload & Preview</button>
        </div>
    </form>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($previewData)): ?>
        <form method="POST" action="confirm_import.php">
            <input type="hidden" name="temp_file" value="<?= htmlspecialchars($tempPath) ?>">

            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="dupe_action" id="dupe_skip" value="skip" checked>
                <label class="form-check-label" for="dupe_skip">On duplicate: Skip</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="dupe_action" id="dupe_overwrite" value="overwrite">
                <label class="form-check-label" for="dupe_overwrite">On duplicate: Overwrite</label>
            </div>

            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Skills</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewData as $index => $data): 
                        $row = $data['row'];
                        $isDuplicate = $data['duplicate'];
                        ?>
                        <tr class="<?= $isDuplicate ? 'table-danger' : 'table-success' ?>">
                            <td><?= $index + 1 ?></td>
                            <?php for ($j = 0; $j < 7; $j++): ?>
                                <td><?= htmlspecialchars($row[$j] ?? '') ?></td>
                            <?php endfor; ?>
                            <td><?= $isDuplicate ? '❌ Duplicate' : '✅ New' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-success">Confirm Import</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
