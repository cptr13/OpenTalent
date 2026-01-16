<?php
// includes/script_rules.php
// Data-access helpers for the Dynamic Script Personalization engine.

if (!defined('OT2_LOADED')) {
    define('OT2_LOADED', true);
}

require_once __DIR__ . '/../config/database.php';

/**
 * Get active legacy template (highest version) by script type slug.
 * Used ONLY as a fallback if no unified row exists.
 * @return array|null ['id','name','version','body','status','script_type_id']
 */
function get_active_template_by_type_slug(PDO $pdo, string $typeSlug): ?array {
    $sql = "
        SELECT t.*
        FROM script_templates t
        INNER JOIN script_types st ON st.id = t.script_type_id
        WHERE st.slug = :slug AND t.status = 'active'
        ORDER BY t.version DESC, t.updated_at DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $typeSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Get tone kit row by slug.
 * @return array|null
 */
function get_tone_kit_by_slug(PDO $pdo, string $toneSlug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM tone_kits WHERE slug = :slug AND is_active = 1 ORDER BY version DESC LIMIT 1");
    $stmt->execute([':slug' => $toneSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Get tone phrases map keyed by 'key' => 'text'.
 * @return array<string,string>
 */
function get_tone_phrases_map(PDO $pdo, int $toneKitId): array {
    $stmt = $pdo->prepare("SELECT `key`, text FROM tone_phrases WHERE tone_kit_id = :id");
    $stmt->execute([':id' => $toneKitId]);
    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out[$r['key']] = (string)$r['text'];
    }
    return $out;
}

/* -----------------------------------------------------------
 * Title→Tone: STRICTLY by contact title (your rule).
 * -----------------------------------------------------------
 */

/**
 * Map a contact title to a tone slug strictly by title keywords.
 * Returns: 'friendly' | 'consultative' | 'direct'
 */
function tone_from_title(?string $title): string {
    $t = mb_strtolower(trim((string)$title), 'UTF-8');

    if ($t === '' || $t === '0') {
        // No title → default to consultative
        return 'consultative';
    }

    // If it's HR / People Ops / Talent oriented → friendly
    if (preg_match('/\b(hr|human resources|people ops?|people operations|talent|recruit(ing|er)?|acquisition)\b/i', $title)) {
        return 'friendly';
    }

    // Exec / senior leadership → direct
    if (preg_match('/\b(ceo|cfo|coo|cto|cpo|chief|president|owner|founder|vp|vice\s*president|evp|svp|avp|head|board|chair|principal|executive|managing\s*director)\b/i', $title)) {
        return 'direct';
    }

    // Everyone else (managers, SMEs, engineers, ops, plant, finance, etc.) → consultative
    return 'consultative';
}

/* -----------------------------------------------------------
 * Unified template accessors
 * -----------------------------------------------------------
 */

/**
 * Fetch a unified template row by (content_kind, touch_number, tone_default).
 *
 * content_kind values (CURRENT CANONICAL for your unified pipeline):
 *  - 'live_script'
 *  - 'voicemail'
 *  - 'cadence_email'
 *  - 'cadence_linkedin'
 *
 * tone_default: 'friendly' | 'consultative' | 'direct'
 * @return array|null ['template_slug','content_kind','touch_number','tone_default','subject','body','version','status']
 */
function get_unified_template(PDO $pdo, string $contentKind, int $touchNumber, string $toneSlug): ?array {
    $sql = "
        SELECT template_slug, content_kind, touch_number, tone_default, subject, body, version, status
        FROM script_templates_unified
        WHERE content_kind = :kind
          AND status = 'active'
          AND tone_default = :tone
          AND touch_number = :touch
        ORDER BY version DESC, template_slug ASC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':kind'  => $contentKind,
        ':tone'  => $toneSlug,
        ':touch' => (int)$touchNumber
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/* -----------------------------------------------------------
 * Cadence utilities (kept for compatibility)
 * -----------------------------------------------------------
 */

/**
 * For the "mixed" cadence, map each touch to its intended channel.
 * Touch plan (example):
 *   1 Email, 2 LinkedIn, 3 Email, 4 LinkedIn, 5 Email, 6 LinkedIn,
 *   7 Email, 8 LinkedIn, 9 Email, 10 LinkedIn, 11 Call/Voicemail, 12 Email
 *
 * @return 'email'|'linkedin'|'call'
 */
function script_channel_for(string $cadence, int $touch): string {
    $cadence = strtolower($cadence);
    $touch   = max(1, (int)$touch);

    if ($cadence !== 'mixed') {
        // For pure voicemail cadence, always a call channel
        return 'call';
    }

    // Mixed cadence routing:
    if ($touch === 11) return 'call';

    // Odd = Email, Even = LinkedIn (except 11 handled above)
    if ($touch % 2 === 1) return 'email';
    return 'linkedin';
}

/* -----------------------------------------------------------
 * Pipeline routing (CANONICAL)
 * Pipeline = "show the current touch script" using outreach_stage.
 * Touch→content_kind mapping follows your authoritative 12-touch cadence:
 *
 * Touch  Channel / content_kind
 * 1      voicemail
 * 2      cadence_email
 * 3      live_script
 * 4      cadence_linkedin
 * 5      voicemail
 * 6      cadence_email
 * 7      live_script
 * 8      cadence_email (also usable as LinkedIn DM)
 * 9      voicemail
 * 10     cadence_email
 * 11     live_script (“No Voicemail” body allowed)
 * 12     cadence_email (close-out)
 * -----------------------------------------------------------
 */
function pipeline_content_kind_for_touch(int $touch): string {
    $t = max(1, (int)$touch);

    if ($t === 4) return 'cadence_linkedin';
    if ($t === 3 || $t === 7 || $t === 11) return 'live_script';
    if ($t === 1 || $t === 5 || $t === 9)  return 'voicemail';

    // Touches 2,6,8,10,12 are email-based (touch 8 is dual-use email/DM by your rule)
    return 'cadence_email';
}

/* -----------------------------------------------------------
 * Primary: per-touch template variants
 *  - Uses UNIFIED templates
 *  - Tone chosen STRICTLY by contact title unless an override is provided
 *  - Content kind driven by script type (Cold Call → live_script, Voicemail → voicemail, Pipeline → touch map)
 *  - If not found, return null to let renderer fall back to legacy or generic
 * -----------------------------------------------------------
 */

/**
 * Return:
 *   ['name' => string, 'body' => string]  OR  null to allow DB/generic fallback.
 *
 * Inputs:
 *   $type          : 'cold_call' or 'voicemail' or 'pipeline' (from UI)
 *   $cadence       : 'voicemail' | 'mixed' (kept for compatibility; Pipeline ignores it)
 *   $touch         : outreach touch number (1..12)
 *   $vars          : render vars; we use $vars['title'] for title→tone fallback
 *   $toneOverride  : OPTIONAL manual tone slug from UI dropdown
 *                    ('friendly' | 'consultative' | 'direct' | 'auto')
 */
function script_template_for(string $type, string $cadence, int $touch, array $vars, ?string $toneOverride = null): ?array
{
    global $pdo;

    $type  = strtolower(trim($type));
    $touch = max(1, (int)$touch);

    // Decide tone: override (if valid) → else strict title-based
    $toneSlug = null;
    if ($toneOverride !== null) {
        $t = strtolower(trim($toneOverride));
        if ($t === 'friendly' || $t === 'consultative' || $t === 'direct') {
            $toneSlug = $t; // dropdown wins
        } elseif ($t === 'auto' || $t === '') {
            $toneSlug = tone_from_title($vars['title'] ?? null);
        } else {
            // Unknown override → fall back to title rule
            $toneSlug = tone_from_title($vars['title'] ?? null);
        }
    } else {
        // No override passed → original strict rule
        $toneSlug = tone_from_title($vars['title'] ?? null);
    }

    // Map script type → unified content_kind
    //   'cold_call' → 'live_script'
    //   'voicemail' → 'voicemail'
    //   'pipeline'  → touch-based mapping
    if ($type === 'pipeline') {
        $contentKind = pipeline_content_kind_for_touch($touch);
    } else {
        $contentKind = ($type === 'voicemail') ? 'voicemail' : 'live_script';
    }

    // Try unified template first
    try {
        $row = get_unified_template($pdo, $contentKind, $touch, $toneSlug);
        if ($row && !empty($row['body'])) {
            $name = (string)($row['template_slug'] ?? ($contentKind . "_t{$touch}_{$toneSlug}"));
            return [
                'name' => $name,
                'body' => (string)$row['body']
            ];
        }
    } catch (Throwable $e) {
        // swallow and let legacy fallback
    }

    // No unified match → allow renderer to use legacy template fallback
    return null;
}

/* -----------------------------------------------------------
 * (Optional) Legacy stage/persona rule readers
 * Kept only because the renderer still calls them for tone kits.
 * They no longer influence which unified script template is chosen.
 * -----------------------------------------------------------
 */

/**
 * Find stage rule by stage slug + touch number.
 * @return array|null
 */
function find_stage_rule(PDO $pdo, string $stageSlug, int $touch): ?array {
    $sql = "
        SELECT * FROM script_rules_stage
        WHERE outreach_stage_slug = :slug
          AND :touch BETWEEN touch_min AND touch_max
        ORDER BY touch_min ASC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $stageSlug, ':touch' => $touch]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Find persona rule by function slug.
 * @return array|null
 */
function find_persona_rule(PDO $pdo, string $functionSlug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM script_rules_persona WHERE function_slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $functionSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
