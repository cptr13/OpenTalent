<?php
/**
 * includes/cadence.php
 *
 * Centralized cadence definitions and lookup for outreach stage scheduling.
 *
 * Public API:
 *   cadence_lookup(int $stage, string $cadenceType = 'voicemail'): array{
 *       label: string,        // Human-readable stage label
 *       delay_bd: int,        // Business days until NEXT touch (0 = end)
 *       flags: array,         // Optional feature flags (e.g., ['li_connect' => true])
 *       channel: string       // 'call'|'email'|'linkedin'|'call_vm'
 *   }
 *
 * Convenience helpers:
 *   cadence_types(): array<string>                    // ['voicemail','mixed']
 *   cadence_touch_count(string $type='voicemail'):int // 12
 *   cadence_stage_title(string $type,int $stage):string
 *   cadence_table(string $type='voicemail'): array<int,array{label:string,delay_bd:int,flags:array,channel:string}>
 */

// ---- Public: list supported cadences ----
function cadence_types(): array {
    return ['voicemail', 'mixed'];
}

function cadence_touch_count(string $type = 'voicemail'): int {
    return 12; // both cadences are 12-touch
}

/**
 * Primary lookup — returns label + business-day delay + flags + channel.
 */
function cadence_lookup(int $stage, string $cadenceType = 'voicemail'): array
{
    $type = strtolower(trim($cadenceType));
    if (!in_array($type, cadence_types(), true)) {
        $type = 'voicemail';
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
function cadence_table(string $type = 'voicemail'): array
{
    $type = strtolower(trim($type));

    // ----- Mixed Cadence (Phone + Other) -----
    if ($type === 'mixed') {
        // Alternates between email and LinkedIn, with a call/VM near the end.
        $labels = [
            1  => 'Touch 1 – Email #1',
            2  => 'Touch 2 – LinkedIn #1',
            3  => 'Touch 3 – Email #2',
            4  => 'Touch 4 – LinkedIn #2',
            5  => 'Touch 5 – Email #3',
            6  => 'Touch 6 – LinkedIn #3',
            7  => 'Touch 7 – Email #4',
            8  => 'Touch 8 – LinkedIn #4',
            9  => 'Touch 9 – Email #5',
            10 => 'Touch 10 – LinkedIn #5',
            11 => 'Touch 11 – Call / Voicemail',
            12 => 'Touch 12 – Breakup Email',
        ];
        $delays = [
            1 => 3,
            2 => 2,
            3 => 3,
            4 => 2,
            5 => 3,
            6 => 5,
            7 => 5,
            8 => 5,
            9 => 10,
            10 => 10,
            11 => 10,
            12 => 0,
        ];
        $channels = [
            1=>'email', 2=>'linkedin', 3=>'email', 4=>'linkedin', 5=>'email', 6=>'linkedin',
            7=>'email', 8=>'linkedin', 9=>'email', 10=>'linkedin', 11=>'call', 12=>'email'
        ];

        $out = [];
        for ($i=1; $i<=12; $i++) {
            $out[$i] = [
                'label'    => $labels[$i] ?? "Touch {$i}",
                'delay_bd' => (int)($delays[$i] ?? 0),
                'flags'    => [],
                'channel'  => $channels[$i] ?? 'email',
            ];
        }
        return $out;
    }

    // ----- Voicemail-Only Cadence -----
    // Rhythm: 2 calls/week × 3 weeks → 1.5 calls/week × 2 weeks → 1 call/week × 3 weeks
    $labels = [
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
    $delays = [
        1 => 2, // -> T2
        2 => 3, // -> T3
        3 => 2, // -> T4
        4 => 3, // -> T5
        5 => 2, // -> T6
        6 => 3, // -> T7
        7 => 4, // -> T8
        8 => 3, // -> T9
        9 => 4, // -> T10
        10 => 5, // -> T11
        11 => 5, // -> T12
        12 => 0, // end
    ];
    $flags = [
        1 => ['li_connect' => true], // optional LinkedIn connection add-on for Touch 1
    ];

    $out = [];
    for ($i=1; $i<=12; $i++) {
        $out[$i] = [
            'label'    => $labels[$i] ?? "Touch {$i}",
            'delay_bd' => (int)($delays[$i] ?? 0),
            'flags'    => $flags[$i] ?? [],
            'channel'  => 'call', // or 'call_vm' if you want finer granularity
        ];
    }
    return $out;
}
