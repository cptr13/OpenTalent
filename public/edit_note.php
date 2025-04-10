<?php
require_once __DIR__ . '/../config/database.php';

$note_id = $_GET['id'] ?? null;
$note = null;
$redirect_url = null;
$error = '';
$note_content = '';

// Determine the context (candidate, client, contact, or job)
$context = '';
$context_id = null;

if (isset($_GET['candidate_id'])) {
    $context = 'candidate';
    $context_id = (int) $_GET['candidate_id'];
    $redirect_url = "view_candidate.php?id=$context_id";
} elseif (isset($_GET['client_id'])) {
    $context = 'client';
    $context_id = (int) $_GET['client_id'];
    $redirect_url = "view_client.php?id=$context_id";
} elseif (isset($_GET['contact_id'])) {
    $context = 'contact';
    $context_id = (int) $_GET['contact_id'];
    $redirect_url = "view_contact.php?id=$context_id";
} elseif (isset($_GET['job_id'])) {
    $context = 'job';
    $context_id = (int) $_GET['job_id'];
    $redirect_url = "view_job.php?id=$context_id";
}

if (!$note_id || !$context || !$context_id) {
    die("Invalid request.");
}

// Load existing note
$stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND {$context}_id = ?");
$stmt->execute([$note_id, $context_id]);
$note = $stmt->fetch();

if (!$note) {
    die("Note not found.");
}

$note_content = $note['content'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_content = trim($_POST['note'] ?? '');

    if ($new_content === '') {
        $error = "Note content cannot be empty.";
    } else {
        $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ? AND {$context}_id = ?");
        $stmt->execute([$new_content, $note_id, $context_id]);
        header("Location: $redirect_url");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Note</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card">
        <div class="card-header">Edit Note</div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label for="note">Note:</label>
                    <textarea name="note" class="form-control" rows="5"><?= htmlspecialchars($note_content) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= $redirect_url ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
