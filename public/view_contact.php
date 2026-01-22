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
$currentContactStatus = $contact['contact_status'] ?? '';

// Grab outreach stage for the Scripts panel context
$defaultStage = isset($contact['outreach_stage']) && (int)$contact['outreach_stage'] > 0 ? (int)$contact['outreach_stage'] : 1;

// ---- Unified Cadence Only ----
$currentCadence = 'unified';

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
        $raw = trim(implode(', ', array_filter(
            [$addrStreet, $addrCity, $addrState, $addrZip, $addrCountry],
            fn($x) => $x !== ''
        )));
        if ($raw !== '') {
            $regionDisplay = infer_region_from_location($raw);
        }
    }
} catch (Throwable $e) {
    $regionDisplay = null;
}

/**
 * Build cadence labels from centralized cadence_lookup() when available.
 * Falls back to the unified labels if not present.
 * Returns array like: ['unified' => [1 => 'Touch 1 – ...', ...]]
 */
function build_cadence_labels(bool $haveCadenceLookup): array
{
    $fallbackUnified = [
        1  => 'Touch 1 – Call',
        2  => 'Touch 2 – Email / Call (No Email)',
        3  => 'Touch 3 – Call',
        4  => 'Touch 4 – LinkedIn Connection',
        5  => 'Touch 5 – Call',
        6  => 'Touch 6 – Email / Call (No Email)',
        7  => 'Touch 7 – Call',
        8  => 'Touch 8 – LinkedIn / Email (Fallback)',
        9  => 'Touch 9 – Call',
        10 => 'Touch 10 – Email / Call (No Email)',
        11 => 'Touch 11 – Call',
        12 => 'Touch 12 – Close-the-Loop Email',
    ];

    $labels = [
        'unified' => $fallbackUnified,
    ];

    if (!$haveCadenceLookup) {
        return $labels;
    }

    $built = [];
    for ($i = 1; $i <= 50; $i++) {
        $meta = cadence_lookup($i, 'unified');
        if (!is_array($meta) || empty($meta['label'])) {
            break;
        }
        $built[$i] = (string)$meta['label'];
        if (array_key_exists('delay_bd', $meta) && (int)$meta['delay_bd'] === 0) {
            break;
        }
    }

    if (!empty($built)) {
        $labels['unified'] = $built;
    }

    return $labels;
}

