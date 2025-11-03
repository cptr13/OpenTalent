<?php
// includes/script_renderer.php
// Deterministic renderer for dynamic, tone-aware scripts (no AI).

if (!defined('OT2_LOADED')) {
    define('OT2_LOADED', true);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/script_rules.php';

// Optional region helper (safe if absent)
if (file_exists(__DIR__ . '/geo_region.php')) {
    require_once __DIR__ . '/geo_region.php';
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
    $scriptType = trim((string)($ctx['script_type_slug'] ?? ''));
    if ($scriptType === '') {
        return [
            'text' => "Quick note on your hiring priorities.\nHappy to share a one-pager or schedule 15 minutes.",
            'tone_used' => 'consultative',
            'template_name' => 'generic',
            'context' => [],
            'missing' => ['script_type_slug'],
            'warnings' => ['script_type_missing_generic_used']
        ];
    }

    $contactId   = isset($ctx['contact_id']) ? (int)$ctx['contact_id'] : null;
    $clientId    = isset($ctx['client_id']) ? (int)$ctx['client_id'] : null;
    $jobId       = isset($ctx['job_id']) ? (int)$ctx['job_id'] : null;
    $candidateId = isset($ctx['candidate_id']) ? (int)$ctx['candidate_id'] : null; // candidate fallback

    $toneMode  = strtolower((string)($ctx['tone_mode'] ?? 'auto'));
    if (!in_array($toneMode, ['auto','friendly','consultative','direct'], true)) {
        $toneMode = 'auto';
    }

    $includeSmalltalk  = array_key_exists('include_smalltalk', $ctx) ? (bool)$ctx['include_smalltalk'] : true;
    $includeMicroOffer = array_key_exists('include_micro_offer', $ctx) ? (bool)$ctx['include_micro_offer'] : true;

    // 2) Gather facts (now with candidate fallback + location/region derivation)
    $vars = hydrate_vars($contactId, $clientId, $jobId, $candidateId);

    // 3) Determine cadence + touch (UI may omit them; infer from contact row)
    $cadenceFromCtx = isset($ctx['cadence_type']) ? strtolower((string)$ctx['cadence_type']) : null;
    if ($cadenceFromCtx !== null && !in_array($cadenceFromCtx, ['voicemail','mixed'], true)) {
        $cadenceFromCtx = 'voicemail';
    }
    $cadenceType = $cadenceFromCtx
        ?? (isset($vars['outreach_cadence']) && in_array(strtolower((string)$vars['outreach_cadence']), ['voicemail','mixed'], true)
                ? strtolower((string)$vars['outreach_cadence'])
                : 'voicemail');

    $touchFromCtx = isset($ctx['touch_number']) ? (int)$ctx['touch_number'] : null;
    $touchNumber  = $touchFromCtx && $touchFromCtx > 0
        ? $touchFromCtx
        : (isset($vars['outreach_stage_num']) && (int)$vars['outreach_stage_num'] > 0
            ? (int)$vars['outreach_stage_num']
            : 1);

    // Ensure cadence + touch are in the context for templates and for tone rules
    $vars['cadence_type']        = $cadenceType;
    $vars['touch_number']        = $touchNumber;
    $vars['attempt_count_total'] = max(1, (int)$touchNumber); // tone reacts to later touches

    // Optional: expose a channel if rules define it (e.g., 'phone' or 'email')
    if (function_exists('script_channel_for')) {
        try {
            $channel = script_channel_for($scriptType, $cadenceType, $touchNumber, $vars);
            if (is_string($channel) && $channel !== '') {
                $vars['channel'] = $channel;
            }
        } catch (Throwable $e) {
            // Non-fatal
        }
    }

    // ----------------------------
    // 4) Tone selection — DROPDOWN WINS
    // ----------------------------
    $toneUsed = null;
    $toneMap  = [];

    if ($toneMode !== 'auto') {
        // Explicit selection: keep tone_used as requested even if we must fallback phrases
        $toneUsed = $toneMode;
        $toneMap  = build_tone_map($pdo, $toneMode, $vars);
        if (!$toneMap) {
            // Use consultative phrases if the selected kit is missing, but DO NOT change tone_used
            $fallbackPhraseTone = 'consultative';
            $toneMap = build_tone_map($pdo, $fallbackPhraseTone, $vars);
            if (!$toneMap) {
                $toneMap = []; // handled a bit later
            }
        }
    } else {
        // Auto inference by stage/persona
        $autoTone = pick_tone(
            null,
            $vars['outreach_stage_slug'] ?? null,
            (int)($vars['attempt_count_total'] ?? 1),
            $vars['contact_function'] ?? null
        );
        $toneUsed = $autoTone;
        $toneMap  = build_tone_map($pdo, $autoTone, $vars);
        if (!$toneMap) {
            $toneUsed = 'consultative';
            $toneMap  = build_tone_map($pdo, 'consultative', $vars);
        }
    }

    // 5) Resolve template variant (cadence + touch) OR fall back to DB template
    $variant = resolve_template_variant($pdo, $scriptType, $cadenceType, $touchNumber, $vars, $toneUsed);
    $templateName = null;
    $body         = null;

    if ($variant) {
        $templateName = $variant['name'];
        $body         = (string)$variant['body'];
    } else {
        // Existing behavior: use the active template by type slug (single body)
        $tpl = get_active_template_by_type_slug($pdo, $scriptType);
        if ($tpl) {
            $templateName = $tpl['name'] . ' (v' . (int)$tpl['version'] . ')';
            $body         = (string)$tpl['body'];
        }
    }

    // 6) If no template at all, use generic
    if ($body === null) {
        return [
            'text' => cleanup_text(default_generic_text($vars, $includeSmalltalk, $includeMicroOffer)),
            'tone_used' => $toneUsed ?? 'consultative',
            'template_name' => 'generic_fallback',
            'context' => $vars,
            'missing' => $missing,
            'warnings' => array_merge($warnings, ['template_missing_generic_used'])
        ];
    }

    // 7) Flags / optional inserts
    $momentSmalltalk = '';
    if ($includeSmalltalk && !empty($vars['contact_local_time'])) {
        $momentSmalltalk = "By the way—it’s {$vars['contact_local_time']} your time; I’ll keep it brief.";
    } elseif ($includeSmalltalk) {
        $warnings[] = 'timezone_missing_smalltalk_suppressed';
    }

    $microOffer = '';
    if ($includeMicroOffer) {
        $stage = $vars['outreach_stage_slug'] ?? 'open';
        if (in_array($stage, ['cold','open'], true)) {
            $microOffer = "I can share a one-pager with our intake checklist.";
        } elseif ($stage === 'followup') {
            $microOffer = "We can deliver an initial slate in ~5 business days.";
        }
    }

    // 8) Compose render data
    $data = $vars;
    foreach ($toneMap as $k => $v) {
        $data["tone.$k"] = $v;
    }
    $data['moment_smalltalk'] = $momentSmalltalk;
    $data['micro_offer']      = $microOffer;

    // 9) Render template (supports dotted keys + simple pipes)
    $rendered = mustache_render($body, $data);

    // 9.1) Post-process for delivery type (Voicemail vs Live Call), and drop timing lines.
    $delivery = detect_delivery_type($ctx, $scriptType);
    $rendered = post_process_delivery_text($rendered, $delivery);

    // 10) Post-cleanup & line collapsing for missing role/days
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
 * Resolve a per-touch template variant if available.
 * Tries a code-defined variant (script_rules.php) first, then DB lookups in script_templates_unified.
 * Return shape: ['name' => string, 'body' => string]
 */
function resolve_template_variant(PDO $pdo, string $scriptType, string $cadenceType, int $touchNumber, array $vars, ?string $toneUsed = null): ?array
{
    // 1) Code-defined variants (lets us iterate without DB churn)
    if (function_exists('script_template_for')) {
        try {
            // Back-compat shim: if script_template_for supports a 5th param (tone override), use it.
            $res = null;
            try {
                $rf = new ReflectionFunction('script_template_for');
                $argc = $rf->getNumberOfParameters();
                if ($argc >= 5) {
                    // New signature: (type, cadence, touch, vars, toneOverride)
                    $res = script_template_for($scriptType, $cadenceType, $touchNumber, $vars, $toneUsed);
                } else {
                    // Old signature: (type, cadence, touch, vars)
                    $res = script_template_for($scriptType, $cadenceType, $touchNumber, $vars);
                }
            } catch (Throwable $e) {
                // If Reflection fails for any reason, fall back to 4-arg call
                $res = script_template_for($scriptType, $cadenceType, $touchNumber, $vars);
            }

            if (is_array($res) && isset($res['body'])) {
                $name = $res['name'] ?? ($scriptType . ':' . $cadenceType . ':T' . $touchNumber);
                return ['name' => $name, 'body' => (string)$res['body']];
            }
        } catch (Throwable $e) {
            // If a template function explodes, do not kill render; just fall back
        }
    }

    // 2) DB variants (script_templates_unified)
    // Derive a delivery "kind" from scriptType (voicemail vs live/call).
    $delivery = infer_delivery_from_script_type($scriptType);

    // Normalize acceptable content_kind values we’ll accept in queries for each delivery
    $kinds = delivery_to_kind_set($delivery); // e.g. ['voicemail','voicemail_script'] or ['live','live_call','cold_call','live_script']

    // Preferred tone is whatever we ended up using (dropdown wins if set)
    $tone = $toneUsed ?: 'consultative';

    // Attempt 1: strict match on kind + touch_number + tone_default + active
    $row = find_unified_variant($pdo, $kinds, $touchNumber, $tone);
    if ($row) {
        return [
            'name' => build_unified_name($row, $delivery, $touchNumber, $tone),
            'body' => (string)$row['body'],
        ];
    }

    // Attempt 2: same kind + touch_number, but any tone (prefer consultative if present)
    $row = find_unified_variant_any_tone($pdo, $kinds, $touchNumber);
    if ($row) {
        return [
            'name' => build_unified_name($row, $delivery, $touchNumber, $row['tone_default'] ?? 'consultative'),
            'body' => (string)$row['body'],
        ];
    }

    // Attempt 3: same kind + tone, but without touch_number (NULL or 0 as generic template)
    $row = find_unified_variant_no_touch($pdo, $kinds, $tone);
    if ($row) {
        return [
            'name' => build_unified_name($row, $delivery, $touchNumber, $tone),
            'body' => (string)$row['body'],
        ];
    }

    // Attempt 4: same kind only (no touch, any tone), pick most recently updated
    $row = find_unified_variant_kind_only($pdo, $kinds);
    if ($row) {
        return [
            'name' => build_unified_name($row, $delivery, $touchNumber, $row['tone_default'] ?? 'consultative'),
            'body' => (string)$row['body'],
        ];
    }

    // Nothing in unified; allow caller to fall back to legacy single-body template
    return null;
}

/** Pick delivery (voicemail|live) from scriptType heuristics */
function infer_delivery_from_script_type(string $scriptType): string {
    $s = strtolower($scriptType);
    if ($s === 'voicemail' || strpos($s, 'voice') !== false || $s === 'vm') return 'voicemail';
    // everything else treats as "live" (cold_call, live_call, call, etc.)
    return 'live';
}

/** Map delivery to acceptable content_kind values (handles different labels you might have used). */
function delivery_to_kind_set(string $delivery): array {
    if ($delivery === 'voicemail') {
        return ['voicemail', 'voicemail_script', 'vm'];
    }
    // live/call family
    return ['live', 'live_call', 'call', 'cold_call', 'live_script'];
}

/** Build a readable template name for debugging/UI */
function build_unified_name(array $row, string $delivery, int $touchNumber, string $tone): string {
    $kind = $row['content_kind'] ?? $delivery;
    $tn   = (string)($row['touch_number'] ?? $touchNumber);
    $t    = (string)($row['tone_default'] ?? $tone);
    $id   = (string)($row['id'] ?? '?');
    return "unified:{$kind}:T{$tn}:tone={$t} #{$id}";
}

/** Strict match: kind IN (…) AND touch_number = ? AND tone_default = ? AND status='active' */
function find_unified_variant(PDO $pdo, array $kinds, int $touchNumber, string $tone) {
    $in = implode(',', array_fill(0, count($kinds), '?'));
    $sql = "
        SELECT id, content_kind, touch_number, tone_default, body, status, updated_at
        FROM script_templates_unified
        WHERE status = 'active'
          AND content_kind IN ($in)
          AND (touch_number = ?)
          AND LOWER(tone_default) = LOWER(?)
        ORDER BY updated_at DESC
        LIMIT 1
    ";
    $params = array_merge($kinds, [$touchNumber, $tone]);
    return db_first_row($pdo, $sql, $params);
}

/** Same kind + touch_number, any tone (prefer consultative if tied by updated_at) */
function find_unified_variant_any_tone(PDO $pdo, array $kinds, int $touchNumber) {
    $in = implode(',', array_fill(0, count($kinds), '?'));
    $sql = "
        SELECT id, content_kind, touch_number, tone_default, body, status, updated_at
        FROM script_templates_unified
        WHERE status = 'active'
          AND content_kind IN ($in)
          AND (touch_number = ?)
        ORDER BY (LOWER(tone_default) = 'consultative') DESC, updated_at DESC
        LIMIT 1
    ";
    $params = array_merge($kinds, [$touchNumber]);
    return db_first_row($pdo, $sql, $params);
}

/** Same kind + tone, but no touch_number (NULL or 0) */
function find_unified_variant_no_touch(PDO $pdo, array $kinds, string $tone) {
    $in = implode(',', array_fill(0, count($kinds), '?'));
    $sql = "
        SELECT id, content_kind, touch_number, tone_default, body, status, updated_at
        FROM script_templates_unified
        WHERE status = 'active'
          AND content_kind IN ($in)
          AND (touch_number IS NULL OR touch_number = 0)
          AND LOWER(tone_default) = LOWER(?)
        ORDER BY updated_at DESC
        LIMIT 1
    ";
    $params = array_merge($kinds, [$tone]);
    return db_first_row($pdo, $sql, $params);
}

/** Same kind only, any tone, pick most recent */
function find_unified_variant_kind_only(PDO $pdo, array $kinds) {
    $in = implode(',', array_fill(0, count($kinds), '?'));
    $sql = "
        SELECT id, content_kind, touch_number, tone_default, body, status, updated_at
        FROM script_templates_unified
        WHERE status = 'active'
          AND content_kind IN ($in)
        ORDER BY updated_at DESC
        LIMIT 1
    ";
    return db_first_row($pdo, $sql, $kinds);
}

/** Fetch first row helper */
function db_first_row(PDO $pdo, string $sql, array $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Load facts from DB and compute derived fields.
 *
 * Adds:
 *  - location (best-effort: contact addr → client.location → job.location → candidate city/state)
 *  - region (derived via infer_region_from_parts or infer_region_from_location)
 *  - placeholder parity keys for template convenience
 *
 * Candidate fallback (if no contact data):
 *   contact_first  <- candidates.first_name (else "there")
 *   contact_title  <- candidates.current_job (if present)
 *   contact_function <- derived from title
 */
function hydrate_vars(?int $contactId, ?int $clientId, ?int $jobId, ?int $candidateId = null): array {
    global $pdo;

    // Seed with nulls so tokens are visible when missing
    $vars = [
        // Person (contact-first semantics)
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

        // Also expose contact address fields directly for templates
        'address_street'       => null,
        'address_city'         => null,
        'address_state'        => null,
        'address_zip'          => null,
        'address_country'      => null,

        // Company (client)
        'company'              => null,      // legacy alias
        'company_name'         => null,      // preferred
        'industry'             => null,
        'client_location'      => null,      // raw clients.location
        'location'             => null,      // computed best-effort
        'region'               => null,      // derived from location

        // Job bits
        'top_open_role'        => null,
        'job_location'         => null,
        'days_open_top_role'   => null,

        // Control & snippets
        'attempt_count_total'  => 1,
        'outreach_stage'       => null,      // raw numeric from contacts
        'outreach_stage_slug'  => 'open',
        'outreach_stage_num'   => null,
        'outreach_cadence'     => null,
        'contact_local_time'   => null,
        'local_part_of_day'    => null,
        'pain_point_snippet'   => null,
        'value_prop_snippet'   => null,

        // Your info (session/system)
        'your_name'            => null,
        'user_first'           => null,
        'your_agency'          => null,
        'my_phone'             => null,
        'user_phone'           => null,
        'my_email'             => null,
        'calendar_link'        => null,
    ];

    // Current user info
    if (!empty($_SESSION['user'])) {
        $full = trim((string)($_SESSION['user']['full_name'] ?? '')) ?: null;
        $vars['your_name'] = $full;
        $vars['user_first'] = $full ? preg_replace('/\s+.*/', '', $full) : null; // first token
        $vars['my_email']  = $_SESSION['user']['email'] ?? null;
        $vars['my_phone']  = $_SESSION['user']['phone'] ?? null;
        $vars['user_phone'] = $vars['my_phone']; // alias used in scripts
    }

    // Agency / company name from system_settings (single row)
    try {
        $stmt = $pdo->query("SELECT company_name FROM system_settings LIMIT 1");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vars['your_agency'] = trim((string)($row['company_name'] ?? '')) ?: null;
        }
    } catch (Throwable $e) {
        // non-fatal
    }

    $contactFound = false;

    // Holders for parts so we can prefer infer_region_from_parts
    $contactCity = $contactState = $contactCountry = null;
    $candCity = $candState = $candCountry = null;
    $street = $city = $state = $zip = $country = null;

    // Contact
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

            // Person
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

            // Greeting safety
            $vars['contact_first'] = $vars['first_name'] ?: 'there';

            // Company (client)
            $vars['company_name'] = trim((string)($c['client_name'] ?? '')) ?: $vars['company_name'];
            $vars['company']      = $vars['company_name']; // legacy alias
            $vars['industry']     = trim((string)($c['client_industry'] ?? '')) ?: $vars['industry'];

            // Outreach stage mapping (numeric → slug)
            $stageNum = (int)($c['outreach_stage'] ?? 0);
            $vars['outreach_stage']      = $stageNum ?: null;
            $vars['outreach_stage_num']  = $stageNum ?: null;
            $vars['outreach_stage_slug'] = map_outreach_stage_to_slug($stageNum);

            // Optional cadence column on contacts (e.g., 'voicemail' or 'mixed')
            if (array_key_exists('outreach_cadence', $c)) {
                $cad = strtolower((string)$c['outreach_cadence']);
                $vars['outreach_cadence'] = in_array($cad, ['voicemail','mixed'], true) ? $cad : null;
            }

            // Contact address parts (store parts for region inference AND expose to templates)
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

            // Client.location as fallback later
            $vars['client_location']           = trim((string)($c['client_location'] ?? '')) ?: null;
            $vars['_client_location_fallback'] = $vars['client_location'];

            // Local time placeholder (if you later add tz per-contact, compute here)
            $vars['contact_local_time'] = null;
        }
    }

    // Candidate fallback (only if no contact resolved)
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

            // Candidate location parts
            $candCity    = ($cand['city']    ?? '') !== '' ? trim((string)$cand['city'])    : null;
            $candState   = ($cand['state']   ?? '') !== '' ? trim((string)$cand['state'])   : null;
            $candCountry = ($cand['country'] ?? '') !== '' ? trim((string)$cand['country']) : null;

            // Candidate location fallback string
            $candLocParts = [];
            foreach ([$candCity,$candState,$candCountry] as $fldVal) {
                if (!empty($fldVal)) $candLocParts[] = $fldVal;
            }
            $vars['_candidate_location_fallback'] = $candLocParts ? implode(', ', $candLocParts) : null;
        }
    }

    // Client (explicit param can override names/industry and add location fallback)
    if ($clientId) {
        $stmt = $pdo->prepare("SELECT name, industry, location FROM clients WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $clientId]);
        if ($cl = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vars['company_name'] = trim((string)($cl['name'] ?? '')) ?: $vars['company_name'];
            $vars['company']      = $vars['company_name']; // legacy alias
            $vars['industry']     = trim((string)($cl['industry'] ?? '')) ?: $vars['industry'];
            $vars['client_location'] = trim((string)($cl['location'] ?? '')) ?: $vars['client_location'];
            $vars['_client_location_param'] = $vars['client_location'];
        }
    }

    // Job (title, created_at for days open, plus job.location if present)
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

    // -------- Location assembly (best-effort) --------
    // Priority:
    // 1) Contact address (street, city, state, zip, country) if present
    // 2) Client.location (from contact join)
    // 3) Client.location (from explicit client param)
    // 4) Job.location
    // 5) Candidate city/state/country
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

    // -------- Region derivation (prefer parts, else free-form) --------
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

    // Compute local part of day if local time exists
    if (!empty($vars['contact_local_time'])) {
        $vars['local_part_of_day'] = derive_part_of_day($vars['contact_local_time']);
    }

    // Persona derivation from title/department if still empty
    if (empty($vars['contact_function'])) {
        $vars['contact_function'] = derive_function_slug($vars['title']);
    }

    // Snippets: trim/limit
    $vars['pain_point_snippet'] = safe_ellipsis($vars['pain_point_snippet'], 120);
    $vars['value_prop_snippet'] = safe_ellipsis($vars['value_prop_snippet'], 120);

    return $vars;
}

