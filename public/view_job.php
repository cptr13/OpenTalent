<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$job_id = $_GET['id'] ?? null;

if (!$job_id) {
    echo "<div class='alert alert-danger'>No valid job ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Add note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_content'], $_POST['note_candidate_id'])) {
    $note_content = trim($_POST['note_content']);
    $note_candidate_id = (int)$_POST['note_candidate_id'];
    $client_id = $_POST['client_id'] ?? null;
    $contact_id = $_POST['contact_id'] ?? null;

    if ($note_content !== '') {
        $stmt = $pdo->prepare("INSERT INTO notes (candidate_id, job_id, client_id, contact_id, content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$note_candidate_id, $job_id, $client_id, $contact_id, $note_content]);
    }
    header("Location: view_job.php?id=" . urlencode($job_id));
    exit;
}

// Assign candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['candidate_id']) && !isset($_POST['note_content'])) {
    $candidate_id = $_POST['candidate_id'];
    $status = $_POST['status'] ?? 'Screening: Associated to Job';
    $stmt = $pdo->prepare("INSERT INTO applications (candidate_id, job_id, status) VALUES (?, ?, ?)");
    $stmt->execute([$candidate_id, $job_id, $status]);
    header("Location: view_job.php?id=" . $job_id);
    exit;
}

