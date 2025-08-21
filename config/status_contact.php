<?php
/**
 * Contact status list — grouped by category.
 * Format: ['Category' => ['Substatus 1', 'Substatus 2', ...], ...]
 */
return [
    'Outreach' => [
        'New Contact',
        'Attempted to Contact',
        'Contacted',
        'Conversation Started',
        'No-Show',
    ],

    'Meeting / Development' => [
        'Meeting to be Scheduled',
        'Meeting Scheduled',
        'Waiting on Feedback',
        'Second Meeting to be Scheduled',
        'Second Meeting Scheduled',
        'Proposal Sent',
        'Approved to Proceed',
    ],

    'Opportunity / Client' => [
        'Opportunity Identified',
        'Negotiation in Progress',
        'Client Won (New)',
        'Expansion Opportunity',
    ],

    'Active Engagement' => [
        'Active Job Orders',
        'On Hold',
        'Dormant Client',
        'Contact in Future',
    ],

    'Lost / Rejected' => [
        'Not Interested',
        'Rejected – No Budget',
        'Rejected – No Authority',
        'Rejected – No Need',
        'Unqualified',
    ],

    'Contact Action / Limbo' => [
        'Ghosted',
        'Paused by Contact',
        'Withdrawn by Contact',
    ],
];