/**
 * Build tone map by slug, rendering each phrase with the available vars.
 * @return array<string,string>
 */
function build_tone_map(PDO $pdo, string $toneSlug, array $vars): array {
    $kit = get_tone_kit_by_slug($pdo, $toneSlug);
    if (!$kit) return [];

    $phrases = get_tone_phrases_map($pdo, (int)$kit['id']);
    $map = [];
    foreach ($phrases as $k => $txt) {
        $map[$k] = mustache_render($txt, flatten_vars_for_render($vars));
    }
    return $map;
}

// -------------------------------
// Helpers
// -------------------------------

function default_generic_text(array $vars, bool $includeSmalltalk, bool $includeMicroOffer): string {
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
    // Expect like "10:12 AM"
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

/**
 * Basic mustache-like renderer with token-on-empty policy:
 * - Supports dotted keys (e.g., "tone.greeting")
 * - Supports simple pipes: {{var|title}}, {{var|upper}}, {{var|lower}}, {{var|ellipsis120}}, {{var|approx}}
 * - If a key is missing or resolves to an empty string, we return the literal token "{{key}}"
 *   (pipes are ignored in that case; you’ll still see "{{key}}").
 */
function mustache_render(string $tpl, array $vars): string {
    $map = flatten_vars_for_render($vars);

    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)(\|[a-zA-Z]+[0-9]*)?\s*\}\}/u', function($m) use ($map) {
        $key  = $m[1];
        $pipe = isset($m[2]) ? ltrim($m[2], '|') : null;

        // If key not present OR value empty → show the literal token
        if (!array_key_exists($key, $map) || (string)$map[$key] === '') {
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

/**
 * Flatten array (including dotted keys already present).
 */
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
    // Also copy top-level scalar keys as-is.
    foreach ($vars as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $out[$k] = (string)$v;
        }
    }
    return $out;
}

