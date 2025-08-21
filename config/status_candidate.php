<?php
/**
 * Candidate status list — grouped by category.
 * Format: ['Category' => ['Substatus 1', 'Substatus 2', ...], ...]
 */
return [
    'Screening' => [
        'New',
        'Associated to Job',
        'Attempted to Contact',
        'Contacted',
        'Screening / Conversation',
        'No-Show',
    ],
    'Interview' => [
        'Interview to be Scheduled',
        'Interview Scheduled',
        'Waiting on Client Feedback',
        'Second Interview to be Scheduled',
        'Second Interview Scheduled',
        'Submitted to Client',
        'Approved by Client',
    ],
    'Offer' => [
        'To be Offered',
        'Offer Made',
        'Offer Accepted',
        'Offer Declined',
        'Offer Withdrawn',
    ],
    'Hired' => [
        'Hired',
    ],
    'Status Change / Other' => [
        'On Hold',
        'Position Closed',
        'Contact in Future',
    ],
    'Rejected' => [
        'Rejected',
        'Rejected – By Client',
        'Rejected – For Interview',
        'Rejected – Hirable',
        'Unqualified',
        'Not Interested',
    ],
    'Candidate Action / Limbo' => [
        'Ghosted',
        'Paused by Candidate',
        'Withdrawn by Candidate',
    ],
];