$cadenceLabels = build_cadence_labels($haveCadenceLookup);

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

    <!-- Draggable + collapsible grid -->
    <div class="row g-4" id="card-grid">
        <!-- Contact Info -->
        <div class="col-md-4 draggable-card-wrapper" data-card-id="contact_info">
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

                    <p class="mt-2"><strong>Region (auto):</strong> <?= h($regionDisplay ?? '—') ?></p>
                </div>
            </div>
        </div>

        <!-- Position & Company -->
        <div class="col-md-4 draggable-card-wrapper" data-card-id="position_company">
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

        <!-- Follow-Up + Outreach -->
        <div class="col-md-4 draggable-card-wrapper" data-card-id="follow_up_outreach">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Follow-Up</span>
                </div>
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
                    <span class="badge bg-info-subtle text-dark border">Cadence: Unified</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="update_outreach_stage.php" class="mb-2" id="formOutreach">
                        <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
                        <input type="hidden" name="cadence_type" value="unified">

                        <label class="mb-1"><strong>Stage:</strong></label>
                        <select name="outreach_stage" id="outreach_stage" class="form-select form-select-sm mt-1 mb-2" onchange="confirmStageChange(this.form)">
                            <?php foreach ($cadenceLabels[$currentCadence] as $stageNum => $label): ?>
                                <option value="<?= (int)$stageNum ?>" <?= ((int)$defaultStage === (int)$stageNum) ? 'selected' : '' ?>>
                                    <?= h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Unified 12-touch cadence (~22 business days) with calls, email, and LinkedIn.
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

        <!-- Scripts Panel -->
        <div class="col-12 draggable-card-wrapper" data-card-id="scripts">
            <div id="scripts-card" class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>Scripts</span>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge text-bg-primary">Pipeline (Current Touch)</span>

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

                        <span class="badge text-bg-light" id="toneBadge">Tone: Auto</span>
                        <span class="badge text-bg-secondary" id="cadenceBadge">Cadence: Unified</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="scriptError" class="alert alert-danger d-none mb-3"></div>

                    <div id="stageLine" class="mb-2 small text-muted"
                         data-base="Stage: Touch <?= (int)$defaultStage ?> • Cadence: Unified<?php if ($client): ?> • Company: <?= h($client['name']) ?><?php endif; ?>">
                        Stage: Touch <?= (int)$defaultStage ?> • Cadence: Unified<?php if ($client): ?> • Company: <?= h($client['name']) ?><?php endif; ?>
                    </div>

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

        <!-- Rebuttals -->
        <div class="col-12 draggable-card-wrapper" data-card-id="rebuttals">
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

        <!-- Contact Status -->
        <div class="col-md-6 draggable-card-wrapper" data-card-id="contact_status">
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

        <!-- Associated Jobs -->
        <div class="col-12 draggable-card-wrapper" data-card-id="associated_jobs">
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

        <!-- Outreach Template -->
        <?php if ($outreach_template): ?>
            <div class="col-12 draggable-card-wrapper" data-card-id="outreach_template">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Outreach Template</span>
                    </div>
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
        <?php endif; ?>

        <!-- Notes -->
        <div class="col-12 draggable-card-wrapper" data-card-id="notes">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Add Note</span>
                </div>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Past Notes</span>
                </div>
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
    </div> <!-- /#card-grid -->
</div>

<style>
#scripts-card.ring {
    box-shadow: 0 0 0 .25rem rgba(13,110,253,.25);
    transition: box-shadow .3s ease;
}
.rebuttal-copy-btn { white-space: nowrap; }

/* Drag & drop */
.draggable-card-wrapper {
    cursor: move;
}
.draggable-card-wrapper.dragging {
    opacity: 0.6;
}
.draggable-card-wrapper.drag-over {
    outline: 2px dashed #0d6efd;
    outline-offset: 2px;
}

/* Collapse behavior: hide everything but header */
.card.card-collapsed > :not(.card-header) {
    display: none !important;
}

/* Small toggle button */
.card-toggle-btn {
    width: 1.6rem;
    height: 1.6rem;
    padding: 0;
    line-height: 1;
    font-weight: bold;
    font-size: 0.9rem;
}
</style>

