<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$job_id = $_GET['id'] ?? null;

if (!$job_id) {
    echo "<div style='padding:20px; font-family:sans-serif;'>Invalid job ID.</div>";
    exit;
}

$stmt = $pdo->prepare("SELECT title, description FROM jobs WHERE id = ?");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    echo "<div style='padding:20px; font-family:sans-serif;'>Job not found.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($job['title']) ?> — Full Description</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2><?= htmlspecialchars($job['title']) ?></h2>
        <hr>
        <div style="white-space: pre-wrap; font-size: 1rem;">
            <?= nl2br(htmlspecialchars($job['description'])) ?>
        </div>
    </div>
</body>
</html>

