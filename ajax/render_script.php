<?php
// ajax/render_script.php
// AJAX endpoint to render dynamic scripts. Returns strict JSON only.

// IMPORTANT: Never echo warnings/notices into JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Force JSON headers and no caching
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Convert ALL PHP warnings/notices to JSON responses (keeps output valid JSON)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'type'    => 'php_error',
        'errno'   => $errno,
        'message' => $errstr,
        'file'    => $errfile,
        'line'    => $errline,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

set_exception_handler(function($ex) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'type'    => 'exception',
        'message' => $ex->getMessage(),
        'file'    => $ex->getFile(),
        'line'    => $ex->getLine(),
        'trace'   => $ex->getTraceAsString(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

// --- Auth check ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// --- Includes ---
$databasePath = __DIR__ . '/../config/database.php';
$rendererPath = __DIR__ . '/../includes/script_renderer.php';
$rulesPath    = __DIR__ . '/../includes/script_rules.php'; // for tone_from_title()

if (!is_file($databasePath)) throw new RuntimeException("Missing include: $databasePath");
if (!is_file($rendererPath)) throw new RuntimeException("Missing include: $rendererPath");
if (!is_file($rulesPath))    throw new RuntimeException("Missing include: $rulesPath");

require_once $databasePath;
require_once $rendererPath;
require_once $rulesPath;

if (!function_exists('render_script')) {
    throw new RuntimeException("Function render_script() not found in includes/script_renderer.php");
}

/**
 * Fetch a scalar request parameter from POST first, then GET.
 * Returns null if not present.
 */
if (!function_exists('req_param')) {
    function req_param(string $key): ?string {
        if (isset($_POST[$key])) return (string)$_POST[$key];
        if (isset($_GET[$key]))  return (string)$_GET[$key];
        return null;
    }
}

/** Keep script_type sane (avoid weird junk). */
if (!function_exists('normalize_script_type')) {
    function normalize_script_type(?string $s): string {
        $s = strtolower(trim((string)$s));
        // allow letters, numbers, underscore only (matches your slugs like cold_call, voicemail, etc.)
        $s = preg_replace('/[^a-z0-9_]/', '', $s);
        return $s;
    }
}

/** Validate cadence string against allowed values. */
if (!function_exists('normalize_cadence')) {
    function normalize_cadence(?string $s): ?string {
        if ($s === null) return null;
        $s = strtolower(trim($s));
        return in_array($s, ['voicemail','mixed'], true) ? $s : null;
    }
}

/**
 * Normalize tone value from UI dropdown.
 * Allowed: auto | friendly | consultative | direct
 */
if (!function_exists('normalize_tone')) {
    function normalize_tone(?string $s): string {
        if ($s === null) return 'auto';
        $s = strtolower(trim($s));
        return in_array($s, ['auto','friendly','consultative','direct'], true) ? $s : 'auto';
    }
}

try {
    // ---- Inputs ----
    $scriptTypeRaw = req_param('script_type');
    $scriptType    = normalize_script_type($scriptTypeRaw);

    $contactId   = (($v = req_param('contact_id'))   !== null && $v !== '') ? (int)$v : null;
    $clientId    = (($v = req_param('client_id'))    !== null && $v !== '') ? (int)$v : null;
    $jobId       = (($v = req_param('job_id'))       !== null && $v !== '') ? (int)$v : null;
    $candidateId = (($v = req_param('candidate_id')) !== null && $v !== '') ? (int)$v : null;

    // Prefer explicit contact over candidate fallback
    if ($contactId) {
        $candidateId = null;
    }

    if ($scriptType === '') {
        echo json_encode(['ok' => false, 'message' => 'Missing script_type'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Dropdown wins: tone
    $toneParamRaw = req_param('tone');
    if ($toneParamRaw === null) {
        $toneParamRaw = req_param('tone_mode'); // legacy fallback
    }
    $toneRequested = normalize_tone($toneParamRaw);

    // Flags
    $includeSmalltalk  = (($v = req_param('include_smalltalk'))   !== null) ? ((int)$v !== 0) : true;
    $includeMicroOffer = (($v = req_param('include_micro_offer')) !== null) ? ((int)$v !== 0) : true;

    // Dropdown wins: cadence (may be null if not sent)
    $cadenceType = normalize_cadence(req_param('cadence_type'));

    // Dropdown wins: touch_number override for preview (may be null if not sent)
    $touchNumber = null;
    if (($tnRaw = req_param('touch_number')) !== null && $tnRaw !== '') {
        $tn = (int)$tnRaw;
        if ($tn > 0) $touchNumber = $tn;
    }

    // Delivery type (vm/live) passed through to renderer
    $delivery = req_param('delivery_type');
    $delivery = $delivery ? strtolower(trim($delivery)) : null;

    // ---- If contact exists, fetch defaults in ONE query ----
    $row = null;
    if ($contactId) {
        $stmt = $pdo->prepare("SELECT outreach_stage, outreach_cadence, title FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Touch fallback from DB if not explicitly provided
    if ($touchNumber === null) {
        $dbStage = $row['outreach_stage'] ?? null;
        $tn = (int)$dbStage;
        $touchNumber = ($tn > 0) ? $tn : 1;
    }

    // Cadence fallback from DB if not explicitly provided
    if ($cadenceType === null) {
        $savedCad = $row['outreach_cadence'] ?? null;
        $cadenceType = normalize_cadence($savedCad) ?? 'voicemail';
    }

    // ---- Tone resolution ----
    // Keep the contract: dropdown wins. If UI says auto, resolve it to a REAL tone based on contact title.
    // (That means renderer will treat it as manual, and will NOT do its own auto inference.)
    $toneResolved = $toneRequested;
    if ($toneRequested === 'auto') {
        if (!function_exists('tone_from_title')) {
            throw new RuntimeException('tone_from_title() not found; includes/script_rules.php not loaded.');
        }
        $contactTitle = $row['title'] ?? null; // null → tone_from_title should return consultative
        $toneResolved = tone_from_title($contactTitle);
        // hard safety
        if (!in_array($toneResolved, ['friendly','consultative','direct'], true)) {
            $toneResolved = 'consultative';
        }
    }

    // ---- Build context for renderer ----
    $ctx = [
        'script_type_slug'    => $scriptType,
        'contact_id'          => $contactId,
        'client_id'           => $clientId,
        'job_id'              => $jobId,
        'candidate_id'        => $candidateId,

        // Send a concrete tone so renderer treats it as final
        'tone_mode'           => $toneResolved,

        'include_smalltalk'   => $includeSmalltalk,
        'include_micro_offer' => $includeMicroOffer,

        'touch_number'        => $touchNumber,
        'cadence_type'        => $cadenceType,

        'delivery_type'       => $delivery,
    ];

    // ---- Render ----
    $res = render_script($ctx);

    if (is_string($res)) {
        $res = ['text' => $res];
    } elseif (!is_array($res)) {
        throw new RuntimeException('render_script() returned unexpected type: ' . gettype($res));
    }

    // Safe excerpt for UI
    $ctxFull = $res['context'] ?? [];
    $context_excerpt = null;
    if (is_array($ctxFull) && $ctxFull) {
        $context_excerpt = [
            'location'      => $ctxFull['location']      ?? null,
            'region'        => $ctxFull['region']        ?? null,
            'company'       => $ctxFull['company']       ?? ($ctxFull['company_name'] ?? null),
            'contact_first' => $ctxFull['contact_first'] ?? ($ctxFull['first_name'] ?? null),
        ];
    }

    $out = [
        'ok'               => true,
        'text'             => $res['text']          ?? '',
        'tone_used'        => $res['tone_used']     ?? $toneResolved,
        'template_name'    => $res['template_name'] ?? null,
        'context'          => $res['context']       ?? null,
        'context_excerpt'  => $context_excerpt,
        'missing'          => $res['missing']       ?? [],
        'warnings'         => $res['warnings']      ?? [],

        // Echo resolved values (for UI/log sanity)
        'touch_number'     => $touchNumber,
        'cadence_type'     => $cadenceType,

        // Debug: what did UI request vs what did we actually use?
        'tone_requested'   => $toneRequested,
        'tone_param'       => $toneResolved,
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Renderer error',
        'error'   => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