<script>
function confirmStageChange(form) {
    if (confirm("Are you sure you want to update the outreach stage and schedule the next follow-up?")) {
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const jobSelectEl    = document.getElementById('jobSelect');
    const toneSelectEl   = document.getElementById('toneSelect');

    const outputEl       = document.getElementById('scriptOutput');
    const toneBadge      = document.getElementById('toneBadge');
    const toneUsedLabel  = document.getElementById('toneUsedLabel');
    const templateName   = document.getElementById('templateName');
    const errorBox       = document.getElementById('scriptError');
    const copyBtn        = document.getElementById('copyBtn');
    const printBtn       = document.getElementById('printBtn');

    const stageLineEl    = document.getElementById('stageLine');
    const badgesEl       = document.getElementById('contextBadges');

    const stageSelectEl  = document.getElementById('outreach_stage');

    const rebuttalContent = document.getElementById('rebuttalsContent');
    const rebuttalError   = document.getElementById('rebuttalError');
    const rebuttalSearch  = document.getElementById('rebuttalSearch');
    const rebuttalCount   = document.getElementById('rebuttalCount');
    const rebuttalToneEl  = document.getElementById('rebuttalTone');

    const CONTACT_ID = '<?= (int)$contact['id'] ?>';
    const CLIENT_ID  = '<?= (int)($contact['client_id'] ?? 0) ?>';

    const CADENCE_TYPE = 'unified';
    const CADENCE_LABELS = <?= json_encode($cadenceLabels['unified'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    let lastToneUsed = null;
    let rebuttalDebounce = null;

    const grid = document.getElementById('card-grid');
    const LAYOUT_KEY = 'view_contact_layout_v1';
    const COLLAPSE_KEY = 'view_contact_collapsed_v1';

    // ---- Drag & Drop ----
    let dragSrcWrapper = null;

    function saveLayout() {
        if (!grid) return;
        const ids = [];
        grid.querySelectorAll('.draggable-card-wrapper').forEach(el => {
            if (el.dataset.cardId) ids.push(el.dataset.cardId);
        });
        try {
            localStorage.setItem(LAYOUT_KEY, ids.join(','));
        } catch (e) {
            console.warn('Unable to save layout', e);
        }
    }

    function applySavedLayout() {
        if (!grid) return;
        let saved = null;
        try {
            saved = localStorage.getItem(LAYOUT_KEY);
        } catch (e) {
            saved = null;
        }
        if (!saved) return;

        const order = saved.split(',').map(s => s.trim()).filter(Boolean);
        if (!order.length) return;

        const map = {};
        grid.querySelectorAll('.draggable-card-wrapper').forEach(el => {
            const id = el.dataset.cardId;
            if (id) map[id] = el;
        });

        order.forEach(id => {
            if (map[id]) {
                grid.appendChild(map[id]);
            }
        });
    }

    function handleDragStart(e) {
        dragSrcWrapper = this;
        this.classList.add('dragging');
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.cardId || '');
        }
    }

    function handleDragEnd() {
        this.classList.remove('dragging');
        grid.querySelectorAll('.draggable-card-wrapper').forEach(el => el.classList.remove('drag-over'));
    }

    function handleDragOver(e) {
        e.preventDefault();
        if (e.dataTransfer) {
            e.dataTransfer.dropEffect = 'move';
        }
    }

    function handleDragEnter() {
        if (this === dragSrcWrapper) return;
        this.classList.add('drag-over');
    }

    function handleDragLeave() {
        this.classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        if (!dragSrcWrapper || dragSrcWrapper === this) return;
        if (!grid) return;

        const children = Array.from(grid.querySelectorAll('.draggable-card-wrapper'));
        const srcIndex = children.indexOf(dragSrcWrapper);
        const targetIndex = children.indexOf(this);

        if (srcIndex < 0 || targetIndex < 0) return;

        if (srcIndex < targetIndex) {
            grid.insertBefore(dragSrcWrapper, this.nextSibling);
        } else {
            grid.insertBefore(dragSrcWrapper, this);
        }
        saveLayout();
    }

    function initDragDrop() {
        if (!grid) return;
        const wrappers = grid.querySelectorAll('.draggable-card-wrapper');
        wrappers.forEach(el => {
            el.setAttribute('draggable', 'true');
            el.addEventListener('dragstart', handleDragStart);
            el.addEventListener('dragend', handleDragEnd);
            el.addEventListener('dragover', handleDragOver);
            el.addEventListener('dragenter', handleDragEnter);
            el.addEventListener('dragleave', handleDragLeave);
            el.addEventListener('drop', handleDrop);
        });
    }

    // ---- Collapse / Expand per card ----
    function initCardCollapse() {
        if (!grid) return;

        let savedState = {};
        try {
            const raw = localStorage.getItem(COLLAPSE_KEY);
            if (raw) savedState = JSON.parse(raw) || {};
        } catch (e) {
            savedState = {};
        }

        const wrappers = grid.querySelectorAll('.draggable-card-wrapper');
        wrappers.forEach(wrapper => {
            const cardId = wrapper.dataset.cardId;
            const card = wrapper.querySelector('.card');
            if (!card || !cardId) return;

            const header = card.querySelector('.card-header');
            if (!header) return;

            // Make sure header has flex layout
            if (!header.classList.contains('d-flex')) {
                header.classList.add('d-flex', 'justify-content-between', 'align-items-center');
            } else {
                header.classList.add('align-items-center');
            }

            // Avoid double-adding
            if (header.querySelector('.card-toggle-btn')) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-light border card-toggle-btn ms-2';
            btn.innerHTML = '−';

            btn.addEventListener('click', () => {
                const collapsed = card.classList.toggle('card-collapsed');
                btn.innerHTML = collapsed ? '+' : '−';
                savedState[cardId] = collapsed ? 1 : 0;
                try {
                    localStorage.setItem(COLLAPSE_KEY, JSON.stringify(savedState));
                } catch (e) {
                    console.warn('Unable to save collapse state', e);
                }
            });

            header.appendChild(btn);

            // Apply saved state
            if (savedState[cardId]) {
                card.classList.add('card-collapsed');
                btn.innerHTML = '+';
            }
        });
    }

    applySavedLayout();
    initDragDrop();
    initCardCollapse();

    // ---- Scripts & Rebuttals logic ----
    function escapeHtml(s){
        return String(s)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;')
            .replace(/'/g,'&#039;');
    }

    function updateContextUI(ctx){
        const region = ctx && ctx.region ? String(ctx.region) : '';
        const loc    = ctx && ctx.location ? String(ctx.location) : '';

        const chips = [];
        if (region) chips.push('<span class="badge rounded-pill text-bg-secondary me-1">Region: ' + escapeHtml(region) + '</span>');
        if (loc)    chips.push('<span class="badge rounded-pill text-bg-light border me-1">Location: ' + escapeHtml(loc) + '</span>');
        badgesEl.innerHTML = chips.length ? chips.join(' ') : '<span class="text-muted">—</span>';

        const base = stageLineEl.getAttribute('data-base') || stageLineEl.textContent || '';
        let extra = '';
        if (region) extra += ' • Region: ' + region;
        if (loc)    extra += ' • Location: ' + loc;
        stageLineEl.textContent = base + extra;
    }

    function rebuildStageOptions() {
        const labels = CADENCE_LABELS || {};
        const currentValue = stageSelectEl ? stageSelectEl.value : '';
        if (!stageSelectEl) return;

        while (stageSelectEl.firstChild) stageSelectEl.removeChild(stageSelectEl.firstChild);

        Object.keys(labels).forEach(k => {
            const opt = document.createElement('option');
            opt.value = String(k);
            opt.textContent = labels[k];
            stageSelectEl.appendChild(opt);
        });

        if (currentValue && labels[currentValue]) {
            stageSelectEl.value = currentValue;
        } else {
            const first = stageSelectEl.querySelector('option');
            if (first) stageSelectEl.value = first.value;
        }
    }

    async function renderScript() {
        if (!outputEl || !stageLineEl) return;

        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const stageVal = stageSelectEl && stageSelectEl.value ? stageSelectEl.value : '<?= (int)$defaultStage ?>';
        const toneVal  = toneSelectEl ? (toneSelectEl.value || 'auto') : 'auto';

        // Canonical-only request: no legacy "type", no microoffer/smalltalk flags, no delivery_type.
        // TODO: If ../ajax/render_script.php still requires additional legacy params, update that endpoint (not this UI)
        //       to accept canonical params only: contact_id, touch_number, tone (+ optional context like client_id/job_id).
        const params = new URLSearchParams({
            contact_id: CONTACT_ID,
            touch_number: String(stageVal),
            tone: String(toneVal),
            cadence_type: CADENCE_TYPE,
            _ts: String(Date.now())
        });

        if (CLIENT_ID && String(CLIENT_ID) !== '0') {
            params.set('client_id', CLIENT_ID);
        }
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

            outputEl.value = '';
            setTimeout(() => {
                outputEl.value = data.text || '';
            }, 5);

            lastToneUsed = data.tone_used || null;

            const toneMode = toneVal;
            toneBadge.textContent = 'Tone: ' + (toneMode === 'auto'
                ? 'Auto'
                : (toneMode.charAt(0).toUpperCase() + toneMode.slice(1) + ' (override)')
            );
            toneUsedLabel.textContent = 'Tone used: ' + (data.tone_used || '—');
            templateName.textContent = 'Template: ' + (data.template_name || '—');

            updateContextUI(data.context || {});

            logActivity('render', stageVal);

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
            // Rebuttals are for LIVE CALLS.
            script_type: 'cold_call',
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
            if (rebuttalCount) rebuttalCount.textContent =
                (data.count != null ? data.count : '—') + ' match' + (data.count === 1 ? '' : 'es');

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

    async function logActivity(action, touchNumber) {
        try {
            const form = new URLSearchParams({
                action: action,
                script_type: 'pipeline',
                tone_used: lastToneUsed || (toneSelectEl ? (toneSelectEl.value || 'auto') : 'auto'),
                touch_number: String(touchNumber || ''),
                contact_id: CONTACT_ID,
                client_id: CLIENT_ID,
                job_id: jobSelectEl && jobSelectEl.value ? jobSelectEl.value : ''
            });

            // No smalltalk/microoffer flags. Those concepts are removed.
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
        const stageVal = stageSelectEl && stageSelectEl.value ? stageSelectEl.value : '<?= (int)$defaultStage ?>';
        const toneVal  = toneSelectEl ? (toneSelectEl.value || 'auto') : 'auto';

        const params = new URLSearchParams({
            contact_id: CONTACT_ID,
            touch_number: String(stageVal),
            tone: String(toneVal),
            cadence_type: CADENCE_TYPE,
            print: '1',
            _ts: String(Date.now())
        });

        if (CLIENT_ID && String(CLIENT_ID) !== '0') {
            params.set('client_id', CLIENT_ID);
        }
        if (jobSelectEl && jobSelectEl.value) {
            params.set('job_id', jobSelectEl.value);
        }

        return 'print_script.php?' + params.toString();
    }

    // Event bindings
    if (jobSelectEl)  jobSelectEl.addEventListener('change', renderScript);
    if (toneSelectEl) toneSelectEl.addEventListener('change', renderScript);
    if (stageSelectEl) stageSelectEl.addEventListener('change', renderScript);

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
                const stageVal = stageSelectEl && stageSelectEl.value ? stageSelectEl.value : '';
                logActivity('copy', stageVal);
                setTimeout(() => copyBtn.textContent = 'Copy', 1200);
            } catch (e) {
                console.error(e);
            }
        });
    }

    if (printBtn) {
        printBtn.addEventListener('click', async () => {
            const stageVal = stageSelectEl && stageSelectEl.value ? stageSelectEl.value : '';
            logActivity('print', stageVal);
            const url = buildPrintUrl();
            window.open(url, '_blank', 'noopener');
        });
    }

    // Initial load
    rebuildStageOptions();
    const initialStage = '<?= (int)$defaultStage ?>';
    if (stageSelectEl && initialStage && CADENCE_LABELS && CADENCE_LABELS[initialStage]) {
        stageSelectEl.value = initialStage;
    }

    renderScript();
    fetchRebuttals();
});
</script>

<?php
$recipientData = [
    'first_name'   => $contact['first_name'] ?? '',
    'last_name'    => $contact['last_name']  ?? '',
    'full_name'    => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')),
    'company_name' => (is_array($client) && isset($client['name']))
        ? $client['name']
        : ($contact['company'] ?? ''),
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
window.ComposeEmail = window.ComposeEmail || {};
window.ComposeEmail.userData = <?= json_encode($userData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.ComposeEmail.recipientData = <?= json_encode($recipientData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.ComposeEmail.mergeData = <?= json_encode($mergeData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
