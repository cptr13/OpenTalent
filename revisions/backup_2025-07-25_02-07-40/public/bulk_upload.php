<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

$maxZipSize = 20 * 1024 * 1024;
$allowedExtensions = ['pdf', 'docx', 'doc', 'txt', 'rtf', 'odt'];
$uploadDir = __DIR__ . '/../uploads/resumes/';
$log = [];
$imported = 0;
$skipped = 0;
$_SESSION['review_queue'] = [];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

function extractText($filePath, $extension) {
    if (in_array($extension, ['txt', 'rtf'])) {
        return file_get_contents($filePath);
    } elseif ($extension === 'pdf') {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (Exception $e) {
            error_log("PDF parse failed: " . $e->getMessage());
            return '';
        }
    } elseif (in_array($extension, ['docx', 'doc', 'odt'])) {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        return $text;
    }
    return '';
}

function parseResumeText($rawText) {
    $parsed = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'linkedin' => '',
    ];

    $lines = preg_split("/\r\n|\n|\r/", $rawText);

    if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $rawText, $m)) {
        $parsed['email'] = $m[0];
    }

    if (
        preg_match('/\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $rawText, $m) ||
        preg_match('/\b\d{10}\b/', $rawText, $m)
    ) {
        $parsed['phone'] = trim($m[0]);
    }

    if (preg_match('/(https?:\/\/)?(www\.)?linkedin\.com\/[^\s)]+/i', $rawText, $m)) {
        $parsed['linkedin'] = $m[0];
    }

    foreach (array_slice($lines, 0, 5) as $line) {
        $line = trim($line);
        if (preg_match('/^(.+?),\s*([A-Z]{2})(?:\s+(\d{5})(-\d{4})?)?$/i', $line, $m)) {
            $parsed['city'] = ucwords(strtolower($m[1]));
            $parsed['state'] = strtoupper($m[2]);
            if (!empty($m[3])) $parsed['zip'] = $m[3];
        }

        if (
            strlen($line) > 2 &&
            !preg_match('/\d/', $line) &&
            strpos($line, '@') === false &&
            !preg_match('/https?:\/\//i', $line) &&
            substr_count($line, ' ') <= 4 &&
            (
                preg_match('/^[A-Z][a-zA-Z\'\.\-]+(\s[A-Z][a-zA-Z\'\.\-]+)+$/', $line) ||
                preg_match('/^[A-Z]{2,}(\s[A-Z]{2,})+$/', $line)
            )
        ) {
            $parts = explode(' ', ucwords(strtolower($line)), 2);
            $parsed['first_name'] = $parts[0] ?? '';
            $parsed['last_name'] = $parts[1] ?? '';
        }
    }

    return $parsed;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    $autoCreate = ($_POST['mode'] ?? 'auto') === 'auto';
    $defaultStatus = $_POST['default_status'] ?? 'New';
    $defaultOwner = $_POST['default_owner'] ?? ($_SESSION['user']['full_name'] ?? 'System');

    $file = $_FILES['zip_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $log[] = "❌ Error uploading ZIP file.";
    } elseif ($file['size'] > $maxZipSize) {
        $log[] = "❌ ZIP file exceeds maximum size.";
    } else {
        $zipPath = $uploadDir . 'bulk_' . time() . '.zip';
        move_uploaded_file($file['tmp_name'], $zipPath);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            $extractPath = $uploadDir . 'tmp_' . time() . '/';
            mkdir($extractPath, 0777, true);
            $zip->extractTo($extractPath);
            $zip->close();

            $files = scandir($extractPath);
            foreach ($files as $fileName) {
                if (!in_array(pathinfo($fileName, PATHINFO_EXTENSION), $allowedExtensions)) continue;

                $filePath = $extractPath . $fileName;
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $rawText = extractText($filePath, $ext);

                if (!$rawText) {
                    $skipped++;
                    $log[] = "⚠️ Skipped '$fileName' (unreadable or corrupt file)";
                    continue;
                }

                $rawTextUtf8 = mb_convert_encoding($rawText, 'UTF-8', 'auto');
                $parsed = parseResumeText($rawTextUtf8);

                if (empty($parsed['email'])) {
                    $skipped++;
                    $log[] = "⚠️ Skipped '$fileName' (no email found)";
                    continue;
                }

                $stmt = $pdo->prepare("SELECT id FROM candidates WHERE email = ? OR phone = ?");
                $stmt->execute([$parsed['email'], $parsed['phone']]);
                if ($stmt->fetch()) {
                    $skipped++;
                    $log[] = "⚠️ Skipped '$fileName' (duplicate email or phone)";
                    continue;
                }

                $safeName = strtolower(preg_replace('/[^a-z0-9]/i', '_', $parsed['first_name'] . '_' . $parsed['last_name']));
                $resumeFilename = 'resume_' . $safeName . '_' . time() . '.txt';
                file_put_contents($uploadDir . $resumeFilename, $rawTextUtf8);

                if ($autoCreate) {
                    $stmt = $pdo->prepare("
                        INSERT INTO candidates (first_name, last_name, email, phone, city, state, zip, linkedin, resume_text, status, owner, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $parsed['first_name'],
                        $parsed['last_name'],
                        $parsed['email'],
                        $parsed['phone'],
                        $parsed['city'],
                        $parsed['state'],
                        $parsed['zip'],
                        $parsed['linkedin'],
                        $rawTextUtf8,
                        $defaultStatus,
                        $defaultOwner
                    ]);

                    $imported++;
                    $log[] = "✅ Imported '$fileName' as " . htmlspecialchars($parsed['first_name'] . ' ' . $parsed['last_name']);
                } else {
                    $_SESSION['review_queue'][] = [
                        'file_name' => $fileName,
                        'first_name' => $parsed['first_name'],
                        'last_name' => $parsed['last_name'],
                        'email' => $parsed['email'],
                        'phone' => $parsed['phone'],
                        'city' => $parsed['city'],
                        'state' => $parsed['state'],
                        'zip' => $parsed['zip'],
                        'linkedin' => $parsed['linkedin'],
                        'resume_text' => $rawTextUtf8
                    ];
                    $skipped++;
                    $log[] = "ℹ️ Queued '$fileName' for manual review";
                }
            }

            array_map('unlink', glob("$extractPath/*.*"));
            rmdir($extractPath);

            if (!$autoCreate && !empty($_SESSION['review_queue'])) {
                header("Location: review_queue.php");
                exit;
            }
        } else {
            $log[] = "❌ Failed to open ZIP file.";
        }
    }
}
?>

<div class="container mt-5">
    <h2>Bulk Upload Resumes</h2>
    <p>Upload a ZIP file containing resumes. Each will be parsed and either auto-imported or queued for review.</p>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">ZIP File</label>
            <input type="file" name="zip_file" class="form-control" required>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Import Mode</label>
                <select name="mode" class="form-select">
                    <option value="auto" selected>Auto-Create Candidates Immediately</option>
                    <option value="review">Parse & Review Before Creating</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Default Status</label>
                <select name="default_status" class="form-select">
                    <option>New</option>
                    <option>Active</option>
                    <option>Placed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Default Owner</label>
                <input type="text" name="default_owner" class="form-control" value="<?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'System') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Upload & Process</button>
    </form>

    <?php if (!empty($log)): ?>
        <hr>
        <h4>Upload Summary</h4>
        <ul>
            <?php foreach ($log as $entry): ?>
                <li><?= $entry ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="alert alert-info">✔️ Imported: <?= $imported ?> | ❌ Skipped: <?= $skipped ?></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

