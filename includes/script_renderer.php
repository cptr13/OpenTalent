<?php
// includes/script_renderer.php
// Deterministic renderer for canonical outreach scripts (no AI, no legacy fallbacks).

if (!defined('OT2_LOADED')) {
    define('OT2_LOADED', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/script_rules.php';

// Optional region helper (safe if absent)
if (file_exists(__DIR__ . '/geo_region.php')) {
    require_once __DIR__ . '/geo_region.php';
}

// Optional cadence helper (safe if absent)
if (file_exists(__DIR__ . '/cadence.php')) {
    require_once __DIR__ . '/cadence.php';
}

//
// -------------------------------
// Public API
// -------------------------------
// render_script(array $ctx): array
// pick_tone(?string $manual, ?string $stageSlug, ?int $touch, ?string $personaSlug): string
// hydrate_vars(?int $contactId, ?int $clientId, ?int $jobId, ?int $candidateId = null): array
// cleanup_text(string $txt): string
//

/**
 * Render a personalized script based on context.
 * Canonical-only: script_templates_unified is the ONLY source.
 *
 * @param array $ctx
 * @return array {
 *   text: string,
 *   tone_used: string,
 *   template_name: string,
 *   context: array,
 *   missing: array,
 *   warnings: array
 * }
 */
function render_script(array $ctx): array {
    global $pdo;

    $warnings = [];
    $missing  = [];

    // 1) Validate inputs
    // Canonical pipeline-only default:
    // If caller omits script_type_slug, assume 'pipeline' (do NOT hard-fail).
    $scriptType = trim((string)($ctx['script_type_slug'] ?? ''));
    if ($scriptType === '') {
        $scriptType = 'pipeline';
        $warnings[] = 'defaulted_script_type_slug_pipeline';
    }
    $scriptType = strtolower($scriptType);

    $contactId   = isset($ctx['contact_id']) ? (int)$ctx['contact_id'] : null;
    $clientId    = isset($ctx['client_id']) ? (int)$ctx['client_id'] : null;
    $jobId       = isset($ctx['job_id']) ? (int)$ctx['job_id'] : null;
    $candidateId = isset($ctx['candidate_id']) ? (int)$ctx['candidate_id'] : null; // candidate fallback

    $toneMode  = strtolower((string)($ctx['tone_mode'] ?? 'auto'));
    if (!in_array($toneMode, ['auto','friendly','consultative','direct'], true)) {
        $toneMode = 'auto';
    }

    // Canonical system rules:
    // - No smalltalk
    // - No microoffers
    // - No legacy fallbacks
    // (We accept the keys without using them to avoid breaking callers.)
    $includeSmalltalk  = false;
    $includeMicroOffer = false;

    // 2) Gather facts (now with candidate fallback + location/region derivation)
    $vars = hydrate_vars($contactId, $clientId, $jobId, $candidateId);

    // 3) Determine cadence + touch (UI may omit them; infer from contact row)
    $cadenceFromCtx = isset($ctx['cadence_type']) ? strtolower((string)$ctx['cadence_type']) : null;

    $allowedCadences = ['voicemail','mixed','unified'];
    if ($cadenceFromCtx !== null && !in_array($cadenceFromCtx, $allowedCadences, true)) {
        $cadenceFromCtx = null;
    }

    $savedCad = null;
    if (isset($vars['outreach_cadence'])) {
        $savedCad = strtolower((string)$vars['outreach_cadence']);
        if (!in_array($savedCad, ['voicemail','mixed'], true)) {
            $savedCad = null;
        }
    }

    $cadenceTypeRaw = $cadenceFromCtx ?? $savedCad ?? 'voicemail';

    // Legacy normalize (kept for context), but canonical pipeline is independent of this.
    $cadenceType = $cadenceTypeRaw;
    if ($cadenceTypeRaw === 'mixed') {
        $cadenceType = 'voicemail';
    }

    $touchFromCtx = isset($ctx['touch_number']) ? (int)$ctx['touch_number'] : null;
    $touchNumber  = $touchFromCtx && $touchFromCtx > 0
        ? $touchFromCtx
        : (isset($vars['outreach_stage_num']) && (int)$vars['outreach_stage_num'] > 0
            ? (int)$vars['outreach_stage_num']
            : 1);

    $touchNumber = max(1, (int)$touchNumber);

    // Ensure cadence + touch are in the context for templates and for tone rules
    $vars['cadence_type']        = $cadenceType;
    $vars['cadence_type_raw']    = $cadenceTypeRaw;
    $vars['touch_number']        = $touchNumber;
    $vars['attempt_count_total'] = max(1, (int)$touchNumber);

    // Prefer centralized cadence metadata (labels/channels) if available.
    if (function_exists('cadence_lookup')) {
        try {
            $cadenceKey = ($scriptType === 'pipeline') ? 'unified' : (($cadenceTypeRaw === 'unified') ? 'unified' : $cadenceType);
            $meta = cadence_lookup($touchNumber, $cadenceKey);

            if (is_array($meta)) {
                if (!empty($meta['label'])) {
                    $vars['touch_label'] = (string)$meta['label'];
                }
                if (!empty($meta['channel'])) {
                    $vars['channel'] = (string)$meta['channel'];
                }
            }
        } catch (Throwable $e) {
            // non-fatal
        }
    }

    // If cadence_lookup didn't provide channel (or cadence.php missing),
    // apply canonical fallback mapping for the unified pipeline use case.
    if (empty($vars['channel']) && ($cadenceTypeRaw === 'unified' || $scriptType === 'pipeline')) {
        $vars['channel'] = unified_channel_fallback($touchNumber);
        if (empty($vars['touch_label'])) {
            $vars['touch_label'] = unified_label_fallback($touchNumber);
        }
    }

    // Back-compat: if someone expects script_channel_for(), call it correctly (kept, but canonical mapping wins).
    if (empty($vars['channel']) && function_exists('script_channel_for')) {
        try {
            $ch = script_channel_for($cadenceType, $touchNumber);
            if (is_string($ch) && $ch !== '') {
                $vars['channel'] = $ch;
            }
        } catch (Throwable $e) {
            // non-fatal
        }
    }

    // Normalize channel value to one of: call | call_vm | email | linkedin
    if (!empty($vars['channel'])) {
        $vars['channel'] = normalize_channel((string)$vars['channel']);
    }

    // ----------------------------
    // 4) Tone selection — DROPDOWN WINS
    // ----------------------------
    $toneUsed = null;

    if ($toneMode !== 'auto') {
        $toneUsed = $toneMode;
    } else {
        $autoTone = pick_tone(
            null,
            $vars['outreach_stage_slug'] ?? null,
            (int)($vars['attempt_count_total'] ?? 1),
            $vars['contact_function'] ?? null
        );
        $toneUsed = $autoTone;
    }

    // Hard safety: canonical tones only.
    if (!in_array($toneUsed, ['friendly','consultative','direct'], true)) {
        $toneUsed = 'consultative';
    }

    // 5) Resolve canonical template variant from unified table ONLY.
    $variant = resolve_template_variant($pdo, $scriptType, $cadenceTypeRaw, $touchNumber, $vars, $toneUsed, $ctx);
    $templateName = null;
    $body         = null;

    if ($variant) {
        $templateName = $variant['name'];
        $body         = (string)$variant['body'];
    }

    // 6) If canonical template missing, return clear not-found (NO fallback to legacy or generic).
    if ($body === null) {
        $missingKeys = [
            'script_templates_unified',
            'touch_number=' . $touchNumber,
            'tone=' . $toneUsed,
            'script_type=' . $scriptType,
        ];

        // Add canonical kind/slug expectations for debug clarity
        $expectedKind = canonical_content_kind_for_context($scriptType, $touchNumber, $vars, $ctx);
        $expectedSlug = canonical_pipeline_slug($touchNumber, $toneUsed);
        $missingKeys[] = 'content_kind=' . $expectedKind;
        $missingKeys[] = 'template_slug=' . $expectedSlug;

        $missing = array_merge($missing, $missingKeys);

        return canonical_not_found_response('canonical_template_not_found', $missing, $warnings, $vars);
    }

    // 7) Canonical: no smalltalk / microoffer injection.
    $data = $vars;

    // 8) Render template (supports dotted keys + simple pipes) then apply canonical {Token} placeholders.
    $rendered = mustache_render($body, $data);
    $rendered = replace_canonical_placeholders($rendered, $vars);

    // 9) Post-process for delivery type (Voicemail vs Live Call), and drop timing lines.
    // Canonical pipeline kind decides delivery behavior.
    $delivery = detect_delivery_type($ctx, $scriptType, $vars);
    $rendered = post_process_delivery_text($rendered, $delivery);

    // 10) Post-cleanup & line collapsing for missing role/days (kept, but safe).
    if (empty($vars['top_open_role']) || empty($vars['days_open_top_role'])) {
        $rendered = preg_replace(
            '/^.*Quick note on your .*?$/mi',
            'Quick note on your hiring priorities.',
            $rendered
        );
    }

    $final = cleanup_text($rendered);

    return [
        'text'          => $final,
        'tone_used'     => $toneUsed ?? 'consultative',
        'template_name' => $templateName ?: 'unnamed_template',
        'context'       => $vars,
        'missing'       => $missing,
        'warnings'      => $warnings
    ];
}

/**
 * Determine tone by priority.
 */
function pick_tone(?string $manual, ?string $stageSlug, ?int $touch, ?string $personaSlug): string {
    global $pdo;

    if (!empty($manual)) {
        return $manual;
    }

    $stageSlug = $stageSlug ?: 'open';
    $touch     = $touch ?: 1;

    $rule = find_stage_rule($pdo, $stageSlug, $touch);
    if ($rule && !empty($rule['default_tone_slug'])) {
        return strtolower($rule['default_tone_slug']);
    }

    if (!empty($personaSlug)) {
        $pr = find_persona_rule($pdo, $personaSlug);
        if ($pr && !empty($pr['default_tone_slug'])) {
            return strtolower($pr['default_tone_slug']);
        }
    }

    return 'consultative';
}

/**
 * Resolve a per-touch template variant.
 * Canonical-only: lookup in script_templates_unified only.
 * Return shape: ['name' => string, 'body' => string]
 */
function resolve_template_variant(PDO $pdo, string $scriptType, string $cadenceTypeRaw, int $touchNumber, array $vars, ?string $toneUsed = null, array $ctx = []): ?array
{
    $tone = $toneUsed ?: 'consultative';
    if (!in_array($tone, ['friendly','consultative','direct'], true)) {
        $tone = 'consultative';
    }

    $kind = canonical_content_kind_for_context($scriptType, $touchNumber, $vars, $ctx);

    // Canonical slug contract applies to pipeline.
    $expectedSlug = canonical_pipeline_slug($touchNumber, $tone);

    $row = find_canonical_unified_variant($pdo, $kind, $touchNumber, $tone, $expectedSlug);
    if ($row) {
        return [
            'name' => build_pipeline_name($row),
            'body' => (string)$row['body'],
        ];
    }

    // No tone fallback, no kind fallback, no legacy fallback.
    return null;
}

/**
 * Canonical DB lookup: strict match for canonical templates.
 * Enforces exact template_slug for pipeline contract.
 */
function find_canonical_unified_variant(PDO $pdo, string $kind, int $touchNumber, string $tone, string $expectedSlug) {
    $sql = "
        SELECT id, template_slug, content_kind, touch_number, tone_default, body, status, updated_at
        FROM script_templates_unified
        WHERE status = 'active'
          AND template_slug = ?
          AND content_kind = ?
          AND touch_number = ?
          AND LOWER(tone_default) = LOWER(?)
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ";
    return db_first_row($pdo, $sql, [$expectedSlug, $kind, $touchNumber, $tone]);
}

/** Build readable name for pipeline templates (uses actual row identifiers) */
function build_pipeline_name(array $row): string {
    $slug = (string)($row['template_slug'] ?? 'pipeline');
    $kind = (string)($row['content_kind'] ?? 'unknown_kind');
    $tn   = (string)($row['touch_number'] ?? '?');
    $tone = (string)($row['tone_default'] ?? '?');
    $id   = (string)($row['id'] ?? '?');
    return "{$slug} ({$kind} T{$tn} tone={$tone} #{$id})";
}

/**
 * Canonical content_kind mapping for unified pipeline.
 * Final allowed kinds:
 * - cadence_voicemail: steps 1,3,5,7,9,11
 * - cadence_email: steps 2,6,10,12
 * - cadence_linkedin: steps 4,8
 */
function canonical_content_kind_for_touch(int $touchNumber): string {
    $t = max(1, (int)$touchNumber);

    if (in_array($t, [1,3,5,7,9,11], true)) return 'cadence_voicemail';
    if (in_array($t, [2,6,10,12], true)) return 'cadence_email';
    if (in_array($t, [4,8], true)) return 'cadence_linkedin';

    // Should never happen with 1-12, but keep deterministic.
    return 'cadence_voicemail';
}

/**
 * Canonical kind selection for this context.
 * For now, pipeline is the canonical system. We still map non-pipeline deterministically into the canonical kinds
 * to avoid any old "live_script" or other removed kinds.
 */
function canonical_content_kind_for_context(string $scriptType, int $touchNumber, array $vars, array $ctx): string {
    $s = strtolower(trim($scriptType));

    if ($s === 'pipeline') {
        return canonical_content_kind_for_touch($touchNumber);
    }

    // If something else calls this renderer, we still only allow canonical kinds.
    // Prefer explicit channel if present, else map by touch.
    $ch = $vars['channel'] ?? ($ctx['channel'] ?? null);
    $ch = $ch ? normalize_channel((string)$ch) : null;

    if ($ch === 'email') return 'cadence_email';
    if ($ch === 'linkedin') return 'cadence_linkedin';

    // call/call_vm and unknown fall back to voicemail kind (canonical).
    return 'cadence_voicemail';
}

/**
 * Canonical slug for pipeline rows: pipeline_step{NN}_{tone}
 */
function canonical_pipeline_slug(int $touchNumber, string $tone): string {
    $t = max(1, (int)$touchNumber);
    $nn = str_pad((string)$t, 2, '0', STR_PAD_LEFT);

    $tone = strtolower(trim($tone));
    if (!in_array($tone, ['friendly','consultative','direct'], true)) {
        $tone = 'consultative';
    }

    return "pipeline_step{$nn}_{$tone}";
}

/** Fetch first row helper */
function db_first_row(PDO $pdo, string $sql, array $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Normalize channel values into: call | call_vm | email | linkedin
 */
function normalize_channel(string $ch): string {
    $c = strtolower(trim($ch));
    if ($c === 'call_vm' || $c === 'vm' || $c === 'voicemail' || $c === 'callvoicemail') return 'call_vm';
    if ($c === 'call' || $c === 'phone' || $c === 'cold_call' || $c === 'live') return 'call';
    if ($c === 'linkedin' || $c === 'li' || $c === 'dm') return 'linkedin';
    if ($c === 'email' || $c === 'mail') return 'email';
    return $c;
}

/**
 * Fallback unified channel mapping (only if cadence_lookup is absent).
 * Must match the UI contract labels/steps.
 */
function unified_channel_fallback(int $touch): string {
    $t = max(1, (int)$touch);

    // UI contract mapping:
    // 1 Call
    // 2 Email / Call (No Email)
    // 3 Call
    // 4 LinkedIn Connection
    // 5 Call
    // 6 Email / Call (No Email)
    // 7 Call
    // 8 LinkedIn / Email (Fallback)
    // 9 Call
    // 10 Email / Call (No Email)
    // 11 Call
    // 12 Close-the-Loop Email
    if (in_array($t, [2,6,10,12], true)) return 'email';
    if (in_array($t, [4,8], true)) return 'linkedin';

    // Canonical: "call" steps are stored as cadence_voicemail kind.
    // We keep call_vm for the post-processing logic, but the template lookup uses canonical kind mapping.
    return 'call_vm';
}

/**
 * Fallback labels if cadence_lookup missing (minimal).
 */
function unified_label_fallback(int $touch): string {
    $t = max(1, (int)$touch);
    return "Touch {$t}";
}

/**
 * Canonical not-found response (no fallbacks).
 */
function canonical_not_found_response(string $reason, array $missing, array $warnings, array $vars): array {
    $warnings[] = $reason;

    return [
        'text'          => '[CANONICAL SCRIPT NOT FOUND]',
        'tone_used'     => 'consultative',
        'template_name' => 'not_found',
        'context'       => $vars,
        'missing'       => $missing,
        'warnings'      => $warnings,
    ];
}

/**
 * Canonical placeholder replacement for single-brace tokens:
 * {FirstName}, {YourName}, {FirmName}, {Industry}
 * - Unresolved -> empty string
 * - Never show raw {Token}
 */
function replace_canonical_placeholders(string $text, array $vars): string {
    $firstName = '';
    if (!empty($vars['first_name'])) {
        $firstName = (string)$vars['first_name'];
    } elseif (!empty($vars['contact_first']) && (string)$vars['contact_first'] !== 'there') {
        $firstName = (string)$vars['contact_first'];
    }

    $yourName = '';
    if (!empty($vars['user_first'])) {
        $yourName = (string)$vars['user_first'];
    } elseif (!empty($vars['your_name'])) {
        $yourName = (string)$vars['your_name'];
    }

    $firmName = '';
    if (!empty($vars['your_agency'])) {
        $firmName = (string)$vars['your_agency'];
    }

    $industry = '';
    if (!empty($vars['industry'])) {
        $industry = (string)$vars['industry'];
    }

    $repl = [
        '{FirstName}' => $firstName,
        '{YourName}'  => $yourName,
        '{FirmName}'  => $firmName,
        '{Industry}'  => $industry,
    ];

    $text = strtr($text, $repl);

    // Strip any remaining single-brace {Token} so users never see raw placeholders.
    // (We only target identifier-like tokens to avoid nuking normal braces in prose.)
    $text = preg_replace('/\{[A-Za-z][A-Za-z0-9_]*\}/u', '', $text);

    return $text;
}

/**
 * Load facts from DB and compute derived fields.
 * (unchanged below this line, except we keep your existing behavior)
 */
function hydrate_vars(?int $contactId, ?int $clientId, ?int $jobId, ?int $candidateId = null): array {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $vars = [
        'contact_first'        => 'there',
        'first_name'           => null,
        'last_name'            => null,
        'full_name'            => null,
        'email'                => null,
        'secondary_email'      => null,
        'phone'                => null,
        'phone_mobile'         => null,
        'title'                => null,
        'department'           => null,
        'linkedin'             => null,

        'address_street'       => null,
        'address_city'         => null,
        'address_state'        => null,
        'address_zip'          => null,
        'address_country'      => null,

        'company'              => null,
        'company_name'         => null,
        'industry'             => null,
        'client_location'      => null,
        'location'             => null,
        'region'               => null,

        'top_open_role'        => null,
        'job_location'         => null,
        'days_open_top_role'   => null,

        'attempt_count_total'  => 1,
        'outreach_stage'       => null,
        'outreach_stage_slug'  => 'open',
        'outreach_stage_num'   => null,
        'outreach_cadence'     => null,
        'contact_local_time'   => null,
        'local_part_of_day'    => null,
        'pain_point_snippet'   => null,
        'value_prop_snippet'   => null,

        'touch_label'          => null,
        'channel'              => null,

        'your_name'            => null,
        'user_first'           => null,
        'your_agency'          => null,
        'my_phone'             => null,
        'user_phone'           => null,
        'my_email'             => null,
        'calendar_link'        => null,
    ];

    if (!empty($_SESSION['user'])) {
        $full = trim((string)($_SESSION['user']['full_name'] ?? '')) ?: null;
        $vars['your_name'] = $full;
        $vars['user_first'] = $full ? preg_replace('/\s+.*/', '', $full) : null;
        $vars['my_email']  = $_SESSION['user']['email'] ?? null;
        $vars['my_phone']  = $_SESSION['user']['phone'] ?? null;
        $vars['user_phone'] = $vars['my_phone'];
    }

    try {
        $stmt = $pdo->query("SELECT company_name FROM system_settings LIMIT 1");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vars['your_agency'] = trim((string)($row['company_name'] ?? '')) ?: null;
        }
    } catch (Throwable $e) {
        // non-fatal
    }

    $contactFound = false;

    $contactCity = $contactState = $contactCountry = null;
    $candCity = $candState = $candCountry = null;
    $street = $city = $state = $zip = $country = null;

    if ($contactId) {
        $stmt = $pdo->prepare("
            SELECT c.*, cl.name AS client_name, cl.industry AS client_industry, cl.location AS client_location
            FROM contacts c
            LEFT JOIN clients cl ON cl.id = c.client_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $contactId]);
        if ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contactFound = true;

            $vars['first_name']      = trim((string)($c['first_name'] ?? '')) ?: null;
            $vars['last_name']       = trim((string)($c['last_name'] ?? '')) ?: null;
            $vars['full_name']       = trim((string)($c['full_name'] ?? '')) ?: null;
            $vars['email']           = trim((string)($c['email'] ?? '')) ?: null;
            $vars['secondary_email'] = trim((string)($c['secondary_email'] ?? '')) ?: null;
            $vars['phone']           = trim((string)($c['phone'] ?? '')) ?: null;
            $vars['phone_mobile']    = trim((string)($c['phone_mobile'] ?? '')) ?: null;
            $vars['title']           = trim((string)($c['title'] ?? '')) ?: null;
            $vars['department']      = trim((string)($c['department'] ?? '')) ?: null;
            $vars['linkedin']        = trim((string)($c['linkedin'] ?? '')) ?: null;

            $vars['contact_first'] = $vars['first_name'] ?: 'there';

            $vars['company_name'] = trim((string)($c['client_name'] ?? '')) ?: $vars['company_name'];
            $vars['company']      = $vars['company_name'];
            $vars['industry']     = trim((string)($c['client_industry'] ?? '')) ?: $vars['industry'];

            $stageNum = (int)($c['outreach_stage'] ?? 0);
            $vars['outreach_stage']      = $stageNum ?: null;
            $vars['outreach_stage_num']  = $stageNum ?: null;
            $vars['outreach_stage_slug'] = map_outreach_stage_to_slug($stageNum);

            if (array_key_exists('outreach_cadence', $c)) {
                $cad = strtolower((string)$c['outreach_cadence']);
                $vars['outreach_cadence'] = in_array($cad, ['voicemail','mixed'], true) ? $cad : null;
            }

            $street  = trim((string)($c['address_street']  ?? ''));
            $city    = trim((string)($c['address_city']    ?? ''));
            $state   = trim((string)($c['address_state']   ?? ''));
            $zip     = trim((string)($c['address_zip']     ?? ''));
            $country = trim((string)($c['address_country'] ?? ''));

            $vars['address_street']  = $street !== ''  ? $street  : null;
            $vars['address_city']    = $city !== ''    ? $city    : null;
            $vars['address_state']   = $state !== ''   ? $state   : null;
            $vars['address_zip']     = $zip !== ''     ? $zip     : null;
            $vars['address_country'] = $country !== '' ? $country : null;

            $contactCity    = $vars['address_city'];
            $contactState   = $vars['address_state'];
            $contactCountry = $vars['address_country'];

            $vars['client_location']           = trim((string)($c['client_location'] ?? '')) ?: null;
            $vars['_client_location_fallback'] = $vars['client_location'];

            $vars['contact_local_time'] = null;
        }
    }

    if (!$contactFound && $candidateId) {
        $stmt = $pdo->prepare("
            SELECT first_name, last_name, current_job, city, state, country
            FROM candidates
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $candidateId]);
        if ($cand = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vars['first_name']   = trim((string)($cand['first_name'] ?? '')) ?: null;
            $vars['last_name']    = trim((string)($cand['last_name'] ?? '')) ?: null;
            $vars['full_name']    = ($vars['first_name'] || $vars['last_name'])
                                    ? trim(($vars['first_name'] ?? '') . ' ' . ($vars['last_name'] ?? ''))
                                    : null;
            $vars['contact_first'] = $vars['first_name'] ?: 'there';
            $vars['title']        = trim((string)($cand['current_job'] ?? '')) ?: null;
            $vars['contact_function'] = derive_function_slug($vars['title']);

            $candCity    = ($cand['city']    ?? '') !== '' ? trim((string)$cand['city'])    : null;
            $candState   = ($cand['state']   ?? '') !== '' ? trim((string)$cand['state'])   : null;
            $candCountry = ($cand['country'] ?? '') !== '' ? trim((string)$cand['country']) : null;

            $candLocParts = [];
            foreach ([$candCity,$candState,$candCountry] as $fldVal) {
                if (!empty($fldVal)) $candLocParts[] = $fldVal;
            }
            $vars['_candidate_location_fallback'] = $candLocParts ? implode(', ', $candLocParts) : null;
        }
    }

    if ($clientId) {
        $stmt = $pdo->prepare("SELECT name, industry, location FROM clients WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $clientId]);
        if ($cl = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vars['company_name'] = trim((string)($cl['name'] ?? '')) ?: $vars['company_name'];
            $vars['company']      = $vars['company_name'];
            $vars['industry']     = trim((string)($cl['industry'] ?? '')) ?: $vars['industry'];
            $vars['client_location'] = trim((string)($cl['location'] ?? '')) ?: $vars['client_location'];
            $vars['_client_location_param'] = $vars['client_location'];
        }
    }

    if ($jobId) {
        $stmt = $pdo->prepare("SELECT title, created_at, location FROM jobs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $jobId]);
        if ($j = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vars['top_open_role']      = trim((string)($j['title'] ?? '')) ?: null;
            $vars['days_open_top_role'] = compute_days_open($j['created_at'] ?? null);
            $jobLoc = trim((string)($j['location'] ?? ''));
            $vars['job_location'] = $jobLoc !== '' ? $jobLoc : null;
        }
    }

    $location = null;
    if (isset($street) || isset($contactCity) || isset($contactState) || isset($zip) || isset($contactCountry)) {
        $parts = [];
        foreach ([$street ?? '', $contactCity ?? '', $contactState ?? '', $zip ?? '', $contactCountry ?? ''] as $v) {
            $v = trim((string)$v);
            if ($v !== '') $parts[] = $v;
        }
        if (!empty($parts)) {
            $location = implode(', ', $parts);
        }
    }
    if (!$location && !empty($vars['_client_location_fallback'])) {
        $location = $vars['_client_location_fallback'];
    }
    if (!$location && !empty($vars['_client_location_param'])) {
        $location = $vars['_client_location_param'];
    }
    if (!$location && !empty($vars['job_location'])) {
        $location = $vars['job_location'];
    }
    if (!$location && !empty($vars['_candidate_location_fallback'])) {
        $location = $vars['_candidate_location_fallback'];
    }
    $vars['location'] = $location ?: null;

    $region = null;
    try {
        if (function_exists('infer_region_from_parts') &&
            (($contactCity || $contactState || $contactCountry))) {
            $region = infer_region_from_parts(
                (string)($contactCity ?? ''),
                (string)($contactState ?? ''),
                (string)($contactCountry ?? '')
            );
        }
        if ($region === null && !$contactFound && function_exists('infer_region_from_parts') &&
            ($candCity || $candState || $candCountry)) {
            $region = infer_region_from_parts(
                (string)($candCity ?? ''),
                (string)($candState ?? ''),
                (string)($candCountry ?? '')
            );
        }
        if ($region === null && $vars['location'] !== null && function_exists('infer_region_from_location')) {
            $region = infer_region_from_location($vars['location']);
        }
    } catch (Throwable $e) {
        $region = null;
    }
    $vars['region'] = $region ?: null;

    if (!empty($vars['contact_local_time'])) {
        $vars['local_part_of_day'] = derive_part_of_day($vars['contact_local_time']);
    }

    if (empty($vars['contact_function'])) {
        $vars['contact_function'] = derive_function_slug($vars['title']);
    }

    $vars['pain_point_snippet'] = safe_ellipsis($vars['pain_point_snippet'], 120);
    $vars['value_prop_snippet'] = safe_ellipsis($vars['value_prop_snippet'], 120);

    return $vars;
}

// -------------------------------
// Helpers
// -------------------------------

function default_generic_text(array $vars, bool $includeSmalltalk, bool $includeMicroOffer): string {
    // NOTE: Canonical-only system should never use this.
    // Kept for compatibility if some other caller uses it directly.
    $s = [];
    $greeting = $vars['contact_first'] ? "Hi {$vars['contact_first']}," : "Hi there,";
    $touchTag = isset($vars['touch_number']) ? " (Touch {$vars['touch_number']})" : '';
    $s[] = $greeting . " okay to take 30 seconds?" . $touchTag;

    $line = 'Quick note on your hiring priorities.';
    if (!empty($vars['top_open_role']) && !empty($vars['days_open_top_role'])) {
        $line = "Quick note on your {$vars['top_open_role']}—looks open ~{$vars['days_open_top_role']} days.";
    }
    $s[] = $line;

    $val = 'We typically shorten time-to-slate by tightening intake and aligning early.';
    $s[] = $val;

    if ($includeSmalltalk && !empty($vars['contact_local_time'])) {
        $s[] = "By the way—it’s {$vars['contact_local_time']} your time; I’ll keep it brief.";
    }
    if ($includeMicroOffer) {
        $s[] = 'I can share a one-pager with our intake checklist.';
    }
    $s[] = 'Would it help if I sent a one-pager?';
    return implode("\n", $s);
}

function map_outreach_stage_to_slug(int $stageNum): string {
    if ($stageNum >= 1 && $stageNum <= 3) return 'cold';
    if ($stageNum >= 4 && $stageNum <= 6) return 'open';
    if ($stageNum >= 7) return 'followup';
    return 'open';
}

function compute_days_open(?string $createdAt): ?int {
    if (!$createdAt) return null;
    try {
        $start = new DateTime($createdAt);
        $now   = new DateTime('now');
        $diff  = $start->diff($now);
        return max(0, (int)$diff->days);
    } catch (Throwable $e) {
        return null;
    }
}

function derive_part_of_day(string $hhmm_ampm): ?string {
    if (!preg_match('/(\d{1,2}):\d{2}\s*(AM|PM)/i', $hhmm_ampm, $m)) return null;
    $h = (int)$m[1];
    $ampm = strtoupper($m[2]);
    if ($ampm === 'PM' && $h !== 12) $h += 12;
    if ($ampm === 'AM' && $h === 12) $h = 0;

    if ($h < 12) return 'morning';
    if ($h < 17) return 'afternoon';
    return 'evening';
}

function derive_function_slug(?string $title): string {
    $t = strtolower((string)$title);
    if ($t === '') return 'other';

    if (strpos($t, 'hr') !== false || strpos($t, 'human resources') !== false || strpos($t, 'talent') !== false) {
        return 'hr';
    }
    if (strpos($t, 'finance') !== false || strpos($t, 'account') !== false) {
        return 'finance';
    }
    if (strpos($t, 'engineer') !== false || strpos($t, 'engineering') !== false || strpos($t, 'cto') !== false || strpos($t, 'developer') !== false) {
        return 'engineering';
    }
    if (strpos($t, 'operations') !== false || strpos($t, 'ops') !== false || strpos($t, 'plant') !== false || strpos($t, 'manufactur') !== false) {
        return 'ops';
    }
    return 'other';
}

function safe_ellipsis(?string $str, int $maxLen): ?string {
    if ($str === null) return null;
    $s = trim($str);
    if ($s === '') return null;
    if (mb_strlen($s) <= $maxLen) return $s;
    return mb_substr($s, 0, $maxLen - 1) . '…';
}

function mustache_render(string $tpl, array $vars): string {
    $map = flatten_vars_for_render($vars);

    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)(\|[a-zA-Z]+[0-9]*)?\s*\}\}/u', function($m) use ($map) {
        $key  = $m[1];
        $pipe = isset($m[2]) ? ltrim($m[2], '|') : null;

        if (!array_key_exists($key, $map) || (string)$map[$key] === '') {
            // Keep unresolved moustache tokens as-is; canonical single-brace tokens are handled separately.
            return '{{' . $key . '}}';
        }

        $val = (string)$map[$key];
        if ($pipe) {
            $val = apply_pipe($val, $pipe);
        }
        return $val;
    }, $tpl);
}

