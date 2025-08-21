<?php
// includes/kpi_logger.php
// Logs KPI-worthy status changes for recruiting (candidate+job association) and sales (contacts).
// Recruiting: once per (candidate+job) per bucket/event_type, canonical key uses association id.
// Sales: once per (contact entity_id) per bucket/event_type.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ---- Schema helpers ---- */
function kpi_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Look up KPI mapping for a status in kpi_status_map.
 * @param PDO    $pdo
 * @param string $status   Human-readable status label
 * @param string $module   'recruiting' | 'sales'
 * @return array|null      ['kpi_bucket' => ..., 'event_type' => ...]
 */
function kpi_lookup(PDO $pdo, string $status, string $module = 'recruiting'): ?array {
    $sql = "SELECT kpi_bucket, event_type
            FROM kpi_status_map
            WHERE module = ? AND status_name = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$module, $status]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** ---------------------- RECRUITING ---------------------- */
/**
 * Check if we already logged this bucket (and optional event_type) for this association.
 * We de-dupe on association scope, not globally by candidate/job across all associations.
 */
function kpi_already_logged_recruiting_assoc(
    PDO $pdo,
    int $association_id,
    string $bucket,
    ?string $etype = null
): bool {
    if (!kpi_table_exists($pdo, 'status_history')) return false;

    if ($etype) {
        $sql = "SELECT 1
                FROM status_history
                WHERE entity_type = 'candidate'
                  AND entity_id   = ?
                  AND kpi_bucket  = ?
                  AND event_type  = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$association_id, $bucket, $etype]);
    } else {
        $sql = "SELECT 1
                FROM status_history
                WHERE entity_type = 'candidate'
                  AND entity_id   = ?
                  AND kpi_bucket  = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$association_id, $bucket]);
    }
    return (bool)$stmt->fetchColumn();
}

/**
 * Log a recruiting status change for a given association.
 * @param PDO      $pdo
 * @param int      $association_id  Canonical entity_id for recruiting logs
 * @param int      $candidate_id    Legacy mirror
 * @param int      $job_id          Legacy mirror
 * @param string   $new_status
 * @param ?string  $old_status
 * @param ?int     $changed_by
 */
function kpi_log_status_change(
    PDO $pdo,
    int $association_id,
    int $candidate_id,
    int $job_id,
    string $new_status,
    ?string $old_status = null,
    ?int $changed_by = null
): void {
    // Skip if no change
    if ($old_status !== null && trim($old_status) === trim($new_status)) return;

    // Map status → bucket/event
    $map = kpi_lookup($pdo, $new_status, 'recruiting');
    if (!$map || $map['kpi_bucket'] === 'none') return;

    $bucket = $map['kpi_bucket'];
    $etype  = $map['event_type'] ?? null;

    // De-dupe per association
    if (kpi_already_logged_recruiting_assoc($pdo, $association_id, $bucket, $etype)) return;

    // Resolve user
    if ($changed_by === null) {
        $changed_by = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    }
    if (!$changed_by) return;

    // Insert canonical + legacy mirror
    $sql = "INSERT INTO status_history
            (entity_type, entity_id, status_name, kpi_bucket, event_type, changed_by, created_at,
             candidate_id, job_id, new_status)
            VALUES
            ('candidate', ?, ?, ?, ?, ?, NOW(),
             ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $association_id,   // entity_id (association)
        $new_status,       // status_name
        $bucket,           // kpi_bucket
        $etype,            // event_type (nullable)
        $changed_by,       // changed_by
        $candidate_id,     // legacy
        $job_id,           // legacy
        $new_status        // legacy mirror
    ]);
}

/** ---------------------- SALES ---------------------- */
function kpi_already_logged_sales(
    PDO $pdo,
    int $contact_id,
    string $bucket,
    ?string $etype = null
): bool {
    if (!kpi_table_exists($pdo, 'status_history')) return false;

    if ($etype) {
        $sql = "SELECT 1 FROM status_history
                WHERE entity_type = 'contact' AND entity_id = ? AND kpi_bucket = ? AND event_type = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contact_id, $bucket, $etype]);
    } else {
        $sql = "SELECT 1 FROM status_history
                WHERE entity_type = 'contact' AND entity_id = ? AND kpi_bucket = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$contact_id, $bucket]);
    }
    return (bool)$stmt->fetchColumn();
}

function kpi_log_sales_status_change(
    PDO $pdo,
    int $contact_id,
    string $new_status,
    ?string $old_status = null,
    ?int $changed_by = null
): void {
    if ($old_status !== null && trim($old_status) === trim($new_status)) return;

    $map = kpi_lookup($pdo, $new_status, 'sales');
    if (!$map || $map['kpi_bucket'] === 'none') return;

    $bucket = $map['kpi_bucket'];
    $etype  = $map['event_type'] ?? null;

    if (kpi_already_logged_sales($pdo, $contact_id, $bucket, $etype)) return;

    if ($changed_by === null) {
        $changed_by = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    }
    if (!$changed_by) return;

    $sql = "INSERT INTO status_history
            (entity_type, entity_id, status_name, kpi_bucket, event_type, changed_by, created_at, new_status)
            VALUES
            ('contact', ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $contact_id,
        $new_status,
        $bucket,
        $etype,
        $changed_by,
        $new_status
    ]);
}
