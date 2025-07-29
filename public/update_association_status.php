<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
    exit;
}

$association_id = $_POST['association_id'] ?? null;
$status = trim($_POST['new_status'] ?? '');
$content = trim($_POST['note'] ?? '');  // renamed local variable to be clear
$candidate_id = $_POST['candidate_id'] ?? null;

// Optional redirect override
$return = $_POST['return'] ?? 'candidate';
$id_return = $_POST['id_return'] ?? $candidate_id;

if (!$association_id || !$status || !$candidate_id) {
    echo "<div class='alert alert-danger'>Missing required fields.</div>";
    exit;
}

try {
    // Update association status
    $stmt = $pdo->prepare("UPDATE associations SET status = ? WHERE id = ?");
    $stmt->execute([$status, $association_id]);

  // Save optional note (to notes table)
if (!empty($content)) {
    // 🔍 DEBUG: Check that we're hitting the correct code path
    echo "Debug: saving note for association $association_id with content: " . htmlspecialchars($content);
    exit;

    $stmt = $pdo->prepare("INSERT INTO notes (module_type, module_id, content, created_at)
                           VALUES (:module_type, :module_id, :content, NOW())");
    $stmt->execute([
        'module_type' => 'association',
        'module_id' => $association_id,
        'content' => $content
    ]);
}



    // Redirect back to appropriate view
    if ($return === 'job' && is_numeric($id_return)) {
        header("Location: view_job.php?id=" . urlencode($id_return) . "&msg=Status+updated");
    } else {
        header("Location: view_candidate.php?id=" . urlencode($candidate_id) . "&msg=Status+updated");
    }
    exit;
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error updating status: " . htmlspecialchars($e->getMessage()) . "</div>";
}

