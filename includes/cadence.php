<?php
/**
 * includes/cadence.php
 *
 * Centralized cadence definitions and lookup for outreach stage scheduling.
 *
 * Moving forward there is ONE cadence only: the unified 12-touch integrated cadence.
 *
 * Public API:
 *   cadence_lookup(int $stage, string $cadenceType = 'unified'): array{
 *       label: string,        // Human-readable stage label
 *       delay_bd: int,        // Business days until NEXT touch (0 = end)
 *       flags: array,         // Optional feature flags (e.g., ['li_connect' => true])
 *       channel: string       // 'call'|'email'|'linkedin'|'call_vm'
 *   }
 *
 * Convenience helpers:
 *   cadence_types(): array<string>                    // ['unified']
 *   cadence_touch_count(string $type='unified'):int   // 12
 *   cadence_stage_title(string $type,int $stage):string
 *   cadence_table(string $type='unified'): array<int,array{label:string,delay_bd:int,flags:array,channel:string}>
 */

// ---- Public: list supported cadences ----
function cadence_types(): array {
    return ['unified'];
}

function cadence_touch_count(string $type = 'unified'): int {
    return 12; // unified cadence is 12-touch
}

/**
 * Primary lookup — returns label + business-day delay + flags + channel.
 */
function cadence_lookup(int $stage, string $cadenceType = 'unified'): array
{
    $type = strtolower(trim($cadenceType));
    if (!in_array($type, cadence_types(), true)) {
        // Hard cutover: only unified is supported
        $type = 'unified';
    }

    if ($stage < 1)  $stage = 1;
    if ($stage > 12) $stage = 12;

    $table = cadence_table($type);
    if (isset($table[$stage])) {
        return $table[$stage];
    }

    // Fallback: generic label + end
    return [
        'label'    => "Touch {$stage}",
        'delay_bd' => 0,
        'flags'    => [],
        'channel'  => 'call',
    ];
}

/**
 * Convenience: just the label for a given stage/type.
 */
function cadence_stage_title(string $type, int $stage): string {
    $row = cadence_lookup($stage, $type);
    return $row['label'] ?? ("Touch {$stage}");
}

/**
 * Internal: Build the full table for a cadence type.
 * Keys are 1..12; values include label, delay_bd (business days), flags, and channel.
 */
function cadence_table(string $type = 'unified'): array
{
    $type = strtolower(trim($type));

    // ----- Unified 12-Touch Integrated Cadence -----
    // Absolute day spacing (business days):
    // Day 0  – Call
    // Day 1  – Email
    // Day 2  – Call
    // Day 4  – LinkedIn connection
    // Day 5  – Call
    // Day 7  – Email
    // Day 9  – Call
    // Day 12 – LinkedIn (or Email fallback)
    // Day 14 – Call
    // Day 17 – Email
    // Day 20 – Call
    // Day 22 – Close-the-loop email
    //
    // Delays below are business days to NEXT touch (not absolute days).
    $labels = [
        1  => 'Touch 1 – Call',
        2  => 'Touch 2 – Email / Call (No Email)',
        3  => 'Touch 3 – Call',
        4  => 'Touch 4 – LinkedIn Connection',
        5  => 'Touch 5 – Call',
        6  => 'Touch 6 – Email / Call (No Email)',
        7  => 'Touch 7 – Call',
        8  => 'Touch 8 – LinkedIn / Email (Fallback)',
        9  => 'Touch 9 – Call',
        10 => 'Touch 10 – Email / Call (No Email)',
        11 => 'Touch 11 – Call',
        12 => 'Touch 12 – Close-the-Loop Email',
    ];

    $delays = [
        1  => 1, // Day 0  -> Day 1
        2  => 1, // Day 1  -> Day 2
        3  => 2, // Day 2  -> Day 4
        4  => 1, // Day 4  -> Day 5
        5  => 2, // Day 5  -> Day 7
        6  => 2, // Day 7  -> Day 9
        7  => 3, // Day 9  -> Day 12
        8  => 2, // Day 12 -> Day 14
        9  => 3, // Day 14 -> Day 17
        10 => 3, // Day 17 -> Day 20
        11 => 2, // Day 20 -> Day 22
        12 => 0, // end
    ];

    $channels = [
        1  => 'call',
        2  => 'email',
        3  => 'call',
        4  => 'linkedin',
        5  => 'call',
        6  => 'email',
        7  => 'call',
        8  => 'linkedin',
        9  => 'call',
        10 => 'email',
        11 => 'call',
        12 => 'email',
    ];

    // Optional flags kept for future use (none active yet for unified cadence)
    $flags = [];

    $out = [];
    for ($i = 1; $i <= 12; $i++) {
        $out[$i] = [
            'label'    => $labels[$i] ?? "Touch {$i}",
            'delay_bd' => (int)($delays[$i] ?? 0),
            'flags'    => $flags[$i] ?? [],
            'channel'  => $channels[$i] ?? 'call',
        ];
    }
    return $out;
}
