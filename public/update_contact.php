<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php'; // for getStatusList('contact')
require_once __DIR__ . '/../includes/kpi_logger.php'; // <-- added for KPI logging

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo "Invalid contact ID.";
    exit;
}

// Fetch existing row (to detect status changes & ensure exists)
$stmt = $pdo->prepare("SELECT id, contact_status FROM contacts WHERE id = ?");
$stmt->execute([$id]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
    echo "Contact not found.";
    exit;
}

$client_id       = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int)$_POST['client_id'] : null;
$first_name      = trim($_POST['first_name'] ?? '');
$last_name       = trim($_POST['last_name'] ?? '');
$title           = trim($_POST['title'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$follow_up_date  = $_POST['follow_up_date'] ?? null;
$follow_up_notes = $_POST['follow_up_notes'] ?? '';
$outreach_stage  = $_POST['outreach_stage'] ?? 1;
$last_touch_date = $_POST['last_touch_date'] ?? null;
$outreach_status = $_POST['outreach_status'] ?? 'Active';

// Validate contact_status against list
$contact_status_input = trim($_POST['contact_status'] ?? '');
$contact_status = null;

try {
    $statusList = getStatusList('contact'); // ['Category' => ['Sub1', ...], ...]
    $valid = false;
    foreach ($statusList as $cat => $subs) {
        if (in_array($contact_status_input, $subs, true)) {
            $valid = true;
            break;
        }
    }
    $contact_status = $valid ? $contact_status_input : $existing['contact_status'];
} catch (Throwable $e) {
    // If status list fails, keep current status
    $contact_status = $existing['contact_status'];
}

try {
    $pdo->beginTransaction();

    // Update core fields
    $sql = "
        UPDATE contacts
           SET client_id       = :client_id,
               first_name      = :first_name,
               last_name       = :last_name,
               title           = :title,
               email           = :email,
               phone           = :phone,
               contact_status  = :contact_status,
               follow_up_date  = :follow_up_date,
               follow_up_notes = :follow_up_notes,
               outreach_stage  = :outreach_stage,
               last_touch_date = :last_touch_date,
               outreach_status = :outreach_status,
               updated_at      = NOW()
         WHERE id = :id
    ";

    $upd = $pdo->prepare($sql);
    $upd->execute([
        ':client_id'       => $client_id,
        ':first_name'      => $first_name,
        ':last_name'       => $last_name,
        ':title'           => $title,
        ':email'           => $email,
        ':phone'           => $phone,
        ':contact_status'  => $contact_status,
        ':follow_up_date'  => $follow_up_date ?: null,
        ':follow_up_notes' => $follow_up_notes,
        ':outreach_stage'  => $outreach_stage,
        ':last_touch_date' => $last_touch_date ?: null,
        ':outreach_status' => $outreach_status,
        ':id'              => $id,
    ]);

    // Log a note + KPI if status changed
    if ($existing['contact_status'] !== $contact_status) {
        // Note
        $noteStmt = $pdo->prepare("
            INSERT INTO notes (module_type, module_id, content, created_at)
            VALUES ('contact', :module_id, :content, NOW())
        ");
        $content = "Status changed: ".($existing['contact_status'] ?: '—')." → ".($contact_status ?: '—');
        $noteStmt->execute([
            ':module_id' => $id,
            ':content'   => $content,
        ]);

        // KPI (include user id like other paths)
        $user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
        $user_id = (is_numeric($user_id) && (int)$user_id > 0) ? (int)$user_id : 1;
        try {
            kpi_log_sales_status_change($pdo, $id, $contact_status, $existing['contact_status'], $user_id);
        } catch (Throwable $e) {
            // Optional: error_log('KPI sales log failed: ' . $e->getMessage());
        }
    }

    $pdo->commit();

    header("Location: view_contact.php?id=" . $id . "&msg=" . urlencode("Contact updated."));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error updating contact: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
