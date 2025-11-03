<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php'; // loader for status lists
require_once __DIR__ . '/../includes/geo_region.php'; // <-- for region display

// Try to centralize cadence labels from includes/cadence.php
$haveCadenceLookup = false;
$candidateCadencePath = __DIR__ . '/../includes/cadence.php';
if (is_file($candidateCadencePath)) {
    require_once $candidateCadencePath;
    $haveCadenceLookup = function_exists('cadence_lookup');
}

$contact_id = $_GET['id'] ?? null;

if (!$contact_id) {
    echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->execute([$contact_id]);
$contact = $stmt->fetch();

if (!$contact) {
    echo "<div class='alert alert-warning'>Contact not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Helper
function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Build Email compose link (compose_email.php)
$full_name  = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
$return_to  = 'view_contact.php?id=' . (int)$contact['id'];
$compose_qs = http_build_query([
    'to'           => $contact['email'] ?? '',
    'name'         => $full_name,
    'related_type' => 'contact',
    'related_id'   => (int)$contact['id'],
    'return_to'    => $return_to,
]);
$email_url = 'compose_email.php?' . $compose_qs;

$client = null;
if (!empty($contact['client_id'])) {
    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id = ?");
    $stmt->execute([$contact['client_id']]);
    $client = $stmt->fetch();
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE module_type = ? AND module_id = ? ORDER BY created_at DESC");
$stmt->execute(['contact', $contact_id]);
$notes = $stmt->fetchAll();

// Fetch outreach template from outreach_templates table using stage_number
$outreach_template = null;
if (!empty($contact['outreach_stage'])) {
    $stage_number = (int)$contact['outreach_stage'];
    $stmt = $pdo->prepare("SELECT * FROM outreach_templates WHERE stage_number = ?");
    $stmt->execute([$stage_number]);
    $outreach_template = $stmt->fetch();
}

// Fetch associated jobs via job_contacts
$stmt = $pdo->prepare("
    SELECT j.id, j.title, j.status 
    FROM job_contacts jc
    JOIN jobs j ON jc.job_id = j.id
    WHERE jc.contact_id = ?
");
$stmt->execute([$contact_id]);
$associated_jobs = $stmt->fetchAll();

$flash_message = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;

// ----- Contact Status List (entity-aware) -----
$contactStatusList = getStatusList('contact'); // ['Category' => ['Sub1', 'Sub2', ...]]
$currentContactStatus = $contact['contact_status'] ?? ''; // safe if column not present

// Grab outreach stage for the Scripts panel context
$defaultStage = isset($contact['outreach_stage']) && (int)$contact['outreach_stage'] > 0 ? (int)$contact['outreach_stage'] : 1;

// ---- Cadence Type (persisted) ----
$currentCadence = strtolower((string)($contact['outreach_cadence'] ?? 'voicemail'));
if (!in_array($currentCadence, ['voicemail','mixed'], true)) {
    $currentCadence = 'voicemail';
}

// ---- Address + Region (display only) ----
$addrStreet  = trim((string)($contact['address_street']  ?? ''));
$addrCity    = trim((string)($contact['address_city']    ?? ''));
$addrState   = trim((string)($contact['address_state']   ?? ''));
$addrZip     = trim((string)($contact['address_zip']     ?? ''));
$addrCountry = trim((string)($contact['address_country'] ?? ''));

// Build a compact 1–2 line address for display
$line1 = $addrStreet;
$partsLine2 = array_filter([$addrCity, $addrState], fn($x) => $x !== '');
$line2 = implode(', ', $partsLine2);
if ($addrZip !== '') {
    $line2 = $line2 !== '' ? $line2 . ' ' . $addrZip : $addrZip;
}
$line3 = $addrCountry;

// Compute a best-effort region label (using city/state/country first)
$regionDisplay = null;
try {
    $regionDisplay = infer_region_from_parts($addrCity, $addrState, $addrCountry);
    if ($regionDisplay === null) {
        // Fallback: try a single raw location string if parts are sparse
        $raw = trim(implode(', ', array_filter([$addrStreet, $addrCity, $addrState, $addrZip, $addrCountry], fn($x) => $x !== '')));
        if ($raw !== '') {
            $regionDisplay = infer_region_from_location($raw);
        }
    }
} catch (Throwable $e) {
    $regionDisplay = null; // display gracefully as —
}

/**
 * Build cadence labels from centralized cadence_lookup() when available.
 * Falls back to the known voicemail labels if not present.
 * Returns array like: ['voicemail' => [1 => 'Touch 1 – ...', ...], 'mixed' => [...]]
 */
function build_cadence_labels(bool $haveCadenceLookup): array
{
    // Known voicemail fallback (your previous labels)
    $fallbackVoicemail = [
        1  => 'Touch 1 – Voicemail #1',
        2  => 'Touch 2 – Voicemail #2',
        3  => 'Touch 3 – Voicemail #3',
        4  => 'Touch 4 – Voicemail #4',
        5  => 'Touch 5 – Voicemail #5',
        6  => 'Touch 6 – Voicemail #6',
        7  => 'Touch 7 – Voicemail #7',
        8  => 'Touch 8 – Voicemail #8',
        9  => 'Touch 9 – Voicemail #9',
        10 => 'Touch 10 – Voicemail #10',
        11 => 'Touch 11 – Voicemail #11',
        12 => 'Touch 12 – Voicemail #12 (Final)',
    ];

    $labels = [
        'voicemail' => $fallbackVoicemail,
        // If mixed isn’t defined centrally, synthesize a simple 12-step mixed fallback
        'mixed'     => [
            1  => 'Touch 1 – Mixed #1',
            2  => 'Touch 2 – Mixed #2',
            3  => 'Touch 3 – Mixed #3',
            4  => 'Touch 4 – Mixed #4',
            5  => 'Touch 5 – Mixed #5',
            6  => 'Touch 6 – Mixed #6',
            7  => 'Touch 7 – Mixed #7',
            8  => 'Touch 8 – Mixed #8',
            9  => 'Touch 9 – Mixed #9',
            10 => 'Touch 10 – Mixed #10',
            11 => 'Touch 11 – Mixed #11',
            12 => 'Touch 12 – Mixed #12 (Final)',
        ],
    ];

    if (!$haveCadenceLookup) {
        return $labels;
    }

    // Build from cadence_lookup for both cadence types.
    $types = ['voicemail','mixed'];
    foreach ($types as $type) {
        $built = [];
        for ($i = 1; $i <= 50; $i++) {
            $meta = cadence_lookup($i, $type);
            if (!is_array($meta) || empty($meta['label'])) {
                break;
            }
            $built[$i] = (string)$meta['label'];
            if (array_key_exists('delay_bd', $meta) && (int)$meta['delay_bd'] === 0) {
                break;
            }
        }
        if (!empty($built)) {
            $labels[$type] = $built;
        }
    }

    return $labels;
}

$cadenceLabels = build_cadence_labels($haveCadenceLookup);

// Ensure current stage exists for current cadence; if not, reset to 1
if (empty($cadenceLabels[$currentCadence][$defaultStage])) {
    $defaultStage = array_key_first($cadenceLabels[$currentCadence]) ?: 1;
}
?>
<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= h($flash_message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?= h(trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?></h2>
        <div class="d-flex align-items-center gap-2">
            <!-- Scripts button now scrolls to the Scripts panel (no modal) -->
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                onclick="const el=document.getElementById('scripts-card'); if(el){ el.scrollIntoView({behavior:'smooth'}); el.classList.add('ring'); setTimeout(()=>el.classList.remove('ring'),1000);}">
                Scripts
            </button>

            <?php if (!empty($contact['email'])): ?>
                <a href="<?= h($email_url) ?>" class="btn btn-sm btn-outline-primary">Email</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="No email on file">Email</button>
            <?php endif; ?>
            <a href="edit_contact.php?id=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-primary">Edit Contact</a>
            <a href="delete_contact.php?id=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this contact?');">Delete Contact</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Contact Info (now also shows Address + Region) -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Contact Info</span>
                    <a href="edit_contact.php?id=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?= h($contact['email'] ?? '') ?></p>
                    <p><strong>Phone:</strong> <?= h($contact['phone'] ?? '') ?></p>
                    <?php if (!empty($contact['linkedin'])): ?>
                        <p><strong>LinkedIn:</strong> <a href="<?= h($contact['linkedin']) ?>" target="_blank" rel="noopener">View Profile</a></p>
                    <?php endif; ?>

                    <!-- Address block -->
                    <?php if ($line1 !== '' || $line2 !== '' || $line3 !== ''): ?>
                        <div class="mt-3">
                            <strong>Address:</strong>
                            <div style="white-space:pre-line;">
                                <?= h($line1) ?><?= $line1 !== '' && ($line2 !== '' || $line3 !== '') ? "\n" : "" ?><?= h($line2) ?><?= $line2 !== '' && $line3 !== '' ? "\n" : "" ?><?= h($line3) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="mt-3"><strong>Address:</strong> <span class="text-muted">—</span></p>
                    <?php endif; ?>

                    <!-- Auto Region display -->
                    <p class="mt-2"><strong>Region (auto):</strong> <?= h($regionDisplay ?? '—') ?></p>
                </div>
            </div>
        </div>

        <!-- Position & Company -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Position & Company</span>
                    <a href="edit_contact.php?id=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($contact['title'])): ?>
                        <p><strong>Job Title:</strong> <?= h($contact['title']) ?></p>
                    <?php endif; ?>
                    <p><strong>Company:</strong>
                        <?php if ($client): ?>
                            <a href="view_client.php?id=<?= (int)$client['id'] ?>"><?= h($client['name']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">Not Assigned</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Follow-Up -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Follow-Up</div>
                <div class="card-body">
                    <form method="POST" action="update_follow_up.php">
                        <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
                        <label for="follow_up_date"><strong>Next Follow-Up Date:</strong></label>
                        <input type="date" name="follow_up_date" id="follow_up_date" class="form-control form-control-sm mt-1 mb-2" value="<?= h($contact['follow_up_date'] ?? '') ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Update</button>
                    </form>
                    <?php if (!empty($contact['follow_up_date'])): ?>
                        <p class="mt-2"><strong>Scheduled:</strong> <?= h(date('F j, Y', strtotime($contact['follow_up_date']))) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Outreach Status</span>
                    <span class="badge bg-info-subtle text-dark border">Cadence: <?= h(ucfirst($currentCadence)) ?></span>
                </div>
                <div class="card-body">
                    <!-- One form handles BOTH cadence and stage so the selection persists -->
                    <form method="POST" action="update_outreach_stage.php" class="mb-2" id="formOutreach">
                        <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">

                        <label class="mb-1"><strong>Cadence Type:</strong></label>
                        <select name="cadence_type" id="cadence_type" class="form-select form-select-sm mb-3">
                            <option value="voicemail" <?= $currentCadence === 'voicemail' ? 'selected' : '' ?>>Voicemail (Phone-centric)</option>
                            <option value="mixed" <?= $currentCadence === 'mixed' ? 'selected' : '' ?>>Mixed (Phone + Other)</option>
                        </select>

                        <label class="mb-1"><strong>Stage:</strong></label>
                        <select name="outreach_stage" id="outreach_stage" class="form-select form-select-sm mt-1 mb-2" onchange="confirmStageChange(this.form)">
                            <?php
                            foreach ($cadenceLabels[$currentCadence] as $stageNum => $label):
                            ?>
                                <option value="<?= (int)$stageNum ?>" <?= ((int)$defaultStage === (int)$stageNum) ? 'selected' : '' ?>>
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Cadence: 2 calls/week × 3 weeks → ~1.5 calls/week × 2 weeks → 1 call/week × 3 weeks.
                        </div>
                    </form>

                    <form method="POST" action="update_outreach_status.php">
                        <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
                        <label><strong>Status:</strong></label>
                        <select name="outreach_status" class="form-select form-select-sm mt-1" onchange="this.form.submit()">
                            <?php
                            $statuses = ['Active', 'Paused', 'Do Not Contact', 'Completed'];
                            foreach ($statuses as $status): ?>
                                <option value="<?= h($status) ?>" <?= ($contact['outreach_status'] ?? 'Active') === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <p class="mt-3"><strong>Last Touch:</strong> <?= !empty($contact['last_touch_date']) ? h(date('F j, Y', strtotime($contact['last_touch_date']))) : 'Never' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: Scripts Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div id="scripts-card" class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>Scripts</span>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group input-group-sm" style="width: auto;">
                            <label class="input-group-text" for="scriptType">Type</label>
                            <select id="scriptType" class="form-select">
                                <option value="cold_call">Cold Call</option>
                                <option value="voicemail">Voicemail</option>
                            </select>
                        </div>

                        <?php if (!empty($associated_jobs)): ?>
                            <div class="input-group input-group-sm" style="width:auto;">
                                <label class="input-group-text" for="jobSelect">Job</label>
                                <select id="jobSelect" class="form-select">
                                    <option value="">(none)</option>
                                    <?php foreach ($associated_jobs as $job): ?>
                                        <option value="<?= (int)$job['id'] ?>"><?= h($job['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="input-group input-group-sm" style="width:auto;">
                            <label class="input-group-text" for="toneSelect">Tone</label>
                            <select id="toneSelect" class="form-select">
                                <option value="auto" selected>Auto</option>
                                <option value="friendly">Friendly</option>
                                <option value="consultative">Consultative</option>
                                <option value="direct">Direct</option>
                            </select>
                        </div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="toggleSmalltalk" checked>
                            <label class="form-check-label small" for="toggleSmalltalk">Small-talk</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="toggleMicroOffer" checked>
                            <label class="form-check-label small" for="toggleMicroOffer">Micro-offer</label>
                        </div>

                        <span class="badge text-bg-light" id="toneBadge">Tone: Auto</span>
                        <span class="badge text-bg-secondary" id="cadenceBadge">Cadence: <?= h(ucfirst($currentCadence)) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="scriptError" class="alert alert-danger d-none mb-3"></div>

                    <!-- Stage/Company line; JS will append Region/Location -->
                    <div id="stageLine" class="mb-2 small text-muted"
                         data-base="Stage: Touch <?= (int)($defaultStage) ?> • Cadence: <?= h(ucfirst($currentCadence)) ?><?php if ($client): ?> • Company: <?= h($client['name']) ?><?php endif; ?>">
                        Stage: Touch <?= (int)($defaultStage) ?> • Cadence: <?= h(ucfirst($currentCadence)) ?><?php if ($client): ?> • Company: <?= h($client['name']) ?><?php endif; ?>
                    </div>

                    <!-- NEW: Context badges (Region/Location) -->
                    <div id="contextBadges" class="mb-2"></div>

                    <textarea id="scriptOutput" class="form-control font-monospace" rows="8" readonly style="white-space: pre-wrap;"></textarea>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">
                            <span id="templateName">Template: —</span> • <span id="toneUsedLabel">Tone used: —</span>
                        </small>
                        <div class="d-flex gap-2">
                            <button id="printBtn" type="button" class="btn btn-sm btn-outline-secondary">Print</button>
                            <button id="copyBtn" type="button" class="btn btn-sm btn-outline-primary">Copy</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: Live Calls — Objections & Rebuttals -->
    <div class="row mt-4">
        <div class="col-12">
            <div id="rebuttals-card" class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>Live Calls: Objections &amp; Rebuttals</span>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group input-group-sm" style="width:auto;">
                            <label class="input-group-text" for="rebuttalTone">Tone</label>
                            <select id="rebuttalTone" class="form-select">
                                <option value="all" selected>All</option>
                                <option value="friendly">Friendly</option>
                                <option value="consultative">Consultative</option>
                                <option value="direct">Direct</option>
                            </select>
                        </div>
                        <div class="input-group input-group-sm" style="width: 320px;">
                            <span class="input-group-text">Search</span>
                            <input id="rebuttalSearch" type="text" class="form-control" placeholder="Type keyword (e.g., budget, vendors, send info)">
                        </div>
                        <span class="badge text-bg-light" id="rebuttalCount">—</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="rebuttalError" class="alert alert-danger d-none mb-3"></div>
                    <div id="rebuttalsContent"><div class="text-muted">Loading…</div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW ROW: Contact Status (entity-scoped) -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Contact Status</span>
                    <?php if ($currentContactStatus): ?>
                        <span class="badge bg-secondary"><?= h($currentContactStatus) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="update_contact_status.php" class="row g-2">
                        <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
                        <div class="col-12">
                            <label for="contact_status" class="form-label"><strong>Set Status</strong></label>
                            <select id="contact_status" name="contact_status" class="form-select" required>
                                <option value="">-- Select Status --</option>
                                <?php foreach ($contactStatusList as $category => $substatuses): ?>
                                    <optgroup label="<?= h($category) ?>">
                                        <?php foreach ($substatuses as $sub): ?>
                                            <option value="<?= h($sub) ?>" <?= ($currentContactStatus === $sub ? 'selected' : '') ?>>
                                                <?= h($sub) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="contact_status_note" class="form-label">Note (optional)</label>
                            <textarea id="contact_status_note" name="note" class="form-control" rows="2" placeholder="Add context for this status change (optional)"></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-sm btn-primary">Update Status</button>
                        </div>
                    </form>
                    <small class="text-muted d-block mt-2">
                        Uses the contact-specific status list (separate from candidate statuses).
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Associated Jobs -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Associated Jobs</span>
                    <a href="associate.php?contact_id=<?= (int)$contact['id'] ?>&return=view_contact.php?id=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-outline-primary">Associate Job</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($associated_jobs)): ?>
                        <ul class="list-group">
                            <?php foreach ($associated_jobs as $job): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="view_job.php?id=<?= (int)$job['id'] ?>"><?= h($job['title']) ?></a>
                                    <span class="badge bg-secondary"><?= h($job['status']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted mb-0">No jobs associated with this contact.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Outreach Template Preview -->
    <?php if ($outreach_template): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Outreach Template</div>
                    <div class="card-body">
                        <p><strong>Channel:</strong> <?= ucfirst(h($outreach_template['channel'] ?? '')) ?></p>
                        <?php if (!empty($outreach_template['subject'])): ?>
                            <p><strong>Subject:</strong> <?= h($outreach_template['subject']) ?></p>
                        <?php endif; ?>
                        <div style="white-space: pre-wrap; word-wrap: break-word;">
                            <?= nl2br(h($outreach_template['body'] ?? '')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Notes Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header">Add Note</div>
                <div class="card-body">
                    <form action="add_note.php" method="POST">
                        <input type="hidden" name="module_type" value="contact">
                        <input type="hidden" name="module_id" value="<?= (int)$contact['id'] ?>">
                        <div class="mb-3">
                            <textarea name="note" class="form-control" rows="3" placeholder="Enter your note here..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success">Add Note</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Past Notes</div>
                <div class="card-body">
                    <?php if (!empty($notes)): ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="mb-4">
                                <div class="small text-muted"><?= h(date('F j, Y \a\t g:i A', strtotime($note['created_at']))) ?></div>
                                <div><?= nl2br(h($note['content'])) ?></div>
                                <div class="d-flex align-items-start gap-2 mt-2">
                                    <a href="edit_note.php?id=<?= (int)$note['id'] ?>&contact_id=<?= (int)$contact['id'] ?>&return=contact&id_return=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="delete_note.php?id=<?= (int)$note['id'] ?>&return=contact&id_return=<?= (int)$contact['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this note?');">Delete</a>
                                </div>
                                <hr>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No notes yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* small highlight when scrolling to Scripts card */
#scripts-card.ring { box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); transition: box-shadow .3s ease; }
/* compact accordion buttons */
.rebuttal-copy-btn { white-space: nowrap; }
</style>

<script>
function confirmStageChange(form) {
    if (confirm("Are you sure you want to update the outreach stage and schedule the next follow-up?")) {
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const scriptTypeEl   = document.getElementById('scriptType');
    const jobSelectEl    = document.getElementById('jobSelect');
    const toneSelectEl   = document.getElementById('toneSelect');
    const toggleSmall    = document.getElementById('toggleSmalltalk');
    const toggleOffer    = document.getElementById('toggleMicroOffer');

    const outputEl       = document.getElementById('scriptOutput');
    const toneBadge      = document.getElementById('toneBadge');
    const toneUsedLabel  = document.getElementById('toneUsedLabel');
    const templateName   = document.getElementById('templateName');
    const errorBox       = document.getElementById('scriptError');
    const copyBtn        = document.getElementById('copyBtn');
    const printBtn       = document.getElementById('printBtn');

    const stageLineEl    = document.getElementById('stageLine');
    const badgesEl       = document.getElementById('contextBadges');

    const cadenceSel     = document.getElementById('cadence_type');
    const cadenceBadge   = document.getElementById('cadenceBadge');
    const stageSelectEl  = document.getElementById('outreach_stage');

    const rebuttalContent = document.getElementById('rebuttalsContent');
    const rebuttalError   = document.getElementById('rebuttalError');
    const rebuttalSearch  = document.getElementById('rebuttalSearch');
    const rebuttalCount   = document.getElementById('rebuttalCount');
    const rebuttalToneEl  = document.getElementById('rebuttalTone');

    const CONTACT_ID = '<?= (int)$contact['id'] ?>';
    const CLIENT_ID  = '<?= (int)($contact['client_id'] ?? 0) ?>';

    // Centralized cadence labels embedded from PHP (built via cadence_lookup when available)
    const CADENCE_LABELS = <?= json_encode($cadenceLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    let lastToneUsed = null; // from server response, used for logging
    let rebuttalDebounce = null;

    function escapeHtml(s){
        return String(s)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
    }

    function updateContextUI(ctx){
        // Badges
        const region = ctx && ctx.region ? String(ctx.region) : '';
        const loc    = ctx && ctx.location ? String(ctx.location) : '';

        const chips = [];
        if (region) chips.push('<span class="badge rounded-pill text-bg-secondary me-1">Region: ' + escapeHtml(region) + '</span>');
        if (loc)    chips.push('<span class="badge rounded-pill text-bg-light border me-1">Location: ' + escapeHtml(loc) + '</span>');
        badgesEl.innerHTML = chips.length ? chips.join(' ') : '<span class="text-muted">—</span>';

        // Stage/Company line with appended context
        const base = stageLineEl.getAttribute('data-base') || stageLineEl.textContent || '';
        let extra = '';
        if (region) extra += ' • Region: ' + region;
        if (loc)    extra += ' • Location: ' + loc;
        stageLineEl.textContent = base + extra;
    }

    function rebuildStageOptions(cadenceType) {
        const labels = CADENCE_LABELS[cadenceType] || {};
        const currentValue = stageSelectEl ? stageSelectEl.value : '';
        if (!stageSelectEl) return;

        // Clear options
        while (stageSelectEl.firstChild) stageSelectEl.removeChild(stageSelectEl.firstChild);

        // Rebuild
        Object.keys(labels).forEach(k => {
            const opt = document.createElement('option');
            opt.value = String(k);
            opt.textContent = labels[k];
            stageSelectEl.appendChild(opt);
        });

        // Try to keep the same stage selected; fall back to first available
        if (currentValue && labels[currentValue]) {
            stageSelectEl.value = currentValue;
        } else {
            const first = stageSelectEl.querySelector('option');
            if (first) stageSelectEl.value = first.value;
        }
    }

    async function renderScript() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const cadenceVal = cadenceSel ? cadenceSel.value : '<?= h($currentCadence) ?>';
        const stageVal   = stageSelectEl && stageSelectEl.value ? stageSelectEl.value : '<?= (int)$defaultStage ?>';
        const scriptTypeVal = scriptTypeEl ? scriptTypeEl.value : 'voicemail';
        const toneVal    = toneSelectEl ? (toneSelectEl.value || 'auto') : 'auto';

        const params = new URLSearchParams({
            // legacy keys (kept)
            script_type: scriptTypeVal,
            tone: toneVal,
            // expected keys for renderer/ajax
            script_type_slug: scriptTypeVal,
            tone_mode: toneVal,
            touch_number: stageVal,
            delivery_type: scriptTypeVal,
            contact_id: CONTACT_ID,
            client_id: CLIENT_ID,
            include_smalltalk: toggleSmall.checked ? '1' : '0',
            include_micro_offer: toggleOffer.checked ? '1' : '0',
            cadence_type: cadenceVal,
            _ts: String(Date.now())
        });

        if (jobSelectEl && jobSelectEl.value) {
            params.set('job_id', jobSelectEl.value);
        }

        try {
            const url = '../ajax/render_script.php?' + params.toString();
            const res = await fetch(url, { credentials: 'same-origin' });

            const raw = await res.text();
            let data = null;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                const snippet = raw.slice(0, 200);
                throw new Error(`Server returned non-JSON (status ${res.status}). URL: ${url}\n${snippet}`);
            }

            if (!res.ok || !data || data.ok === false) {
                const msg = (data && data.message) ? data.message : `HTTP ${res.status}`;
                throw new Error(`Render failed: ${msg}`);
            }

            // Force the textarea to repaint so tone changes are always visible
            outputEl.value = '';
            setTimeout(() => {
                outputEl.value = data.text || '';
            }, 5);

            lastToneUsed = data.tone_used || null;

            const toneMode = toneVal;
            toneBadge.textContent = 'Tone: ' + (toneMode === 'auto' ? 'Auto' : (toneMode.charAt(0).toUpperCase() + toneMode.slice(1) + ' (override)'));
            toneUsedLabel.textContent = 'Tone used: ' + (data.tone_used || '—');
            templateName.textContent = 'Template: ' + (data.template_name || '—');

            updateContextUI(data.context || {});

            if (cadenceBadge && cadenceVal) {
                const cap = cadenceVal.charAt(0).toUpperCase() + cadenceVal.slice(1);
                cadenceBadge.textContent = 'Cadence: ' + cap;
            }

            logActivity('render');

        } catch (e) {
            outputEl.value = '';
            errorBox.textContent = e.message;
            errorBox.classList.remove('d-none');
            console.error(e);

            badgesEl.innerHTML = '<span class="text-muted">—</span>';
            const base = stageLineEl.getAttribute('data-base') || '';
            if (base) stageLineEl.textContent = base;
        }
    }

    async function fetchRebuttals() {
        if (!rebuttalContent) return;

        rebuttalError.classList.add('d-none');
        rebuttalError.textContent = '';

        const q = rebuttalSearch ? rebuttalSearch.value.trim() : '';
        const tone = rebuttalToneEl ? rebuttalToneEl.value : 'all';

        const params = new URLSearchParams({
            script_type: scriptTypeEl ? scriptTypeEl.value : 'cold_call',
            q: q,
            tone: tone,
            _ts: String(Date.now())
        });

        try {
            const url = '../ajax/fetch_rebuttals.php?' + params.toString();
            const res = await fetch(url, { credentials: 'same-origin' });
            const raw = await res.text();
            let data = null;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                const snippet = raw.slice(0, 200);
                throw new Error(`Non-JSON from fetch_rebuttals (status ${res.status}): ${snippet}`);
            }

            if (!res.ok || !data || data.ok === false) {
                const msg = (data && data.message) ? data.message : `HTTP ${res.status}`;
                throw new Error(msg);
            }

            rebuttalContent.innerHTML = data.html || '<div class="text-muted">No results.</div>';
            if (rebuttalCount) rebuttalCount.textContent = (data.count != null ? data.count : '—') + ' match' + (data.count === 1 ? '' : 'es');

            // Wire up copy buttons inside the generated HTML
            rebuttalContent.querySelectorAll('[data-copy-text]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const txt = btn.getAttribute('data-copy-text') || '';
                    navigator.clipboard.writeText(txt).then(() => {
                        const original = btn.textContent;
                        btn.textContent = 'Copied';
                        setTimeout(() => btn.textContent = original, 1000);
                    }).catch(()=>{});
                });
            });

        } catch (e) {
            rebuttalContent.innerHTML = '';
            rebuttalError.textContent = e.message;
            rebuttalError.classList.remove('d-none');
        }
    }

    async function logActivity(action) {
        try {
            const flags = {
                smalltalk: !!toggleSmall.checked,
                micro_offer: !!toggleOffer.checked
            };
            const form = new URLSearchParams({
                action: action,
                script_type: (scriptTypeEl ? scriptTypeEl.value : 'voicemail'),
                tone_used: lastToneUsed || (toneSelectEl.value || 'auto'),
                contact_id: CONTACT_ID,
                client_id: CLIENT_ID,
                job_id: jobSelectEl && jobSelectEl.value ? jobSelectEl.value : ''
            });
            form.append('flags_json', JSON.stringify(flags));
            await fetch('../ajax/log_script_activity.php?_ts=' + Date.now(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            });
        } catch (e) {
            console.warn('log failed', e);
        }
    }

    function buildPrintUrl() {
        const cadenceVal = cadenceSel ? cadenceSel.value : '<?= h($currentCadence) ?>';
        const stageVal   = stageSelectEl && stageSelectEl.value ? stageSelectEl.value : '<?= (int)$defaultStage ?>';
        const scriptTypeVal = scriptTypeEl ? scriptTypeEl.value : 'voicemail';
        const toneVal    = toneSelectEl ? (toneSelectEl.value || 'auto') : 'auto';

        const params = new URLSearchParams({
            // legacy
            script_type: scriptTypeVal,
            tone: toneVal,
            // expected by renderer/print
            script_type_slug: scriptTypeVal,
            tone_mode: toneVal,
            touch_number: stageVal,
            delivery_type: scriptTypeVal,
            contact_id: CONTACT_ID,
            client_id: CLIENT_ID,
            include_smalltalk: toggleSmall.checked ? '1' : '0',
            include_micro_offer: toggleOffer.checked ? '1' : '0',
            cadence_type: cadenceVal,
            print: '1',
            _ts: String(Date.now())
        });
        if (jobSelectEl && jobSelectEl.value) {
            params.set('job_id', jobSelectEl.value);
        }
        return 'print_script.php?' + params.toString();
    }

    // Event bindings (Scripts)
    if (scriptTypeEl) scriptTypeEl.addEventListener('change', () => { renderScript(); fetchRebuttals(); });
    if (jobSelectEl)  jobSelectEl.addEventListener('change', renderScript);
    if (toneSelectEl) toneSelectEl.addEventListener('change', renderScript);
    if (toggleSmall)  toggleSmall.addEventListener('change', renderScript);
    if (toggleOffer)  toggleOffer.addEventListener('change', renderScript);

    if (cadenceSel) {
        cadenceSel.addEventListener('change', () => {
            const val = cadenceSel.value || 'voicemail';
            rebuildStageOptions(val);
            if (cadenceBadge) {
                const cap = val.charAt(0).toUpperCase() + val.slice(1);
                cadenceBadge.textContent = 'Cadence: ' + cap;
            }
            renderScript(); // instant preview
            const form = document.getElementById('formOutreach');
            if (form) form.submit();
        });
    }

    // Event bindings (Rebuttals)
    if (rebuttalSearch) {
        rebuttalSearch.addEventListener('input', () => {
            clearTimeout(rebuttalDebounce);
            rebuttalDebounce = setTimeout(fetchRebuttals, 250);
        });
    }
    if (rebuttalToneEl) {
        rebuttalToneEl.addEventListener('change', fetchRebuttals);
    }

    if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
            try {
                outputEl.select();
                document.execCommand('copy');
                copyBtn.textContent = 'Copied';
                logActivity('copy');
                setTimeout(() => copyBtn.textContent = 'Copy', 1200);
            } catch (e) {
                console.error(e);
            }
        });
    }

    if (printBtn) {
        printBtn.addEventListener('click', async () => {
            logActivity('print');
            const url = buildPrintUrl();
            window.open(url, '_blank', 'noopener');
        });
    }

    // Initial render + initial rebuttals load
    rebuildStageOptions('<?= h($currentCadence) ?>');
    const initialStage = '<?= (int)$defaultStage ?>';
    if (stageSelectEl && initialStage && CADENCE_LABELS['<?= h($currentCadence) ?>'] && CADENCE_LABELS['<?= h($currentCadence) ?>'][initialStage]) {
        stageSelectEl.value = initialStage;
    }
    renderScript();
    fetchRebuttals();
});
</script>

<?php
// ---- Merge data for potential email compose or other UI bits ----
$recipientData = [
    'first_name'   => $contact['first_name'] ?? '',
    'last_name'    => $contact['last_name']  ?? '',
    'full_name'    => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
    'company_name' => $client['name'] ?? ($contact['company'] ?? ''), // prefer linked client name
    'job_title'    => $contact['title'] ?? '',
];

$userData = [
    'user_name'  => $_SESSION['user']['name']  ?? '',
    'user_email' => $_SESSION['user']['email'] ?? '',
    'user_phone' => $_SESSION['user']['phone'] ?? '',
];

$mergeData = array_merge($recipientData, $userData, [
    'today' => date('F j, Y'),
]);
?>

<script>
// Expose merge context (unchanged, in case other components use it)
window.ComposeEmail = window.ComposeEmail || {};
window.ComposeEmail.userData = <?= json_encode($userData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.ComposeEmail.recipientData = <?= json_encode($recipientData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.ComposeEmail.mergeData = <?= json_encode($mergeData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