function apply_pipe(string $val, string $pipe): string {
    if ($val === '') return '';
    $name = strtolower($pipe);

    if ($name === 'title') {
        return mb_convert_case($val, MB_CASE_TITLE, "UTF-8");
    }
    if ($name === 'upper') {
        return mb_strtoupper($val, 'UTF-8');
    }
    if ($name === 'lower') {
        return mb_strtolower($val, 'UTF-8');
    }
    if (preg_match('/^ellipsis(\d{2,3})$/', $name, $mm)) {
        $n = (int)$mm[1];
        return safe_ellipsis($val, $n) ?? '';
    }
    if ($name === 'approx') {
        if (preg_match('/^\d+$/', $val)) return "~{$val}";
        return $val;
    }
    return $val;
}

function flatten_vars_for_render(array $vars): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($vars));
    foreach ($it as $leaf) {
        $keys = [];
        foreach (range(0, $it->getDepth()) as $depth) {
            $keys[] = $it->getSubIterator($depth)->key();
        }
        $path = implode('.', $keys);
        $out[$path] = (string)$leaf;
    }
    foreach ($vars as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $out[$k] = (string)$v;
        }
    }
    return $out;
}

function cleanup_text(string $txt): string {
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);
    $lines = array_map(function($l){ return rtrim($l, " \t"); }, explode("\n", $txt));
    $clean = [];
    $prevBlank = false;
    foreach ($lines as $l) {
        $isBlank = (trim($l) === '');
        if ($isBlank && $prevBlank) {
            continue;
        }
        $clean[] = $l;
        $prevBlank = $isBlank;
    }
    return trim(implode("\n", $clean));
}

