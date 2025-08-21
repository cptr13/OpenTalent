<?php
declare(strict_types=1);

/**
 * Unified status loader.
 * Usage examples:
 *   $candidateStatuses = getStatusList('candidate');
 *   $contactStatuses   = getStatusList('contact');
 */
function getStatusList(string $entity): array
{
    $map = [
        'candidate' => __DIR__ . '/status_candidate.php',
        'contact'   => __DIR__ . '/status_contact.php',
    ];

    if (!isset($map[$entity])) {
        throw new RuntimeException("Unknown entity for status list: {$entity}");
    }

    /** @var array $list */
    $list = require $map[$entity];
    if (!is_array($list)) {
        throw new RuntimeException("Status list file did not return an array: {$map[$entity]}");
    }

    return $list;
}
