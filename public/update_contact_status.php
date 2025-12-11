<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php';       // getStatusList('contact')
require_once __DIR__ . '/../includes/kpi_logger.php'; // unified KPI logger

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contacts.php?error=' . urlencode('Invalid request method.'));
    exit;
}

$contact_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$new_status = trim($_POST['contact_status'] ?? '');
$note_input = trim($_POST['note'] ?? '');
$return_to  = 'view_contact.php?id=' . $contact_id;

if ($contact_id <= 0 || $new_status === '') {
    header('Location: ' . $return_to . '&error=' . urlencode('Missing contact or status.'));
    exit;
}

// Validate status against configured contact list
$valid = false;
try {
    $statusList = getStatusList('contact'); // ['Category' => ['Sub1', ...], ...]
    foreach ($statusList as $category => $subs) {
        if (in_array($new_status, $subs, true)) {
            $valid = true;
            break;
        }
    }
} catch (Throwable $e) {
    $valid = false;
}

if (!$valid) {
    header('Location: ' . $return_to . '&error=' . urlencode('Invalid contact status.'));
    exit;
}

try {
    $pdo->beginTransaction();

    // Get current status from DB (trust DB over any posted "old_status")
    $cur = $pdo->prepare("SELECT contact_status FROM contacts WHERE id = :id FOR UPDATE");
    $cur->execute([':id' => $contact_id]);
    $row = $cur->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        header('Location: ' . $return_to . '&error=' . urlencode('Contact not found.'));
        exit;
    }

    $old_status = (string)($row['contact_status'] ?? '');

    // Always update the status (even if unchanged) so the DB matches what you just selected
    $upd = $pdo->prepare("UPDATE contacts SET contact_status = :status WHERE id = :id");
    $upd->execute([
        ':status' => $new_status,
        ':id'     => $contact_id,
    ]);

    // Auto note to record the touch
    // - If status changed: "Status changed: Old → New"
    // - If same:          "Status logged: New"
    $autoNoteText = ($old_status !== $new_status)
        ? "Status changed: " . ($old_status !== '' ? $old_status : '—') . " → {$new_status}"
        : "Status logged: {$new_status}";

    $auto = $pdo->prepare("
        INSERT INTO notes (module_type, module_id, content, created_at)
        VALUES ('contact', :module_id, :content, NOW())
    ");
    $auto->execute([
        ':module_id' => $contact_id,
        ':content'   => $autoNoteText,
    ]);

    // KPI logging (sales-side) — now logs EVERY time you hit Update Status
    $user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    $user_id = (is_numeric($user_id) && (int)$user_id > 0) ? (int)$user_id : 1;

    try {
        kpi_log_sales_status_change($pdo, $contact_id, $new_status, $old_status, $user_id);
    } catch (Throwable $e) {
        // Optional: error_log('KPI sales log failed: ' . $e->getMessage());
    }

    // Optional manual note (always attach to CONTACT)
    if ($note_input !== '') {
        $noteStmt = $pdo->prepare("
            INSERT INTO notes (module_type, module_id, content, created_at)
            VALUES ('contact', :module_id, :content, NOW())
        ");
        $noteStmt->execute([
            ':module_id' => $contact_id,
            ':content'   => $note_input,
        ]);
    }

    $pdo->commit();
    header('Location: ' . $return_to . '&msg=' . urlencode('Contact status updated.'));
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . $return_to . '&error=' . urlencode('Failed to update status: ' . $e->getMessage()));
    exit;
}
