<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$statusList = require __DIR__ . '/../config/status_list.php';

$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    echo "<div class='alert alert-danger'>Invalid candidate ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Helpers ----
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_viewable_inline(string $ext): bool {
    return in_array(strtolower($ext), ['pdf','png','jpg','jpeg','gif','webp'], true);
}
function file_icon(string $ext): string {
    $ext = strtolower($ext);
    if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) return '🖼️';
    if ($ext === 'pdf') return '📕';
    if (in_array($ext, ['doc','docx'])) return '📝';
    if (in_array($ext, ['xls','xlsx','csv'])) return '📊';
    if (in_array($ext, ['ppt','pptx'])) return '📽️';
    if (in_array($ext, ['txt','rtf'])) return '📄';
    return '📎';
}
function format_bytes(int $bytes): string {
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return sprintf('%.1f %s', $bytes, $units[$i]);
}
function build_file_view_url(string $relativePath): string {
    return 'file_view.php?path=' . urlencode($relativePath);
}
function build_file_download_url(string $relativePath): string {
    return 'file_download.php?path=' . urlencode($relativePath);
}
// Unified delete URLs (file_delete.php)
function build_delete_only_url(string $relativePath, string $returnUrl): string {
    return 'file_delete.php?mode=delete&path=' . urlencode($relativePath) . '&return=' . urlencode($returnUrl);
}
function build_delete_both_url(string $relativePath, int $candidateId, string $field, string $returnUrl): string {
    // Deletes file AND clears DB field
    return 'file_delete.php?mode=both&path=' . urlencode($relativePath)
        . '&candidate_id=' . $candidateId
        . '&field=' . urlencode($field)
        . '&return=' . urlencode($returnUrl);
}
function build_clear_only_url(int $candidateId, string $field, string $returnUrl): string {
    // Clears DB field only
    return 'file_delete.php?mode=clear&candidate_id=' . $candidateId
        . '&field=' . urlencode($field)
        . '&return=' . urlencode($returnUrl);
}
function render_file_actions_strict(string $relativePath, string $deleteUrl): string {
    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    $btns = '';
    if (is_viewable_inline($ext)) {
        $btns .= '<a class="btn btn-sm btn-outline-primary me-2" target="_blank" href="' . build_file_view_url($relativePath) . '">View</a>';
    }
    $btns .= '<a class="btn btn-sm btn-outline-secondary me-2" href="' . build_file_download_url($relativePath) . '">Download</a>';
    $btns .= '<a class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete file from disk and clear DB?\')" href="' . $deleteUrl . '">Delete</a>';
    return $btns;
}

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-warning'>Candidate not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Email compose link (uses compose_email.php) ----
$full_name = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
$return_to = 'view_candidate.php?id=' . (int)$candidate['id'];
$compose_params = [
    'to'           => $candidate['email'] ?? '',
    'name'         => $full_name,
    'related_type' => 'candidate',
    'related_id'   => (int)$candidate['id'],
    'return_to'    => $return_to,
];
$email_url = 'compose_email.php?' . http_build_query($compose_params);

$error = '';
$flash_message = $_GET['msg'] ?? null;

// Save note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (content, module_type, module_id, created_at) VALUES (?, 'candidate', ?, NOW())");
            $stmt->execute([$content, $candidate_id]);
            header("Location: view_candidate.php?id=$candidate_id&msg=Note+added+successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error saving note: " . h($e->getMessage());
        }
    } else {
        $error = "Note cannot be empty.";
    }
}

