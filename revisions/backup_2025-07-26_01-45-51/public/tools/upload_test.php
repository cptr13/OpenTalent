<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['testfile']) && is_uploaded_file($_FILES['testfile']['tmp_name'])) {
        $filename = $_FILES['testfile']['name'];
        $temp = $_FILES['testfile']['tmp_name'];
        echo "<p style='color:green;'>✅ File '$filename' uploaded successfully to temp as '$temp'</p>";
    } else {
        echo "<p style='color:red;'>❌ File upload failed or missing in \$_FILES</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Upload Test</title></head>
<body>
<h2>📤 Upload Test</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="testfile" required>
    <button type="submit">Upload</button>
</form>
</body>
</html>

