<?php
// includes/kpi_logger.php
// Logs KPI-worthy status changes once per candidate+job.
// De-dupe rule: if event_type exists, de-dupe by (candidate, job, bucket, event_type);
// otherwise de-dupe by (candidate, job, bucket) only.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Look up KPI mapping for a status.
 * Returns ['kpi_bucket' => ..., 'event_type' => ...] or null if not mapped.
 */
function kpi_lookup(PDO $pdo, string $status): ?array {
    $sql = "SELECT kpi_bucket, event_type
            FROM kpi_status_map
            WHERE module = 'recruiting' AND status_name = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Has this candidate+job already been credited?
 * - If $event_type is provided, check exact event de-dupe.
 * - Else de-dupe at the bucket level.
 */
function kpi_already_logged(
    PDO $pdo,
    int $candidate_id,
    int $job_id,
    string $kpi_bucket,
    ?string $event_type = null
): bool {
    if ($event_type !== null && $event_type !== '') {
        $sql = "SELECT 1
                FROM status_history
                WHERE candidate_id = ? AND job_id = ? AND kpi_bucket = ? AND event_type = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$candidate_id, $job_id, $kpi_bucket, $event_type]);
    } else {
        $sql = "SELECT 1
                FROM status_history
                WHERE candidate_id = ? AND job_id = ? AND kpi_bucket = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$candidate_id, $job_id, $kpi_bucket]);
    }
    return (bool)$stmt->fetchColumn();
}

/**
 * Log a KPI event if appropriate.
 * - Only logs if status maps to a KPI bucket (not 'none').
 * - De-dupe by exact event when event_type exists; otherwise by bucket.
 * - Skips if old_status === new_status (no-op updates).
 */
function kpi_log_status_change(
    PDO $pdo,
    int $candidate_id,
    int $job_id,
    string $new_status,
    ?string $old_status = null,
    ?int $changed_by = null
): void {
    // No change? bail.
    if ($old_status !== null && trim($old_status) === trim($new_status)) {
        return;
    }

    $map = kpi_lookup($pdo, $new_status);
    if (!$map) {
        return; // unknown status label
    }

    $bucket = $map['kpi_bucket'];
    $etype  = $map['event_type'] ?? null;

    if ($bucket === 'none') {
        return; // not KPI-worthy
    }

    // De-dupe (by event if available; else by bucket)
    if (kpi_already_logged($pdo, $candidate_id, $job_id, $bucket, $etype)) {
        return;
    }

    // Fallback for changed_by if not provided
    if ($changed_by === null) {
        $changed_by = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    }

    // Insert history row
    $sql = "INSERT INTO status_history
            (candidate_id, job_id, new_status, kpi_bucket, event_type, changed_by)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $candidate_id,
        $job_id,
        $new_status,
        $bucket,
        $etype,
        $changed_by
    ]);
}
