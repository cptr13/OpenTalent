<?php
// includes/sortable.php
// Reusable, safe sorting helper for list pages

function ot_get_sort(array $allowed, string $defaultCol, string $defaultDir = 'asc'): array {
    $sort = strtolower($_GET['sort'] ?? $defaultCol);
    $dir  = strtolower($_GET['dir']  ?? $defaultDir);

    if (!isset($allowed[$sort])) $sort = $defaultCol;
    if (!in_array($dir, ['asc', 'desc'], true)) $dir = $defaultDir;

    // Build safe ORDER BY (value is a SQL fragment like "c.name" or "u.full_name")
    $orderBy = $allowed[$sort] . ' ' . strtoupper($dir);

    // Helper closures for headers
    $toggleDir = fn(string $col) => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $isActive  = fn(string $col) => $sort === $col;
    $arrow     = fn(string $col) => $isActive($col) ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';

    // Build a link that preserves existing query params
    $buildLink = function(string $col, ?string $label = null) use ($toggleDir) {
        $qs = $_GET;
        $qs['sort'] = $col;
        $qs['dir']  = $toggleDir($col);
        return '?' . http_build_query($qs);
    };

    return [
        'sort'      => $sort,
        'dir'       => $dir,
        'orderBy'   => $orderBy,
        'toggleDir' => $toggleDir,
        'isActive'  => $isActive,
        'arrow'     => $arrow,
        'link'      => $buildLink,
    ];
}
