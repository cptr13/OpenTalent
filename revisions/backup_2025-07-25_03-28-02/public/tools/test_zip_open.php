<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zipFile = $_FILES['backup']['tmp_name'] ?? null;

    if (!$zipFile || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
        echo "Upload failed or no file submitted.";
        exit;
    }

    $info = [
        'zipFile' => $zipFile,
        'exists' => file_exists($zipFile),
        'size' => filesize($zipFile),
        'mime' => mime_content_type($zipFile),
        'is_uploaded' => is_uploaded_file($zipFile),
    ];

    echo "<pre>";
    print_r($info);

    $zip = new ZipArchive();
    $res = $zip->open($zipFile);
    if ($res === true) {
        echo "✅ ZIP opened successfully!\n";
        for ($i = 0; $i < $zip->numFiles; $i++) {
            echo "- " . $zip->getNameIndex($i) . "\n";
        }
        $zip->close();
    } else {
        echo "❌ Failed to open ZIP. Error code: $res";
    }
    echo "</pre>";
    exit;
}
?>

<form method="post" enctype="multipart/form-data">
    <label>Select ZIP file:</label><br>
    <input type="file" name="backup" required>
    <br><br>
    <button type="submit">Upload & Test</button>
</form>

