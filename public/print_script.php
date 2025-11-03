<?php
// public/print_script.php
// Clean printable view for dynamic scripts.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/script_renderer.php';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$scriptType    = isset($_GET['script_type']) ? trim((string)$_GET['script_type']) : '';
$contactId     = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : null;
$candidateId   = isset($_GET['candidate_id']) ? (int)$_GET['candidate_id'] : null;
$clientId      = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$jobId         = isset($_GET['job_id']) ? (int)$_GET['job_id'] : null;
$tone          = isset($_GET['tone']) ? strtolower(trim((string)$_GET['tone'])) : 'auto';

$includeSmalltalk  = isset($_GET['include_smalltalk']) ? ((int)$_GET['include_smalltalk'] !== 0) : true;
$includeMicroOffer = isset($_GET['include_micro_offer']) ? ((int)$_GET['include_micro_offer'] !== 0) : true;

$ctx = [
  'script_type_slug'    => $scriptType,
  'contact_id'          => $contactId ?: null,
  'candidate_id'        => !$contactId ? ($candidateId ?: null) : null, // only used when no contact provided
  'client_id'           => $clientId ?: null,
  'job_id'              => $jobId ?: null,
  'tone_mode'           => in_array($tone, ['auto','friendly','consultative','direct'], true) ? $tone : 'auto',
  'include_smalltalk'   => $includeSmalltalk,
  'include_micro_offer' => $includeMicroOffer,
];

$res = render_script($ctx);
if (is_string($res)) {
  $res = ['text' => $res];
} elseif (!is_array($res)) {
  $res = [];
}

// Pull context (location/region) safely
$renderCtx = isset($res['context']) && is_array($res['context']) ? $res['context'] : [];
$region    = $renderCtx['region']   ?? null;
$location  = $renderCtx['location'] ?? null;

// ---- Context for header (contact, candidate, client) ----
$contactName   = '';
$candidateName = '';
$companyName   = '';

// Contact
if ($contactId) {
  $stmt = $pdo->prepare("SELECT first_name, last_name, client_id FROM contacts WHERE id = ? LIMIT 1");
  $stmt->execute([$contactId]);
  if ($c = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $contactName = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    // Allow implicit client from contact if not explicitly provided
    if (!$clientId && !empty($c['client_id'])) {
      $clientId = (int)$c['client_id'];
    }
  }
}

// Candidate (used when no contact)
if (!$contactName && $candidateId) {
  $stmt = $pdo->prepare("SELECT first_name, last_name FROM candidates WHERE id = ? LIMIT 1");
  $stmt->execute([$candidateId]);
  if ($cand = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $candidateName = trim(($cand['first_name'] ?? '') . ' ' . ($cand['last_name'] ?? ''));
  }
}

// Client / Company
if ($clientId) {
  $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ? LIMIT 1");
  $stmt->execute([$clientId]);
  if ($cl = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $companyName = $cl['name'] ?? '';
  }
}

// Who line
$whoLine = $contactName ?: ($candidateName ?: 'Contact');
if ($companyName) {
  $whoLine .= ' — ' . $companyName;
}

// Header pills content
$toneUsed     = $res['tone_used']     ?? ($tone === 'auto' ? 'auto' : $tone);
$templateName = $res['template_name'] ?? '—';
$smalltalkLbl = $includeSmalltalk ? 'On' : 'Off';
$microofferLbl= $includeMicroOffer ? 'On' : 'Off';

// Script body
$scriptText = $res['text'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Print Script</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --fg:#111; --muted:#666; --border:#ddd; --pad:20px; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: var(--fg); margin:0; }
    .wrap { max-width: 800px; margin: 0 auto; padding: var(--pad); }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .sub { color: var(--muted); margin-bottom: 16px; }
    .meta { font-size: 12px; color: var(--muted); margin-bottom: 16px; display:flex; flex-wrap:wrap; gap:8px; }
    .box { border:1px solid var(--border); padding:16px; border-radius:8px; white-space:pre-wrap; }
    .pill { border:1px solid var(--border); border-radius:999px; padding:4px 10px; font-size:12px; }
    .pill.muted { color: var(--muted); }
    .row { display:flex; gap:12px; align-items:center; }
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="no-print" style="display:flex; justify-content:flex-end; margin-bottom:8px;">
      <button onclick="window.print()" style="padding:8px 12px; border:1px solid #ccc; background:#f6f6f6; border-radius:8px; cursor:pointer;">Print</button>
    </div>

    <h1><?= h($whoLine) ?></h1>
    <div class="sub">
      <?= h(ucwords(str_replace('_',' ', $scriptType ?: 'script'))) ?>
    </div>

    <div class="meta">
      <span class="pill">Tone used: <?= h($toneUsed) ?></span>
      <span class="pill">Template: <?= h($templateName) ?></span>
      <span class="pill">Small-talk: <?= h($smalltalkLbl) ?></span>
      <span class="pill">Micro-offer: <?= h($microofferLbl) ?></span>
      <span class="pill<?= $region ? '' : ' muted' ?>">Region: <?= h($region ?: '—') ?></span>
      <span class="pill<?= $location ? '' : ' muted' ?>">Location: <?= h($location ?: '—') ?></span>
    </div>

    <div class="box"><?= h($scriptText) ?></div>
  </div>

  <script>
    // Auto-open print dialog if "print=1" is present
    (function(){
      const params = new URLSearchParams(location.search);
      if (params.get('print') === '1') { setTimeout(()=>window.print(), 50); }
    })();
  </script>
</body>
</html>
