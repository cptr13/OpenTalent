<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$note_id = $_GET['id'] ?? null;
$note = null;
$redirect_url = null;
$error = '';
$note_content = '';

// Valid module types for context
$valid_modules = ['candidate', 'client', 'contact', 'job'];
$module_type = '';
$module_id = null;

// Detect context from GET params (candidate_id, client_id, etc)
foreach ($valid_modules as $module) {
    $param = $module . '_id';
    if (isset($_GET[$param])) {
        $module_type = $module;
        $module_id = (int) $_GET[$param];
        $redirect_url = "view_{$module}.php?id=$module_id";
        break;
    }
}

if (!$note_id || !$module_type || !$module_id) {
    echo "<div class='alert alert-danger m-3'>Invalid request.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Attempt to load note by new schema
$stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND module_type = ? AND module_id = ?");
$stmt->execute([$note_id, $module_type, $module_id]);
$note = $stmt->fetch();

// If note not found, try legacy columns
if (!$note) {
    switch ($module_type) {
        case 'candidate':
            $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND candidate_id = ?");
            break;
        case 'client':
            $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND client_id = ?");
            break;
        case 'contact':
            $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND contact_id = ?");
            break;
        case 'job':
            $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND job_id = ?");
            break;
        default:
            echo "<div class='alert alert-danger m-3'>Invalid module type.</div>";
            require_once __DIR__ . '/../includes/footer.php';
            exit;
    }
    $stmt->execute([$note_id, $module_id]);
    $note = $stmt->fetch();
    if (!$redirect_url && $module_type && $module_id) {
        $redirect_url = "view_{$module_type}.php?id=$module_id";
    }
}

if (!$note) {
    echo "<div class='alert alert-warning m-3'>Note not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$note_content = $note['content'] ?? '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_content = trim($_POST['note'] ?? '');

    if ($new_content === '') {
        $error = "Note content cannot be empty.";
    } else {
        // Update using new schema first
        $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ? AND module_type = ? AND module_id = ?");
        $stmt->execute([$new_content, $note_id, $module_type, $module_id]);
        if ($stmt->rowCount() === 0) {
            // Fallback update with legacy fields
            switch ($module_type) {
                case 'candidate':
                    $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ? AND candidate_id = ?");
                    break;
                case 'client':
                    $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ? AND client_id = ?");
                    break;
                case 'contact':
                    $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ? AND contact_id = ?");
                    break;
                case 'job':
                    $stmt = $pdo->prepare("UPDATE notes SET content = ? WHERE id = ? AND job_id = ?");
                    break;
                default:
                    echo "<div class='alert alert-danger m-3'>Invalid module type.</div>";
                    require_once __DIR__ . '/../includes/footer.php';
                    exit;
            }
            $stmt->execute([$new_content, $note_id, $module_id]);
        }
        header("Location: $redirect_url?msg=Note+updated+successfully");
        exit;
    }
}
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">Edit Note</div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="note" class="form-label">Note</label>
                            <textarea name="note" class="form-control" rows="5" id="note"><?= htmlspecialchars($note_content) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= htmlspecialchars($redirect_url) ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