// Get job
$stmt = $pdo->prepare("
    SELECT jobs.*, clients.id AS client_id, clients.name AS company_name 
    FROM jobs 
    LEFT JOIN clients ON jobs.client_id = clients.id 
    WHERE jobs.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    echo "<div class='alert alert-warning'>Job not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Assigned candidates
$stmt = $pdo->prepare("
    SELECT a.id AS application_id, c.id AS candidate_id, c.name, a.status 
    FROM applications a 
    JOIN candidates c ON a.candidate_id = c.id 
    WHERE a.job_id = ?
");
$stmt->execute([$job_id]);
$assigned = $stmt->fetchAll();

// Notes map
$noteMap = [];
foreach ($assigned as $a) {
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE candidate_id = ? ORDER BY created_at DESC");
    $stmt->execute([$a['candidate_id']]);
    $noteMap[$a['candidate_id']] = $stmt->fetchAll();
}

$candidates = $pdo->query("SELECT id, name FROM candidates ORDER BY name")->fetchAll();
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();
$contacts = $pdo->query("SELECT id, full_name FROM contacts ORDER BY full_name")->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= htmlspecialchars($job['title']) ?></h2>
        <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-primary">Edit Job</a>
    </div>

    <div class="row">
        <!-- Job Info -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Job Information</div>
                <div class="card-body">
                    <p><strong>Company:</strong>
    <?php if (!empty($job['client_id'])): ?>
        <a href="view_client.php?id=<?= $job['client_id'] ?>">
            <?= htmlspecialchars($job['company_name']) ?>
        </a>
    <?php else: ?>
        <?= htmlspecialchars($job['company_name'] ?? '—') ?>
    <?php endif; ?>
</p>

                    <p><strong>Location:</strong> <?= htmlspecialchars($job['location'] ?? '–') ?></p>
                    <p><strong>Type:</strong> <?= htmlspecialchars($job['employment_type'] ?? '–') ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($job['status'] ?? '–') ?></p>
                    <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($job['description'] ?? '')) ?></p>
                </div>
            </div>
        </div>

        <!-- Assigned Candidates & Notes -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">Assigned Candidates</div>
                <div class="card-body">
                    <?php if (empty($assigned)): ?>
                        <p>No candidates assigned.</p>
                    <?php else: ?>
                        <?php foreach ($assigned as $a): ?>
                            <div class="mb-4 border rounded p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="view_candidate.php?id=<?= $a['candidate_id'] ?>">
                                            <?= htmlspecialchars($a['name']) ?>
                                        </a>
                                        <span class="badge bg-info ms-2"><?= htmlspecialchars($a['status']) ?></span>
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addNoteModal<?= $a['candidate_id'] ?>">📝</button>
                                        <a href="delete_application.php?application_id=<?= $a['application_id'] ?>&job_id=<?= $job_id ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Remove this candidate from the role?');">🗑️</a>
                                    </div>
                                </div>

                                <?php if (!empty($noteMap[$a['candidate_id']])): ?>
                                    <h6 class="mt-3">Notes</h6>
                                    <ul class="list-group">
                                        <?php foreach ($noteMap[$a['candidate_id']] as $note): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?= $note['created_at'] ?></strong><br>
                                                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                                                </div>
                                                <div class="btn-group">
                                                    <a href="edit_note.php?id=<?= $note['id'] ?>&candidate_id=<?= $a['candidate_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    <a href="delete_note.php?id=<?= $note['id'] ?>&candidate_id=<?= $a['candidate_id'] ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <!-- Add Note Modal -->
                            <div class="modal fade" id="addNoteModal<?= $a['candidate_id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Add Note for <?= htmlspecialchars($a['name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="note_candidate_id" value="<?= $a['candidate_id'] ?>">
                                                <div class="mb-3">
                                                    <label>Note Content</label>
                                                    <textarea name="note_content" class="form-control" rows="3" required></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Link to Client (optional)</label>
                                                    <select name="client_id" class="form-select">
                                                        <option value="">— None —</option>
                                                        <?php foreach ($clients as $client): ?>
                                                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label>Link to Contact (optional)</label>
                                                    <select name="contact_id" class="form-select">
                                                        <option value="">— None —</option>
                                                        <?php foreach ($contacts as $contact): ?>
                                                            <option value="<?= $contact['id'] ?>"><?= htmlspecialchars($contact['full_name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary">Add Note</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assign Candidate -->
            <div class="card">
                <div class="card-header">Assign a Candidate</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Select Candidate:</label>
                            <select name="candidate_id" class="form-select" required>
                                <option value="">-- Choose --</option>
                                <?php foreach ($candidates as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Status:</label>
                            <select name="status" class="form-select">
                                <optgroup label="1 - Screening">
                                    <option>Screening: Associated to Job</option>
                                    <option>Screening: Attempted to Contact</option>
                                    <option>Screening: Sent Text Message</option>
                                    <option>Screening: Received Text Message</option>
                                    <option>Screening: Left Voicemail</option>
                                    <option>Screening: Sent Email</option>
                                    <option>Screening: Received Email</option>
                                    <option>Screening: Conversation/Screening</option>
                                    <option>Screening: Not interested-Screening Call</option>
                                    <option>Screening: Qualified-Screening Call</option>
                                    <option>Screening: Unqualified-Screening Call</option>
                                    <option>Screening: Submitted to client</option>
                                </optgroup>
                                <optgroup label="2 - Interview">
                                    <option>Interview: Client Interview to be scheduled</option>
                                    <option>Interview: Client Interview Scheduled</option>
                                    <option>Interview: Client Interview In Progress</option>
                                    <option>Interview: Client Second Interview to be scheduled</option>
                                    <option>Interview: Client Second Interview Scheduled</option>
                                </optgroup>
                                <optgroup label="3 - Offered">
                                    <option>Offered: Closing Call/Verbal Offer</option>
                                    <option>Offered: Offer To Be Made</option>
                                    <option>Offered: Offer Made</option>
                                    <option>Offered: Offer Accepted</option>
                                    <option>Offered: Offer Declined</option>
                                </optgroup>
                                <optgroup label="4 - Hired"><option>Hired</option></optgroup>
                                <optgroup label="5 - Status Change">
                                    <option>Status Change: On Hold</option>
                                    <option>Status Change: Position Closed</option>
                                </optgroup>
                                <optgroup label="6 - Rejected">
                                    <option>Rejected: Rejected but Hirable</option>
                                    <option>Rejected: Rejected After Interview</option>
                                    <option>Rejected: Rejected by client</option>
                                    <option>Rejected: Contact in Future</option>
                                    <option>Rejected: Offer Withdrawn</option>
                                </optgroup>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success">Assign</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>