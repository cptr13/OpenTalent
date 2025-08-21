<?php
/**
 * Status badge renderer for contacts.
 * Usage:
 *   require_once __DIR__ . '/status_badge.php';
 *   echo contact_status_badge($contact['contact_status'] ?? null, 'sm');
 *
 * $size: 'sm' | 'md' | 'lg'
 */
function contact_status_badge(?string $status, string $size = 'sm'): string
{
    $status = trim((string)$status);
    if ($status === '') {
        return '<span class="text-muted">—</span>';
    }

    // Neutral badge for now. If you later group by category, map to different classes here.
    $sizeClass = match ($size) {
        'lg' => 'badge px-3 py-2',
        'md' => 'badge px-2 py-1',
        default => 'badge px-2', // sm
    };

    // Bootstrap 5: bg-secondary keeps it subtle; change to category colors later if desired.
    return '<span class="'. $sizeClass .' bg-secondary">'. htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'</span>';
}
