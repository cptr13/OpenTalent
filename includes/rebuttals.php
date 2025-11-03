<?php
/**
 * includes/rebuttals.php
 *
 * Central catalog for live-call objections & rebuttals.
 * You can expand this freely. Keep copy short and field-usable.
 *
 * Public:
 *   rebuttal_catalog(): array
 *   get_rebuttals(string $scriptType='cold_call', ?string $query=null): array
 */

function rebuttal_catalog(): array {
    // Top-level keyed by script type; start with cold_call.
    return [
        'cold_call' => [
            [
                'category' => 'Access / Gatekeeper',
                'items' => [
                    [
                        'id' => 'gk_send_info',
                        'title' => '“Just send info.”',
                        'tags' => ['gatekeeper','send info','email'],
                        'response' =>
"Totally get it. I’m not asking for time right now—just a 15–20 second line so {{decision_maker}} knows what this is before you forward me. Then they can ignore me if it’s off-base. Fair?
[If yes]: Thanks—my name is {{user_name}} with Oakway Group. We help {{industry}} teams fill professional and salaried roles quickly without locking into retainers. If it sounds useful, I’ll keep it short with {{decision_maker}}."
                    ],
                    [
                        'id' => 'gk_busy',
                        'title' => '“They’re busy / not available.”',
                        'tags' => ['gatekeeper','busy'],
                        'response' =>
"Understood. Can I give you one sentence so it’s clear what I’m calling about before you decide if it’s a fit to pass along?
[If yes]: We help {{industry}} leaders hire salaried professionals fast—no retainers, no spam—just vetted candidates. If that’s not relevant, we’re done."
                    ],
                ],
            ],

            [
                'category' => 'Not Hiring / No Openings',
                'items' => [
                    [
                        'id' => 'nh_now',
                        'title' => '“We’re not hiring right now.”',
                        'tags' => ['not hiring','timing'],
                        'response' =>
"That’s fair. I’m not asking for a search today. Two quick reasons clients talk with me anyway:
1) When something critical lands unexpectedly, they want a fast lane.
2) Market intel on what similar roles are paying and how fast they’re moving.
If either is useful, happy to trade notes for 10 minutes next week—then I’ll get out of your hair."
                    ],
                ],
            ],

            [
                'category' => 'We Have Vendors / Preferred List',
                'items' => [
                    [
                        'id' => 'vendors_list',
                        'title' => '“We already have vendors / a PSL.”',
                        'tags' => ['vendors','psl','preferred'],
                        'response' =>
"That’s normal. I’m not asking you to reshuffle anything. When teams keep us on the bench, three things win us a seat:
• We stay in our lane (salaried/professional roles).
• We’re easy to engage—no retainers.
• We move quietly and quickly when something urgent hits.
If I earn a small trial on a hard-to-fill role, great—if not, no harm done."
                    ],
                ],
            ],

            [
                'category' => 'Internal Recruiting',
                'items' => [
                    [
                        'id' => 'internal_team',
                        'title' => '“Our internal team handles it.”',
                        'tags' => ['internal','talent acquisition'],
                        'response' =>
"Totally. We play well with internal teams. Where we help:
• Backfilling urgent roles without derailing their roadmap.
• Reaching passive candidates they don’t have time to chase.
• Spinning up niche searches while they cover volume.
If nothing like that is coming, we don’t need to meet. If it is, a 10-minute fit check helps us be ready."
                    ],
                ],
            ],

            [
                'category' => 'Budget / Fees',
                'items' => [
                    [
                        'id' => 'fees_budget',
                        'title' => '“No budget / fees are too high.”',
                        'tags' => ['budget','fees','cost'],
                        'response' =>
"Understood. Our model is simple: contingent, no retainers, and we only move if there’s clear value. If a role is stagnant or business-critical, the cost of vacancy dwarfs the fee. If it’s not critical, you shouldn’t use us. Happy to outline when it is and isn’t worth it so you can shelve us appropriately."
                    ],
                ],
            ],

            [
                'category' => 'Remote / Onsite',
                'items' => [
                    [
                        'id' => 'remote_local',
                        'title' => '“We need people local.”',
                        'tags' => ['local','onsite','remote'],
                        'response' =>
"Agreed—local fit matters. We lead with local pipelines first, then expand outward only if you want more reach. The point today isn’t to push remote; it’s to be ready with local options when you need speed."
                    ],
                ],
            ],

            [
                'category' => 'Timing / Call Back',
                'items' => [
                    [
                        'id' => 'callback',
                        'title' => '“Call me back later.”',
                        'tags' => ['timing','callback'],
                        'response' =>
"Happy to. Before I do—so I don’t waste your future time—are you open to a quick intro later this week to see if we’re worth keeping on deck, or should I stand down unless a specific role pops?"
                    ],
                ],
            ],

            [
                'category' => 'Email Me',
                'items' => [
                    [
                        'id' => 'email_me',
                        'title' => '“Just email me.”',
                        'tags' => ['email','send info'],
                        'response' =>
"Can do—though I don’t want to spam you. 15 seconds now will tell us if it’s worth an email. If it’s not, I won’t send anything. Fair?"
                    ],
                ],
            ],

            [
                'category' => 'Process / VMS',
                'items' => [
                    [
                        'id' => 'vms_only',
                        'title' => '“We use a VMS / strict process.”',
                        'tags' => ['vms','process'],
                        'response' =>
"Understood. We’re flexible with process and can work inside your rules. Usually the only question is whether you ever need a specialist lane to move a critical role faster—if yes, we can add value within your framework."
                    ],
                ],
            ],

            [
                'category' => 'Model (Retained vs Contingent)',
                'items' => [
                    [
                        'id' => 'model_fit',
                        'title' => '“We only do retained / or only contingent.”',
                        'tags' => ['retained','contingent','model'],
                        'response' =>
"We execute both models. For early conversations, we default to clean contingent—no retainers—so it’s easy to try us when something matters. If a retained search is smarter for a role, we’ll say so."
                    ],
                ],
            ],
        ],
        // Future script types can go here (voicemail, discovery, etc.)
    ];
}

function get_rebuttals(string $scriptType = 'cold_call', ?string $query = null): array {
    $scriptType = strtolower(trim($scriptType));
    $catalog = rebuttal_catalog();
    $blocks = $catalog[$scriptType] ?? [];

    if ($query === null || $query === '') {
        return $blocks;
    }

    $q = mb_strtolower($query);
    $out = [];
    foreach ($blocks as $block) {
        $category = (string)($block['category'] ?? 'Other');
        $items    = (array)($block['items'] ?? []);
        $matched  = [];
        foreach ($items as $it) {
            $hay = mb_strtolower(
                implode(' ', [
                    $it['title'] ?? '',
                    $it['response'] ?? '',
                    implode(' ', (array)($it['tags'] ?? []))
                ])
            );
            if (mb_strpos($hay, $q) !== false) {
                $matched[] = $it;
            }
        }
        if ($matched) {
            $out[] = ['category' => $category, 'items' => $matched];
        }
    }
    return $out;
}
