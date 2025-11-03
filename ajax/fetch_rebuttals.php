<?php
// ajax/fetch_rebuttals.php
// Returns JSON with rendered Bootstrap accordion HTML for rebuttals (DB-driven, tone-aware with fallback).

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'type' => 'php_error',
        'errno' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});
set_exception_handler(function($ex) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'type' => 'exception',
        'message' => $ex->getMessage(),
        'file' => $ex->getFile(),
        'line' => $ex->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// ---- Parameters ----
$scriptType = isset($_GET['script_type']) ? strtolower(trim((string)$_GET['script_type'])) : 'cold_call';

// Support both 'tone' and legacy 't'
$toneParam  = isset($_GET['tone']) ? strtolower(trim((string)$_GET['tone'])) : (
    isset($_GET['t']) ? strtolower(trim((string)$_GET['t'])) : 'friendly'
);

// Support both 'search' and legacy 'q'
$search     = isset($_GET['search']) ? trim((string)$_GET['search']) : (
    isset($_GET['q']) ? trim((string)$_GET['q']) : ''
);

if ($toneParam === 'auto') $toneParam = 'friendly';

// ---- Primary query (no script_type filter; that column doesn't exist) ----
$sql = "
    SELECT 
        o.id AS objection_id,
        o.category,
        o.title AS objection_title,
        o.objection_slug,
        r.tone,
        r.body AS response_text,
        r.status AS response_status
    FROM outreach_objections o
    LEFT JOIN outreach_responses r
      ON r.objection_slug = o.objection_slug AND r.status = 'active'
    WHERE o.status = 'active'
";
$params = [];

if ($toneParam !== 'all') {
    $sql .= " AND (r.tone = :tone OR r.tone IS NULL)";
    $params[':tone'] = $toneParam;
}

if ($search !== '') {
    $sql .= " AND (o.title LIKE :search OR r.body LIKE :search)";
    $params[':search'] = "%$search%";
}

// Safe ordering (avoid non-existent columns like o.sort_order / r.priority)
$sql .= " ORDER BY o.category ASC, o.title ASC, r.tone ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Determine which objections need fallback (no response returned for selected tone/search) ----
$foundBySlug = [];
foreach ($rows as $r) {
    $slug = $r['objection_slug'];
    $resp = trim((string)($r['response_text'] ?? ''));
    if ($resp !== '') {
        $foundBySlug[$slug] = true;
    }
}

// Build the candidate set of objections (respecting search filter)
$sqlCandidates = "SELECT objection_slug FROM outreach_objections WHERE status='active'";
$paramsCandidates = [];
if ($search !== '') {
    $sqlCandidates .= " AND title LIKE :search";
    $paramsCandidates[':search'] = "%$search%";
}

$stmtCand = $pdo->prepare($sqlCandidates);
$stmtCand->execute($paramsCandidates);
$missingSlugs = [];
while ($o = $stmtCand->fetch(PDO::FETCH_ASSOC)) {
    if (!isset($foundBySlug[$o['objection_slug']])) {
        $missingSlugs[] = $o['objection_slug'];
    }
}

// ---- Fallback: fetch first available active responses (prefer Friendly where present)
if ($toneParam !== 'all' && $missingSlugs) {
    $in  = str_repeat('?,', count($missingSlugs) - 1) . '?';
    $sqlFallback = "
        SELECT 
            o.category,
            o.title AS objection_title,
            o.objection_slug,
            COALESCE(
                (SELECT rr.tone FROM outreach_responses rr 
                   WHERE rr.objection_slug = o.objection_slug AND rr.status='active' AND rr.tone='friendly'
                   ORDER BY rr.tone ASC LIMIT 1),
                (SELECT rr2.tone FROM outreach_responses rr2 
                   WHERE rr2.objection_slug = o.objection_slug AND rr2.status='active'
                   ORDER BY rr2.tone ASC LIMIT 1)
            ) AS tone,
            COALESCE(
                (SELECT rr.body FROM outreach_responses rr 
                   WHERE rr.objection_slug = o.objection_slug AND rr.status='active' AND rr.tone='friendly'
                   ORDER BY rr.tone ASC LIMIT 1),
                (SELECT rr2.body FROM outreach_responses rr2 
                   WHERE rr2.objection_slug = o.objection_slug AND rr2.status='active'
                   ORDER BY rr2.tone ASC LIMIT 1)
            ) AS response_text
        FROM outreach_objections o
        WHERE o.objection_slug IN ($in) AND o.status='active'
        ORDER BY o.category ASC, o.title ASC
    ";
    $stmtFb = $pdo->prepare($sqlFallback);
    $stmtFb->execute($missingSlugs);
    $fallbackRows = $stmtFb->fetchAll(PDO::FETCH_ASSOC);

    // Tag as fallback for visual marker
    foreach ($fallbackRows as &$fr) {
        $fr['is_fallback'] = true;
    }
    unset($fr);

    $rows = array_merge($rows, $fallbackRows);
}

// ---- Group results into Category -> Objection -> Responses[] ----
$grouped = [];
foreach ($rows as $r) {
    $cat   = $r['category'] ?: 'Other';
    $title = $r['objection_title'] ?: 'Untitled';
    $tone  = $r['tone'] ?: 'general';
    $resp  = trim((string)($r['response_text'] ?? ''));
    $isFallback = !empty($r['is_fallback']);

    if ($resp === '') continue;

    if (!isset($grouped[$cat])) {
        $grouped[$cat] = [];
    }

    // Find existing objection entry
    $foundIdx = null;
    foreach ($grouped[$cat] as $i => $it) {
        if ($it['title'] === $title) { $foundIdx = $i; break; }
    }

    $entry = [
        'tone' => $tone,
        'text' => $resp,
        'is_fallback' => $isFallback
    ];

    if ($foundIdx === null) {
        $grouped[$cat][] = [
            'title' => $title,
            'responses' => [$entry]
        ];
    } else {
        $grouped[$cat][$foundIdx]['responses'][] = $entry;
    }
}

// ---- Render Bootstrap accordion HTML (collapsed by default) ----
$accordionId = 'reb-acc-' . bin2hex(random_bytes(4));
$html = '';
$total = 0;

foreach ($grouped as $category => $items) {
    $catSafe = htmlspecialchars($category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $catId = $accordionId . '-cat-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($category));

    $html .= '<div class="mb-2"><h6 class="mb-1">' . $catSafe . '</h6>';
    $html .= '<div class="accordion" id="'. $catId .'">';

    foreach ($items as $j => $it) {
        $total++;
        $title = htmlspecialchars($it['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $itemId = $catId . '-item-' . $j;
        $headingId = $itemId . '-h';
        $collapseId = $itemId . '-c';

        $respHtml = '';
        foreach ($it['responses'] as $r) {
            $toneLabel = ucfirst(htmlspecialchars($r['tone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $text = nl2br(htmlspecialchars($r['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $tag = $r['is_fallback'] ? " <small class='text-muted ms-2'>(Default tone response from {$toneLabel})</small>" : "";
            // Show tone labels only when user chose "all"; otherwise keep clean
            $respHtml .= "<div class='mb-2'>" . ($toneParam === 'all' ? "<strong>{$toneLabel}:</strong><br>" : "") . "{$text}{$tag}</div>";
        }

        $plainCopy = implode("\n\n", array_column($it['responses'], 'text'));
        $html .= '
<div class="accordion-item">
  <h2 class="accordion-header" id="'. $headingId .'">
    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#'. $collapseId .'" aria-expanded="false" aria-controls="'. $collapseId .'">
      ' . $title . '
    </button>
  </h2>
  <div id="'. $collapseId .'" class="accordion-collapse collapse" aria-labelledby="'. $headingId .'" data-bs-parent="#'. $catId .'">
    <div class="accordion-body">
      <div class="d-flex justify-content-between align-items-start">
        <div class="pe-3" style="white-space:pre-wrap">'. $respHtml .'</div>
        <button type="button" class="btn btn-sm btn-outline-primary rebuttal-copy-btn" data-copy-text="'. htmlspecialchars($plainCopy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'">Copy</button>
      </div>
    </div>
  </div>
</div>';
    }

    $html .= '</div></div>';
}

if ($total === 0) {
    $html = '<div class="text-muted">No matching rebuttals.</div>';
}

echo json_encode([
    'ok'    => true,
    'html'  => $html,
    'count' => $total,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
