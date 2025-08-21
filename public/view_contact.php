<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php'; // loader for status lists

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
        <!-- Contact Info -->
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
                <div class="card-header">Outreach Status</div>
                <div class="card-body">
                    <form method="POST" action="update_outreach_stage.php" class="mb-2">
                        <input type="hidden" name="id" value="<?= (int)$contact['id'] ?>">
                        <label><strong>Stage:</strong></label>
                        <select name="outreach_stage" class="form-select form-select-sm mt-1 mb-2" onchange="confirmStageChange(this.form)">
                            <?php
                            $touchLabels = [
                                1 => 'Touch 1 – Email #1',
                                2 => 'Touch 2 – LinkedIn #1',
                                3 => 'Touch 3 – Email #2',
                                4 => 'Touch 4 – LinkedIn #2',
                                5 => 'Touch 5 – Email #3',
                                6 => 'Touch 6 – LinkedIn #3',
                                7 => 'Touch 7 – Email #4',
                                8 => 'Touch 8 – LinkedIn #4',
                                9 => 'Touch 9 – Email #5',
                                10 => 'Touch 10 – LinkedIn #5',
                                11 => 'Touch 11 – Call / Voicemail',
                                12 => 'Touch 12 – Breakup Email',
                            ];
                            foreach ($touchLabels as $i => $label): ?>
                                <option value="<?= $i ?>" <?= ($contact['outreach_stage'] ?? 1) == $i ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
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

<script>
function confirmStageChange(form) {
    if (confirm("Are you sure you want to update the outreach stage and schedule the next follow-up?")) {
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