// Load notes
$stmt = $pdo->prepare("
    SELECT * FROM notes 
    WHERE 
        (module_type = 'candidate' AND module_id = ?) 
        OR 
        (module_type = 'association' AND module_id IN (
            SELECT id FROM associations WHERE candidate_id = ?
        ))
    ORDER BY created_at DESC
");
$stmt->execute([$candidate_id, $candidate_id]);
$all_notes = $stmt->fetchAll();

$candidate_notes = [];
$association_notes = [];
foreach ($all_notes as $note) {
    if ($note['module_type'] === 'association') {
        $association_notes[(int)$note['module_id']][] = $note;
    } else {
        $candidate_notes[] = $note;
    }
}

// Load job associations WITH contacts + client
$stmt = $pdo->prepare("
    SELECT 
        a.id AS association_id,
        a.status,
        j.id AS job_id,
        j.title,
        cl.id AS client_id,
        cl.name AS client_name,
        GROUP_CONCAT(
            CONCAT(
                c.id, ':',
                COALESCE(c.full_name, CONCAT(TRIM(c.first_name), ' ', TRIM(c.last_name)))
            )
            ORDER BY c.last_name, c.first_name
            SEPARATOR ','
        ) AS contact_pairs
    FROM associations a
    JOIN jobs j           ON a.job_id = j.id
    LEFT JOIN clients cl  ON j.client_id = cl.id
    LEFT JOIN job_contacts jc ON jc.job_id = j.id
    LEFT JOIN contacts c  ON c.id = jc.contact_id
    WHERE a.candidate_id = ?
    GROUP BY a.id, a.status, j.id, j.title, cl.id, cl.name
    ORDER BY a.created_at DESC, j.title ASC
");
$stmt->execute([$candidate_id]);
$associations = $stmt->fetchAll();

// Build a Job -> Contacts map + Job -> Client map for the Scripts panel
$jobContactsMap = []; // job_id => [ [id,name], ... ]
$jobClientMap   = []; // job_id => client_id
foreach ($associations as $a) {
    $jid = (int)$a['job_id'];
    $jobClientMap[$jid] = !empty($a['client_id']) ? (int)$a['client_id'] : 0;
    $jobContactsMap[$jid] = [];
    if (!empty($a['contact_pairs'])) {
        $pairs = explode(',', $a['contact_pairs']);
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $cid = (int)$parts[0];
                $cname = trim($parts[1]);
                if ($cid && $cname !== '') {
                    $jobContactsMap[$jid][] = ['id' => $cid, 'name' => $cname];
                }
            }
        }
    }
}
?>

