<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/header.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$previewData = [];
$error = '';
$tempPath = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $fileType = $_FILES['file']['type'];

    $allowedTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'application/vnd.ms-excel'
    ];

    if (!in_array($fileType, $allowedTypes)) {
        $error = "Unsupported file type.";
    } else {
        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $tempPath = __DIR__ . '/../uploads/clients_preview_' . time() . '.xlsx';
            move_uploaded_file($_FILES['file']['tmp_name'], $tempPath);

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) continue;

                $name = isset($row[0]) ? trim($row[0]) : '';
                $location = isset($row[1]) ? trim($row[1]) : '';
                $industry = isset($row[2]) ? trim($row[2]) : '';
                $url = isset($row[3]) ? trim($row[3]) : '';
                $phone = isset($row[4]) ? trim($row[4]) : '';
                $status = isset($row[5]) ? trim($row[5]) : '';

                $isDuplicate = false;
                $note = '';

                if ($name !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM clients WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))");
                    $stmt->execute([$name]);
                    $isDuplicate = $stmt->fetch() ? true : false;
                } else {
                    $note = "❌ Missing client name";
                }

                $previewData[] = [
                    'row' => [$name, $location, $industry, $url, $phone, $status],
                    'duplicate' => $isDuplicate,
                    'note' => $note
                ];
            }
        } catch (Exception $e) {
            $error = "Error reading file: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <h2>Import Clients (Preview)</h2>

    <form method="POST" enctype="multipart/form-data" class="mb-4">
        <div class="mb-3">
            <label for="file" class="form-label">Upload Excel or CSV File</label>
            <input type="file" name="file" id="file" class="form-control" accept=".csv,.xls,.xlsx" required>
        </div>
        <button type="submit" class="btn btn-primary">Upload & Preview</button>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($previewData)): ?>
        <form method="POST" action="confirm_import_clients.php">
            <input type="hidden" name="temp_file" value="<?= htmlspecialchars($tempPath) ?>">

            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="dupe_action" id="dupe_skip" value="skip" checked>
                <label class="form-check-label" for="dupe_skip">On duplicate: Skip</label>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="radio" name="dupe_action" id="dupe_overwrite" value="overwrite">
                <label class="form-check-label" for="dupe_overwrite">On duplicate: Overwrite</label>
            </div>

            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Industry</th>
                        <th>URL</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewData as $index => $data): 
                        $row = $data['row'];
                        $isDuplicate = $data['duplicate'];
                        $note = $data['note'];
                        ?>
                        <tr class="<?= ($note || $isDuplicate) ? 'table-danger' : 'table-success' ?>">
                            <td><?= $index + 1 ?></td>
                            <?php foreach ($row as $value): ?>
                                <td><?= htmlspecialchars($value ?? '') ?></td>
                            <?php endforeach; ?>
                            <td><?= htmlspecialchars($note ?: ($isDuplicate ? '❌ Duplicate' : '✅ New')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-success">Confirm Import</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
