<?php
$tmpDir = sys_get_temp_dir();
$testFile = $tmpDir . '/opentalent_test_' . time() . '.txt';

if (file_put_contents($testFile, "Test file written at " . date('Y-m-d H:i:s'))) {
    echo "✅ Successfully wrote to temp directory: $testFile";
} else {
    echo "❌ Failed to write to temp directory: $tmpDir";
}
?>