/**
 * Detect delivery type.
 * Canonical:
 * - cadence_voicemail => voicemail post-processing
 * - cadence_email / cadence_linkedin => treat as live for post-processing (strip VM-only parts)
 */
function detect_delivery_type(array $ctx, string $scriptType, array $vars = []): string {
    $st = strtolower((string)$scriptType);

    if ($st === 'pipeline') {
        $kind = canonical_content_kind_for_touch((int)($vars['touch_number'] ?? 1));
        if ($kind === 'cadence_voicemail') return 'voicemail';
        return 'live';
    }

    // Non-pipeline: still only canonical kinds.
    $kind = canonical_content_kind_for_context($scriptType, (int)($vars['touch_number'] ?? 1), $vars, $ctx);
    if ($kind === 'cadence_voicemail') return 'voicemail';
    return 'live';
}

/**
 * Post-process the rendered body for delivery type and remove timing lines.
 */
function post_process_delivery_text(string $raw, string $deliveryType): string {
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    $raw = preg_replace('/^\s*⏱.*$/mu', '', $raw);

    if ($deliveryType === 'voicemail') {
        $raw = preg_replace('/^(\s*)VM-\s*/mu', '$1', $raw);
        $raw = preg_replace('/\sVM-\s*/m', ' ', $raw);
    } else {
        $raw = preg_replace('/^\s*VM-.*$/mu', '', $raw);
        $raw = preg_replace('/\sVM-.*$/m', '', $raw);
    }

    $raw = preg_replace("/\n{3,}/", "\n\n", $raw);

    return trim($raw);
}