<!-- Local tweaks -->
<style>
  .assoc-row { line-height: 1; gap: .5rem; }
  .assoc-sep { color: #6c757d; }
  .assoc-link { white-space: nowrap; }
  /* small highlight when scrolling to Scripts card */
  #scripts-card.ring { box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); transition: box-shadow .3s ease; }
</style>

<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= h($flash_message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?= h(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? '')) ?></h2>
        <div class="d-flex gap-2">
            <!-- Scripts button now scrolls to the Scripts panel -->
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                onclick="const el=document.getElementById('scripts-card'); if(el){ el.scrollIntoView({behavior:'smooth'}); el.classList.add('ring'); setTimeout(()=>el.classList.remove('ring'),1000);}">
                Scripts
            </button>

            <?php if (!empty($candidate['email'])): ?>
                <a href="<?= h($email_url) ?>" class="btn btn-sm btn-outline-primary">Email</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="No email on file">Email</button>
            <?php endif; ?>
            <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
        </div>
    </div>

    <!-- Candidate Info -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Information</span>
                    <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Phone:</strong> <?= h($candidate['phone'] ?? '') ?></p>
                    <p><strong>Email:</strong> <?= h($candidate['email'] ?? '') ?></p>
                    <p><strong>Secondary Email:</strong> <?= h($candidate['secondary_email'] ?? '') ?></p>
                    <p><strong>LinkedIn:</strong> <?= h($candidate['linkedin'] ?? '') ?></p>
                    <p><strong>Owner:</strong> <?= h($candidate['owner'] ?? '—') ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Address</span>
                    <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Street:</strong> <?= h($candidate['street'] ?? '') ?></p>
                    <p><strong>City:</strong> <?= h($candidate['city'] ?? '') ?></p>
                    <p><strong>State:</strong> <?= h($candidate['state'] ?? '') ?></p>
                    <p><strong>Zip:</strong> <?= h($candidate['zip'] ?? '') ?></p>
                    <p><strong>Country:</strong> <?= h($candidate['country'] ?? '') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Resume & Attachments -->
    <div class="row g-4 mt-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Resume Text</span>
                    <a href="view_resume_text.php?id=<?= urlencode($candidate_id) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Fullscreen</a>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($candidate['resume_filename'])): ?>
                        <?php
                            $projectRoot = realpath(__DIR__ . '/..');
                            $uploadsRoot = $projectRoot . '/uploads';
                            $rel = 'resumes/' . $candidate['resume_filename'];
                            $abs = $uploadsRoot . '/' . $rel;
                            if (is_file($abs)) {
                                $size = format_bytes((int)filesize($abs));
                                $ext  = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                                $icon = file_icon($ext);
                                $deleteUrl = build_delete_both_url($rel, (int)$candidate_id, 'resume_filename', $_SERVER['REQUEST_URI']);
                            ?>
                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <span><strong>Resume File:</strong> <?= $icon ?> <?= h($candidate['resume_filename']) ?> <span class="text-muted small ms-2">(<?= h($size) ?>)</span></span>
                                    <span class="d-flex gap-2">
                                        <?= render_file_actions_strict($rel, $deleteUrl) ?>
                                    </span>
                                </div>
                            <?php } else { ?>
                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <span><strong>Resume File:</strong> <em class="text-muted">missing on disk</em> (<?= h($candidate['resume_filename']) ?>)</span>
                                    <a class="btn btn-sm btn-outline-dark" onclick="return confirm('Clear database reference?')"
                                       href="<?= h(build_clear_only_url((int)$candidate_id, 'resume_filename', $_SERVER['REQUEST_URI'])) ?>">Clear</a>
                                </div>
                            <?php } ?>
                    <?php endif; ?>
                    <pre style="white-space: pre-wrap;"><?= h($candidate['resume_text'] ?? '') ?></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Attachments -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-header">Attachments</div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php
                        $attachments = [
                            'Resume'             => ['resume_filename',            'resumes/'],
                            'Formatted Resume'   => ['formatted_resume_filename', 'resumes/'],
                            'Cover Letter'       => ['cover_letter_filename',     'resumes/'],
                            'Other Attachment 1' => ['other_attachment_1',        'resumes/'],
                            'Other Attachment 2' => ['other_attachment_2',        'resumes/'],
                            'Contract'           => ['contract_filename',         'resumes/'],
                        ];
                        $projectRoot = realpath(__DIR__ . '/..');
                        $uploadsRoot = $projectRoot . '/uploads';

                        foreach ($attachments as $label => [$field, $folder]):
                            $filename = $candidate[$field] ?? null;
                            if (empty($filename)) continue;

                            $rel = $folder . $filename;
                            $abs = $uploadsRoot . '/' . $rel;

                            if (!is_file($abs)) {
                                ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><strong><?= h($label) ?>:</strong> <em class="text-muted">missing on disk</em> (<?= h($filename) ?>)</span>
                                    <a class="btn btn-sm btn-outline-dark" onclick="return confirm('Clear database reference?')"
                                       href="<?= h(build_clear_only_url((int)$candidate_id, $field, $_SERVER['REQUEST_URI'])) ?>">Clear</a>
                                </li>
                                <?php
                                continue;
                            }

                            $size = format_bytes((int)filesize($abs));
                            $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $icon = file_icon($ext);
                            $deleteUrl = build_delete_both_url($rel, (int)$candidate_id, $field, $_SERVER['REQUEST_URI']);
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong><?= h($label) ?>:</strong> <?= $icon ?> <?= h($filename) ?> <span class="text-muted small ms-2">(<?= h($size) ?>)</span></span>
                                <span class="d-flex gap-2">
                                    <?= render_file_actions_strict($rel, $deleteUrl) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Status & Roles -->
    <?php
    // Reuse $associations from earlier (already loaded)
    ?>

    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Status & Roles</span>
                    <a href="associate.php?candidate_id=<?= urlencode($candidate_id) ?>" class="btn btn-sm btn-success">+ Associate Job</a>
                </div>
                <div class="card-body">
                    <?php if (empty($associations)): ?>
                        <p class="text-muted">No associated roles yet.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($associations as $a): ?>
                                <?php
                                $contactLinks = [];
                                if (!empty($a['contact_pairs'])) {
                                    $pairs = explode(',', $a['contact_pairs']);
                                    foreach ($pairs as $pair) {
                                        $parts = explode(':', $pair, 2);
                                        if (count($parts) === 2) {
                                            $cid = (int)$parts[0];
                                            $cname = trim($parts[1]);
                                            if ($cid && $cname !== '') {
                                                $contactLinks[] = '<a href="view_contact.php?id=' . $cid . '" class="assoc-link">' . h($cname) . '</a>';
                                            }
                                        }
                                    }
                                }
                                ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="assoc-row d-flex flex-wrap align-items-center">
                                            <a href="view_job.php?id=<?= (int)$a['job_id'] ?>" class="assoc-link"><?= h($a['title']) ?></a>
                                            <span class="assoc-sep">•</span>
                                            <?= !empty($contactLinks) ? implode(', ', $contactLinks) : '<span class="text-muted">No contact</span>' ?>
                                            <span class="assoc-sep">•</span>
                                            <?= !empty($a['client_id']) ? '<a href="view_client.php?id='.(int)$a['client_id'].'" class="assoc-link">'.h($a['client_name'] ?? 'Company').'</a>' : '<span class="text-muted">No company</span>' ?>
                                            <span class="assoc-sep">•</span>
                                            <span class="badge rounded-pill bg-secondary text-white"><?= h($a['status']) ?></span>
                                        </div>
                                        <a href="edit_association.php?id=<?= (int)$a['association_id'] ?>" class="btn btn-sm btn-outline-primary">Edit Status</a>
                                    </div>
                                    <?php if (!empty($association_notes[$a['association_id']])): ?>
                                        <ul class="list-group list-group-flush mt-2 ms-3">
                                            <?php foreach ($association_notes[$a['association_id']] as $n): ?>
                                                <li class="list-group-item small text-muted"><?= nl2br(h($n['content'])) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: Scripts Panel -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div id="scripts-card" class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>Scripts</span>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="input-group input-group-sm" style="width:auto;">
                            <label class="input-group-text" for="scriptType">Type</label>
                            <select id="scriptType" class="form-select">
                                <option value="cold_call">Cold Call</option>
                                <option value="voicemail">Voicemail</option>
                            </select>
                        </div>

                        <div class="input-group input-group-sm" style="width:auto;">
                            <label class="input-group-text" for="jobSelect">Job</label>
                            <select id="jobSelect" class="form-select">
                                <option value="">(none)</option>
                                <?php foreach ($associations as $a): ?>
                                    <option value="<?= (int)$a['job_id'] ?>">
                                        <?= h($a['title']) ?><?= !empty($a['client_name']) ? ' — ' . h($a['client_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-group input-group-sm" style="width:auto;">
                            <label class="input-group-text" for="contactSelect">Contact</label>
                            <select id="contactSelect" class="form-select" disabled>
                                <option value="">(auto)</option>
                            </select>
                        </div>

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
                    </div>
                </div>
                <div class="card-body">
                    <div id="scriptError" class="alert alert-danger d-none mb-3"></div>

                    <div class="mb-2 small text-muted">
                        Candidate: <?= h($full_name) ?><?php if (!empty($candidate['current_job'])): ?> • Current: <?= h($candidate['current_job']) ?><?php endif; ?>
                    </div>

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

    <!-- Notes -->
    <div class="row g-4 mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">Notes</div>
                <div class="card-body">
                    <form method="post" class="mb-3">
                        <div class="mb-2">
                            <textarea name="content" class="form-control" rows="3" placeholder="Add a note..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Add Note</button>
                    </form>

                    <?php if (empty($candidate_notes)): ?>
                        <p class="text-muted">No notes yet.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($candidate_notes as $note): ?>
                                <li class="list-group-item"><?= nl2br(h($note['content'])) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
  // Build Job -> Contacts + Job -> Client maps for JS
  window.OTJobContacts = <?= json_encode($jobContactsMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  window.OTJobClient = <?= json_encode($jobClientMap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scriptTypeEl   = document.getElementById('scriptType');
    const jobSelectEl    = document.getElementById('jobSelect');
    const contactSelectEl= document.getElementById('contactSelect');
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

    const CANDIDATE_ID = '<?= (int)$candidate['id'] ?>';

    let lastToneUsed = null; // from server response, used for logging

    function populateContactsForJob(jobId) {
        contactSelectEl.innerHTML = '<option value="">(auto)</option>';
        const list = window.OTJobContacts[String(jobId)] || [];
        if (list.length > 0) {
            contactSelectEl.removeAttribute('disabled');
            for (const c of list) {
                const opt = document.createElement('option');
                opt.value = String(c.id);
                opt.textContent = c.name;
                contactSelectEl.appendChild(opt);
            }
        } else {
            contactSelectEl.setAttribute('disabled','disabled');
        }
    }

    async function renderScript() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const jobId = jobSelectEl && jobSelectEl.value ? jobSelectEl.value : '';
        const clientId = jobId ? (window.OTJobClient[String(jobId)] || '') : '';
        const selectedContact = contactSelectEl && contactSelectEl.value ? contactSelectEl.value : '';

        const params = new URLSearchParams({
            script_type: scriptTypeEl.value,
            contact_id: selectedContact,                 // explicit contact if chosen
            candidate_id: selectedContact ? '' : CANDIDATE_ID, // pass candidate when no contact
            client_id: clientId ? String(clientId) : '',
            job_id: jobId ? String(jobId) : '',
            tone: toneSelectEl.value || 'auto',
            include_smalltalk: toggleSmall.checked ? '1' : '0',
            include_micro_offer: toggleOffer.checked ? '1' : '0'
        });

        try {
            // IMPORTANT: ../ajax/* because ajax is sibling to public
            const res = await fetch('../ajax/render_script.php?' + params.toString(), { credentials: 'same-origin' });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                // Show raw response to help diagnose PHP errors/HTML output
                outputEl.value = '';
                errorBox.innerHTML = 'Server returned non-JSON (status ' + res.status + ').'
                  + '<br><br><strong>First 800 chars of response:</strong><br>'
                  + '<pre style="white-space:pre-wrap;max-height:240px;overflow:auto;margin:0;">'
                  + text.substring(0, 800).replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]))
                  + (text.length > 800 ? '…' : '')
                  + '</pre>';
                errorBox.classList.remove('d-none');
                console.warn('render_script raw response:', text);
                return;
            }

            if (!data.ok) {
                throw new Error(data.message || 'Failed to render script');
            }

            outputEl.value = data.text || '';
            lastToneUsed = data.tone_used || null;

            const toneMode = (toneSelectEl.value || 'auto');
            toneBadge.textContent = 'Tone: ' + (toneMode === 'auto' ? 'Auto' : (toneMode.charAt(0).toUpperCase() + toneMode.slice(1) + ' (override)'));
            toneUsedLabel.textContent = 'Tone used: ' + (data.tone_used || '—');
            templateName.textContent = 'Template: ' + (data.template_name || '—');
        } catch (e) {
            outputEl.value = '';
            errorBox.textContent = e.message;
            errorBox.classList.remove('d-none');
        }
    }

    async function logActivity(action) {
        try {
            const jobId = jobSelectEl && jobSelectEl.value ? jobSelectEl.value : '';
            const clientId = jobId ? (window.OTJobClient[String(jobId)] || '') : '';
            const selectedContact = contactSelectEl && contactSelectEl.value ? contactSelectEl.value : '';
            const flags = {
                smalltalk: !!toggleSmall.checked,
                micro_offer: !!toggleOffer.checked
            };
            const form = new URLSearchParams({
                action: action,
                script_type: scriptTypeEl.value,
                tone_used: lastToneUsed || (toneSelectEl.value || 'auto'),
                contact_id: selectedContact,
                candidate_id: selectedContact ? '' : CANDIDATE_ID, // log who we personalized to
                client_id: clientId ? String(clientId) : '',
                job_id: jobId ? String(jobId) : ''
            });
            form.append('flags_json', JSON.stringify(flags));
            // IMPORTANT: ../ajax/* because ajax is sibling to public
            await fetch('../ajax/log_script_activity.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            });
        } catch (e) {
            console.warn('log failed');
        }
    }

    function buildPrintUrl() {
        const jobId = jobSelectEl && jobSelectEl.value ? jobSelectEl.value : '';
        const clientId = jobId ? (window.OTJobClient[String(jobId)] || '') : '';
        const selectedContact = contactSelectEl && contactSelectEl.value ? contactSelectEl.value : '';
        const params = new URLSearchParams({
            script_type: scriptTypeEl.value,
            contact_id: selectedContact,
            candidate_id: selectedContact ? '' : CANDIDATE_ID, // pass candidate when no contact
            client_id: clientId ? String(clientId) : '',
            job_id: jobId ? String(jobId) : '',
            tone: toneSelectEl.value || 'auto',
            include_smalltalk: toggleSmall.checked ? '1' : '0',
            include_micro_offer: toggleOffer.checked ? '1' : '0',
            print: '1'
        });
        return 'print_script.php?' + params.toString();
    }

    // Event bindings
    if (scriptTypeEl) scriptTypeEl.addEventListener('change', renderScript);
    if (jobSelectEl)  jobSelectEl.addEventListener('change', () => { populateContactsForJob(jobSelectEl.value); renderScript(); });
    if (contactSelectEl) contactSelectEl.addEventListener('change', renderScript);
    if (toneSelectEl) toneSelectEl.addEventListener('change', renderScript);
    if (toggleSmall)  toggleSmall.addEventListener('change', renderScript);
    if (toggleOffer)  toggleOffer.addEventListener('change', renderScript);

    if (copyBtn) {
        copyBtn.addEventListener('click', async () => {
            try {
                // Modern clipboard with fallback
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(outputEl.value);
                } else {
                    outputEl.select();
                    document.execCommand('copy');
                }
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

    // Initial populate & render
    populateContactsForJob(jobSelectEl.value);
    renderScript();
});
</script>

<script>
// Expose merge data (left intact in case other components use it)
window.ComposeEmail = window.ComposeEmail || {};
window.ComposeEmail.userData = {
  user_name:  <?= json_encode($_SESSION['user']['name']  ?? '') ?>,
  user_email: <?= json_encode($_SESSION['user']['email'] ?? '') ?>,
  user_phone: <?= json_encode($_SESSION['user']['phone'] ?? '') ?>
};
window.ComposeEmail.recipientData = {
  first_name:   <?= json_encode($candidate['first_name'] ?? '') ?>,
  last_name:    <?= json_encode($candidate['last_name']  ?? '') ?>,
  full_name:    <?= json_encode(trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''))) ?>,
  company_name: "",
  job_title:    <?= json_encode($candidate['current_job'] ?? ($candidate['title'] ?? '')) ?>
};
window.ComposeEmail.mergeData = Object.assign(
  {},
  window.ComposeEmail.userData,
  window.ComposeEmail.recipientData,
  { today: <?= json_encode(date('F j, Y')) ?> }
);
</script>

<?php
// Removed modal include; Scripts panel is inline
// include __DIR__ . '/../includes/modal_scripts.php';

require_once __DIR__ . '/../includes/footer.php';
?>
