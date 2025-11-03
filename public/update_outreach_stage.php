<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/date_helpers.php'; // business-day logic

$contact_id   = $_POST['id'] ?? null;
$new_stage    = $_POST['outreach_stage'] ?? null;

// Optional future-proof selector (defaults to voicemail cadence)
$cadence_type = isset($_POST['cadence_type']) ? trim((string)$_POST['cadence_type']) : 'voicemail';
$cadence_type = in_array(strtolower($cadence_type), ['voicemail', 'mixed'], true)
    ? strtolower($cadence_type)
    : 'voicemail';

if (!$contact_id || !$new_stage) {
    header("Location: view_contact.php?id=$contact_id&msg=Missing+data");
    exit;
}

/**
 * Resolve stage metadata using includes/cadence.php when available,
 * with a safe internal fallback matching your voicemail-only cadence.
 *
 * Returns array: ['label' => string, 'delay_bd' => int, 'flags' => array]
 */
function resolve_stage_meta(int $stage, string $cadenceType = 'voicemail'): array
{
    // Preferred: project-wide cadence resolver
    $resolver = __DIR__ . '/../includes/cadence.php';
    if (is_file($resolver)) {
        require_once $resolver;
        if (function_exists('cadence_lookup')) {
            $res = cadence_lookup($stage, $cadenceType);
            if (is_array($res) && isset($res['label']) && array_key_exists('delay_bd', $res)) {
                // Normalize output keys
                return [
                    'label'    => (string)$res['label'],
                    'delay_bd' => (int)$res['delay_bd'],
                    'flags'    => (array)($res['flags'] ?? []),
                ];
            }
        }
    }

    // ---- Fallback: Voicemail-only cadence (12 touches) ----
    static $labels = [
        1  => 'Touch 1 – Voicemail #1',
        2  => 'Touch 2 – Voicemail #2',
        3  => 'Touch 3 – Voicemail #3',
        4  => 'Touch 4 – Voicemail #4',
        5  => 'Touch 5 – Voicemail #5',
        6  => 'Touch 6 – Voicemail #6',
        7  => 'Touch 7 – Voicemail #7',
        8  => 'Touch 8 – Voicemail #8',
        9  => 'Touch 9 – Voicemail #9',
        10 => 'Touch 10 – Voicemail #10',
        11 => 'Touch 11 – Voicemail #11',
        12 => 'Touch 12 – Voicemail #12 (Final)',
    ];

    static $delaysBD = [
        1  => 2, // → Touch 2
        2  => 3, // → Touch 3
        3  => 2, // → Touch 4
        4  => 3, // → Touch 5
        5  => 2, // → Touch 6
        6  => 3, // → Touch 7
        7  => 4, // → Touch 8
        8  => 3, // → Touch 9
        9  => 4, // → Touch 10
        10 => 5, // → Touch 11
        11 => 5, // → Touch 12
        12 => 0, // end
    ];

    static $flags = [
        1 => ['li_connect' => true], // optional LinkedIn connect at first touch
    ];

    return [
        'label'    => $labels[$stage]   ?? ("Touch " . $stage),
        'delay_bd' => $delaysBD[$stage] ?? 0,
        'flags'    => $flags[$stage]    ?? [],
    ];
}

// Build holiday set (current + next year) for business-day math
$year = (int)date('Y');
$holidays = array_merge(
    generateCommonUSHolidays($year),
    generateCommonUSHolidays($year + 1)
);

// Resolve stage label / next delay / flags
$stage      = (int)$new_stage;
$meta       = resolve_stage_meta($stage, $cadence_type);
$stageLabel = $meta['label'];
$delayBD    = (int)$meta['delay_bd'];
$flags      = (array)$meta['flags'];

// Update outreach stage, cadence type, and schedule next follow-up (if any)
if ($delayBD === 0) {
    // End-of-cadence or no next step — clear follow-up
    $stmt = $pdo->prepare("
        UPDATE contacts
           SET outreach_stage = ?,
               outreach_cadence = ?,
               last_touch_date = NOW(),
               follow_up_date = NULL
         WHERE id = ?
    ");
    $stmt->execute([$stage, $cadence_type, $contact_id]);
} else {
    // Schedule next follow-up in BUSINESS DAYS (skip weekends + provided holidays)
    $tz   = new DateTimeZone('Asia/Manila'); // adjust if you ever store per-contact tz
    $now  = new DateTimeImmutable('now', $tz);
    $next = addBusinessDays($now, $delayBD, $holidays)->format('Y-m-d');

    $stmt = $pdo->prepare("
        UPDATE contacts
           SET outreach_stage = ?,
               outreach_cadence = ?,
               last_touch_date = NOW(),
               follow_up_date = ?
         WHERE id = ?
    ");
    $stmt->execute([$stage, $cadence_type, $next, $contact_id]);
}

// Add auto-generated note for audit trail (include key flag hints if present)
$note = "Outreach stage changed to: {$stageLabel} [Cadence: " . ucfirst($cadence_type) . "]";
if (!empty($flags)) {
    $flagKeys = implode(', ', array_keys(array_filter($flags, fn($v) => (bool)$v)));
    if ($flagKeys !== '') {
        $note .= " ({$flagKeys})";
    }
}

$stmt = $pdo->prepare("
    INSERT INTO notes (module_type, module_id, content, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([
    'contact',
    $contact_id,
    $note
]);

header("Location: view_contact.php?id=$contact_id&msg=Outreach+stage+updated");
exit;
