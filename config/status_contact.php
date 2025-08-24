<?php
/**
 * Contact status list — grouped by category.
 * Format: ['Category' => ['Substatus 1', 'Substatus 2', ...], ...]
 * NOTE: Labels here are the source of truth for Sales (Contacts).
 */
return [
    'Leads' => [
        'New / Lead Added',
    ],

    'Outreach Attempts' => [
        'Contact Attempt - Left Voicemail',
        'Contact Attempt - Email Sent',
        'Contact Attempt - LinkedIn Message',
    ],

    'Engagement' => [
        'Conversation',
        'Waiting on Feedback',
    ],

    'Meetings' => [
        'Meeting to be Scheduled',
        'Meeting Scheduled',
    ],

    'Agreements' => [
        'Agreement Sent',
        'Agreement Signed',
    ],

    'Opportunities' => [
        'Job Order Received',
    ],

    'Closed / Other' => [
        'No Interest / Lost',
        'Future Contact / On Hold',
    ],
];
