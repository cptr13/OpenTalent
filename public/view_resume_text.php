<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    echo "Invalid candidate ID.";
    exit;
}

$stmt = $pdo->prepare("SELECT resume_text FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate || empty($candidate['resume_text'])) {
    echo "Resume text not available.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resume Text</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            background-color: #fff;
            font-family: monospace;
            overflow: hidden;
        }

        .content {
            height: 100%;
            width: 100%;
            box-sizing: border-box;
            padding: 40px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="content">
        <?= htmlspecialchars($candidate['resume_text']) ?>
    </div>
</body>
</html>

