<?php
// ajax/render_script.php
// AJAX endpoint to render dynamic scripts. Returns strict JSON only.

ini_set('display_errors', 0); // never echo PHP warnings/notices into JSON
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Force JSON headers and no caching
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Convert ALL PHP warnings/notices/fatals to JSON responses
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

// --- Auth check (no path changes) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// --- Includes (ajax/ is at project root; keep paths exactly as you had them) ---
$databasePath = __DIR__ . '/../config/database.php';
$rendererPath = __DIR__ . '/../includes/script_renderer.php';
$rulesPath    = __DIR__ . '/../includes/script_rules.php'; // for tone_from_title()

if (!is_file($databasePath)) {
    throw new RuntimeException("Missing include: $databasePath");
}
if (!is_file($rendererPath)) {
    throw new RuntimeException("Missing include: $rendererPath");
}
if (!is_file($rulesPath)) {
    throw new RuntimeException("Missing include: $rulesPath");
}

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

/**
 * Validate cadence string against allowed values.
 */
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
    // ---- Sanitize inputs ----
    $scriptType  = trim((string)(req_param('script_type') ?? ''));
    $contactId   = (($v = req_param('contact_id'))   !== null && $v !== '') ? (int)$v : null;
    $clientId    = (($v = req_param('client_id'))    !== null && $v !== '') ? (int)$v : null;
    $jobId       = (($v = req_param('job_id'))       !== null && $v !== '') ? (int)$v : null;
    $candidateId = (($v = req_param('candidate_id')) !== null && $v !== '') ? (int)$v : null;

    // **Dropdown wins**:
    // Prefer ?tone= from the UI dropdown; fall back to ?tone_mode= if present; else 'auto'
    $toneParamRaw = req_param('tone');
    if ($toneParamRaw === null) {
        $toneParamRaw = req_param('tone_mode'); // legacy/fallback
    }
    $tone = normalize_tone($toneParamRaw);

    $includeSmalltalk  = (($v = req_param('include_smalltalk'))   !== null) ? ((int)$v !== 0) : true;
    $includeMicroOffer = (($v = req_param('include_micro_offer')) !== null) ? ((int)$v !== 0) : true;

    // Optional: explicit cadence type override from UI
    $cadenceType = normalize_cadence(req_param('cadence_type')); // may be null if not sent

    if ($scriptType === '') {
        echo json_encode(['ok' => false, 'message' => 'Missing script_type'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // If an explicit contact is chosen, ignore candidate_id (candidate is only a fallback)
    if ($contactId) {
        $candidateId = null;
    }

    // Allow explicit touch_number override for preview (e.g., dropdown changed but not saved yet)
    $touchNumber = null;
    if (($tnRaw = req_param('touch_number')) !== null && $tnRaw !== '') {
        $tn = (int)$tnRaw;
        if ($tn > 0) $touchNumber = $tn;
    }

    // If we have a contact, fetch stage, saved cadence, AND title (for tone auto-resolution) in a single query
    $row = null;
    if ($contactId) {
        $stmt = $pdo->prepare("SELECT outreach_stage, outreach_cadence, title FROM contacts WHERE id = ? LIMIT 1");
        $stmt->execute([$contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Derive touch number if not explicitly provided
    if ($touchNumber === null) {
        if ($row && isset($row['outreach_stage'])) {
            $tn = (int)$row['outreach_stage'];
            $touchNumber = $tn > 0 ? $tn : 1;
        } else {
            $touchNumber = 1; // safe default
        }
    }

    // Resolve cadence type:
    // Priority: explicit UI override -> saved DB value -> default 'voicemail'
    if ($cadenceType === null) {
        $saved = $row['outreach_cadence'] ?? null;
        $cadenceType = normalize_cadence($saved) ?? 'voicemail';
    }

    // ----------------------
    // Tone auto-resolution (STRICT by contact title)
    // ----------------------
    // Only resolve when UI sent 'auto'; otherwise, respect the chosen tone.
    if ($tone === 'auto') {
        $contactTitle = $row['title'] ?? null; // may be null; tone_from_title handles null as consultative
        if (!function_exists('tone_from_title')) {
            throw new RuntimeException('tone_from_title() not found; includes/script_rules.php not loaded.');
        }
        $tone = tone_from_title($contactTitle); // friendly | consultative | direct
    }

    // ----------------------
    // Build render context
    // ----------------------
    $delivery = req_param('delivery_type');
    $delivery = $delivery ? strtolower(trim($delivery)) : null;

    $ctx = [
        'script_type_slug'    => $scriptType,
        'contact_id'          => $contactId,
        'client_id'           => $clientId,
        'job_id'              => $jobId,
        'candidate_id'        => $candidateId,

        // **Dropdown wins** (with auto resolved just above)
        'tone_mode'           => $tone,

        'include_smalltalk'   => $includeSmalltalk,
        'include_micro_offer' => $includeMicroOffer,

        // Context for renderer
        'touch_number'        => $touchNumber,
        'cadence_type'        => $cadenceType,

        // Optional: pass delivery_type through if UI sends it (vm/live)
        'delivery_type'       => $delivery,
    ];

    // ---- Render ----
    $res = render_script($ctx);

    // Normalize/validate the renderer response to avoid undefined-index fatals
    if (is_string($res)) {
        $res = ['text' => $res];
    } elseif (!is_array($res)) {
        throw new RuntimeException('render_script() returned unexpected type: ' . gettype($res));
    }

    // Small, safe excerpt for UI badges (won't break if context is missing)
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
        'tone_used'        => $res['tone_used']     ?? $tone, // echo tone the renderer chose; fallback to resolved tone
        'template_name'    => $res['template_name'] ?? null,
        'context'          => $res['context']       ?? null,
        'context_excerpt'  => $context_excerpt,
        'missing'          => $res['missing']       ?? [],
        'warnings'         => $res['warnings']      ?? [],

        // Echo back what we resolved so UI (or logs) can show it
        'touch_number'     => $touchNumber,
        'cadence_type'     => $cadenceType,

        // Debug/verification: what tone value did the endpoint finally use?
        'tone_param'       => $tone,
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    // Any exception becomes a structured JSON error with file/line
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
