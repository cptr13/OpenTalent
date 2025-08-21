<?php
/**
 * Temporary shim — keeps old references working.
 * Returns the candidate status list.
 *
 * TODO: Once all code is updated to use getStatusList('candidate'),
 * this file can be removed.
 */
return require __DIR__ . '/status_candidate.php';
