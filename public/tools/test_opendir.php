<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$path = '/var/www/html/OT2/uploads';

echo "<p>Checking path: <code>$path</code></p>";

if (!is_dir($path)) {
    echo "<p style='color:red;'>❌ $path is NOT a directory.</p>";
} else {
    echo "<p style='color:green;'>✅ $path exists and is a directory.</p>";

    $handle = @opendir($path);
    if (!$handle) {
        echo "<p style='color:red;'>❌ opendir() FAILED. Permissions or ownership issue.</p>";
    } else {
        echo "<p style='color:green;'>✅ opendir() SUCCESSFUL. Contents:</p><ul>";
        while (($file = readdir($handle)) !== false) {
            echo "<li>$file</li>";
        }
        echo "</ul>";
        closedir($handle);
    }
}

