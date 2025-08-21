<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php'; // for getStatusList('contact')
require_once __DIR__ . '/../includes/kpi_logger.php'; // <-- needed for KPI logging

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $title           = trim($_POST['title'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $client_id       = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int)$_POST['client_id'] : null;
    $follow_up_date  = $_POST['follow_up_date'] ?? null;
    $follow_up_notes = $_POST['follow_up_notes'] ?? '';
    $outreach_stage  = $_POST['outreach_stage'] ?? 1;
    $last_touch_date = $_POST['last_touch_date'] ?? null;
    $outreach_status = $_POST['outreach_status'] ?? 'Active';

    // NEW: Contact Status (validate against contact list)
    $contact_status_input = trim($_POST['contact_status'] ?? 'New Contact');
    $contact_status = null;

    try {
        $statusList = getStatusList('contact'); // ['Category' => ['Sub1', ...], ...]
        $flat = [];
        foreach ($statusList as $cat => $subs) {
            foreach ($subs as $s) { $flat[$s] = true; }
        }
        if (isset($flat[$contact_status_input])) {
            $contact_status = $contact_status_input;
        } else {
            // Fallback to a sane default if provided is invalid
            $contact_status = isset($flat['New Contact']) ? 'New Contact' : null;
        }
    } catch (Throwable $e) {
        // If status config fails for any reason, allow insert without a status
        $contact_status = null;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (
                first_name,
                last_name,
                title,
                email,
                phone,
                client_id,
                contact_status,
                follow_up_date,
                follow_up_notes,
                outreach_stage,
                last_touch_date,
                outreach_status,
                created_at
            ) VALUES (
                :first_name,
                :last_name,
                :title,
                :email,
                :phone,
                :client_id,
                :contact_status,
                :follow_up_date,
                :follow_up_notes,
                :outreach_stage,
                :last_touch_date,
                :outreach_status,
                NOW()
            )
        ");

        $stmt->execute([
            ':first_name'      => $first_name,
            ':last_name'       => $last_name,
            ':title'           => $title,
            ':email'           => $email,
            ':phone'           => $phone,
            ':client_id'       => $client_id,
            ':contact_status'  => $contact_status,
            ':follow_up_date'  => $follow_up_date ?: null,
            ':follow_up_notes' => $follow_up_notes,
            ':outreach_stage'  => $outreach_stage,
            ':last_touch_date' => $last_touch_date ?: null,
            ':outreach_status' => $outreach_status
        ]);

        // NEW: Log a sales KPI event for the initial contact status, if present
        $new_contact_id = (int)$pdo->lastInsertId();
        if ($new_contact_id > 0 && $contact_status !== null && $contact_status !== '') {
            // Uses module 'sales' via kpi_log_sales_status_change() inside includes/kpi_logger.php
            // It will:
            //  - look up (module='sales', status_name=$contact_status) in kpi_status_map
            //  - map to your finalized sales buckets (contact_attempts, conversations, meetings, agreements_signed, job_orders_received)
            //  - insert into status_history(contact_id, new_status, kpi_bucket, event_type, changed_by)
            try {
                // changed_by will be pulled from session inside the logger if null
                kpi_log_sales_status_change($pdo, $new_contact_id, $contact_status, null, $_SESSION['user_id'] ?? null);
            } catch (Throwable $e) {
                // Fail-safe: don’t block contact creation if logging fails
                // You can optionally error_log($e->getMessage());
            }
        }

        // Redirect back to appropriate view
        if ($client_id) {
            header("Location: view_client.php?id=" . $client_id);
        } else {
            header("Location: contacts.php?success=1");
        }
        exit;

    } catch (PDOException $e) {
        echo "Error saving contact: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
} else {
    echo "Invalid request method.";
}
