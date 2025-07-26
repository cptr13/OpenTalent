<?php
session_start();
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Define max file size (5MB)
$maxFileSize = 5 * 1024 * 1024;

// Define allowed extensions
$allowedExtensions = ['pdf', 'docx', 'doc', 'txt', 'rtf', 'odt'];

// Setup upload path
$uploadDir = __DIR__ . '/../uploads/resumes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Helper: Extract text based on extension
function extractText($filePath, $extension) {
    if ($extension === 'txt' || $extension === 'rtf') {
        return file_get_contents($filePath);
    } elseif ($extension === 'pdf') {
        require_once __DIR__ . '/../vendor/autoload.php';
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    } elseif (in_array($extension, ['docx', 'doc', 'odt'])) {
        require_once __DIR__ . '/../vendor/autoload.php';
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            $elements = $section->getElements();
            foreach ($elements as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }
        return $text;
    }
    return '';
}

// Handle form submission
$rawText = '';
$storedFilename = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    $file = $_FILES['resume'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading file.';
    } elseif ($file['size'] > $maxFileSize) {
        $error = 'File exceeds maximum size (5MB).';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            $error = 'Unsupported file type.';
        } else {
            $storedFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($file['name']));
            $destination = $uploadDir . $storedFilename;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $rawText = extractText($destination, $ext);
            } else {
                $error = 'Failed to save uploaded file.';
            }
        }
    }
}
?>

<div class="container mt-4">
    <h2>Upload Resume</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="resume" class="form-label">Select Resume File</label>
            <input type="file" name="resume" class="form-control" required>
            <div class="form-text">Allowed: PDF, DOCX, DOC, TXT, RTF, ODT. Max: 5MB.</div>
        </div>
        <button type="submit" class="btn btn-primary">Upload & Extract</button>
    </form>

    <?php if ($rawText): ?>
        <hr>
        <h4>Extracted Resume Text</h4>
        <form method="POST" action="review_parsed_resume.php">
            <input type="hidden" name="resume_text" value="<?= htmlspecialchars($rawText) ?>">
            <input type="hidden" name="source_file" value="<?= htmlspecialchars($storedFilename) ?>">
            <div class="mb-3">
                <textarea rows="20" class="form-control" readonly><?= htmlspecialchars($rawText) ?></textarea>
            </div>
            <button type="submit" class="btn btn-success">Continue to Field Review</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