/**
 * Cleanup whitespace and blank lines for display/print.
 */
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
 * Detect delivery type from context and scriptType.
 * Returns 'voicemail' or 'live'.
 */
function detect_delivery_type(array $ctx, string $scriptType): string {
    $raw = strtolower((string)($ctx['delivery_type'] ?? $scriptType));
    // Normalize common inputs
    if (strpos($raw, 'voice') !== false || $raw === 'vm') {
        return 'voicemail';
    }
    if (strpos($raw, 'cold') !== false || strpos($raw, 'live') !== false || strpos($raw, 'call') !== false) {
        return 'live';
    }
    // Fallback: treat unknowns as voicemail (safer to include contact info there)
    return 'voicemail';
}

/**
 * Post-process the rendered body for delivery type and remove timing lines.
 * - For voicemail: keep VM-only snippets, but strip the "VM-" label.
 * - For live: drop VM-only snippets (lines starting with "VM-" or inline tails " VM-...").
 * - Remove lines that start with the stopwatch emoji (⏱).
 */
function post_process_delivery_text(string $raw, string $deliveryType): string {
    // Normalize line endings first
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    // Remove timing lines (any line starting with the stopwatch emoji)
    $raw = preg_replace('/^\s*⏱.*$/mu', '', $raw);

    if ($deliveryType === 'voicemail') {
        // Keep the content, but remove the "VM-" label wherever it appears as a marker.
        // 1) Leading "VM-" at line start
        $raw = preg_replace('/^(\s*)VM-\s*/mu', '$1', $raw);
        // 2) Inline " VM-" before the voicemail-only tail
        $raw = preg_replace('/\sVM-\s*/m', ' ', $raw);
    } else {
        // Live/Cold Call: remove VM-only content
        // 1) Remove entire lines that begin with VM-
        $raw = preg_replace('/^\s*VM-.*$/mu', '', $raw);
        // 2) Remove inline tails beginning with " VM-" to end-of-line
        $raw = preg_replace('/\sVM-.*$/m', '', $raw);
    }

    // Collapse 3+ blank lines to max 1 blank line
    $raw = preg_replace("/\n{3,}/", "\n\n", $raw);

    return trim($raw);
}
