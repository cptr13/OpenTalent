<?php
// ajax/render_script.php
// AJAX endpoint to render CANONICAL pipeline scripts only. Returns strict JSON only.

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

/**
 * Canonical mapping: touch_number -> content_kind
 * - voicemail: 1,3,5,7,9,11
 * - email:     2,6,10,12
 * - linkedin:  4,8
 */
if (!function_exists('pipeline_content_kind_for_touch')) {
    function pipeline_content_kind_for_touch(int $touchNumber): string {
        if (in_array($touchNumber, [1,3,5,7,9,11], true)) return 'cadence_voicemail';
        if (in_array($touchNumber, [2,6,10,12], true))    return 'cadence_email';
        if (in_array($touchNumber, [4,8], true))          return 'cadence_linkedin';
        // Safety fallback (should never happen if touch validated)
        return 'cadence_voicemail';
    }
}

try {
    // ---- Inputs (pipeline-only) ----
    $contactId = (($v = req_param('contact_id')) !== null && $v !== '') ? (int)$v : null;
    if (!$contactId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Missing contact_id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // tone: dropdown value. "auto" allowed.
    $toneParamRaw = req_param('tone');
    if ($toneParamRaw === null) {
        $toneParamRaw = req_param('tone_mode'); // legacy UI param name; still accepted, same meaning
    }
    $toneRequested = normalize_tone($toneParamRaw);

    // touch_number: optional explicit override for preview; otherwise use DB outreach_stage
    $touchNumber = null;
    if (($tnRaw = req_param('touch_number')) !== null && $tnRaw !== '') {
        $tn = (int)$tnRaw;
        if ($tn > 0) $touchNumber = $tn;
    }

    // NOTE: We intentionally IGNORE/REJECT legacy / removed concepts and params:
    // - script_type (any legacy branching)
    // - include_smalltalk, include_micro_offer
    // - cadence_type (voicemail/mixed)
    // - delivery_type (live_script / vm variants)
    // - any other legacy script selectors
    // Even if a user manipulates request params, this endpoint stays pipeline-only.

    // ---- Fetch contact defaults in ONE query ----
    $stmt = $pdo->prepare("SELECT outreach_stage, title FROM contacts WHERE id = ? LIMIT 1");
    $stmt->execute([$contactId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Touch fallback from DB if not explicitly provided
    if ($touchNumber === null) {
        $dbStage = $row['outreach_stage'] ?? null;
        $tn = (int)$dbStage;
        $touchNumber = ($tn > 0) ? $tn : 1;
    }

    // Validate touch_number within canonical 1..12
    if ($touchNumber < 1 || $touchNumber > 12) {
        http_response_code(400);
        echo json_encode([
            'ok'      => false,
            'message' => 'Invalid touch_number (must be 1..12)',
            'touch_number' => $touchNumber,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ---- Tone resolution ----
    // Contract: dropdown wins. If UI says auto, resolve it to a REAL tone based on contact title.
    $toneResolved = $toneRequested;
    if ($toneRequested === 'auto') {
        if (!function_exists('tone_from_title')) {
            throw new RuntimeException('tone_from_title() not found; includes/script_rules.php not loaded.');
        }
        $contactTitle = $row['title'] ?? null; // null -> tone_from_title should return consultative
        $toneResolved = tone_from_title($contactTitle);
        if (!in_array($toneResolved, ['friendly','consultative','direct'], true)) {
            $toneResolved = 'consultative';
        }
    }

    // ---- Canonical content_kind mapping ----
    $contentKind = pipeline_content_kind_for_touch($touchNumber);

    // ---- Build context for renderer (pipeline-only) ----
    $ctx = [
        'contact_id'    => $contactId,

        // Canonical selectors
        'touch_number'  => $touchNumber,
        'tone_mode'     => $toneResolved,
        'content_kind'  => $contentKind,

        // Removed concepts forced off (must not exist after changes)
        'include_smalltalk'   => false,
        'include_micro_offer' => false,

        // TODO: If includes/script_renderer.php still expects any legacy fields (e.g. script_type_slug/cadence_type)
        // it should be updated there to use ONLY (touch_number, tone_mode, content_kind) against script_templates_unified.
        // We intentionally do NOT pass user-controlled script_type/cadence/delivery to avoid non-canonical scripts.
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
        'content_kind'     => $contentKind,

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
