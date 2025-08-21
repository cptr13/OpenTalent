<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/status_badge.php'; // for contact_status_badge()

$job_id = $_GET['id'] ?? null;
$error = '';
$flash_message = $_GET['msg'] ?? null;

if (!$job_id) {
    echo "<div class='alert alert-danger'>No valid job ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle new note submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    $note = trim($_POST['note']);
    if (!empty($note)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (content, module_type, module_id, created_at) VALUES (?, 'job', ?, NOW())");
            $stmt->execute([$note, (int)$job_id]);
            header("Location: view_job.php?id=$job_id&msg=Note+added+successfully");
            exit;
        } catch (PDOException $e) {
            $error = "Error saving note: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Note cannot be empty.";
    }
}

// Get job (with alias for job type)
$stmt = $pdo->prepare("
    SELECT jobs.*, jobs.type AS job_type, clients.id AS client_id, clients.name AS company_name 
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
    SELECT a.id AS association_id, c.id AS candidate_id, CONCAT(c.first_name, ' ', c.last_name) AS name, a.status 
    FROM associations a 
    JOIN candidates c ON a.candidate_id = c.id 
    WHERE a.job_id = ?
");
$stmt->execute([$job_id]);
$assigned = $stmt->fetchAll();

// Assigned contacts (include contact_status)
$stmt = $pdo->prepare("
    SELECT contacts.id,
           CONCAT(contacts.first_name, ' ', contacts.last_name) AS name,
           contacts.title,
           contacts.email,
           contacts.phone,
           contacts.contact_status
    FROM job_contacts
    JOIN contacts ON job_contacts.contact_id = contacts.id
    WHERE job_contacts.job_id = ?
");
$stmt->execute([$job_id]);
$contacts = $stmt->fetchAll();

// Load job + association notes
$stmt = $pdo->prepare("
    SELECT n.*, 'job' AS source, NULL AS candidate_name
    FROM notes n
    WHERE n.module_type = 'job' AND n.module_id = ?
    UNION ALL
    SELECT n.*, 'association' AS source, CONCAT(c.first_name, ' ', c.last_name) AS candidate_name
    FROM notes n
    JOIN associations a ON n.module_type = 'association' AND n.module_id = a.id
    JOIN candidates c ON a.candidate_id = c.id
    WHERE a.job_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$job_id, $job_id]);
$notes = $stmt->fetchAll();

// Status options
$status_options = [
    'Screening: Associated to Job',
    'Screening: Contacted',
    'Screening: Interview Scheduled',
    'Screening: Rejected',
    'Interview: Scheduled',
    'Interview: Completed',
    'Interview: Client Rejected',
    'Interview: Candidate Rejected',
    'Offer: Extended',
    'Offer: Accepted',
    'Offer: Declined',
    'Hired',
    'Status Change: Withdrawn by Candidate',
    'Status Change: Put on Hold',
    'Rejected: By Recruiter',
    'Rejected: By Client',
    'Rejected: By Candidate',
    'Candidate Action: Ghosted',
    'Candidate Action: Backed Out',
    'Candidate Action: Unresponsive'
];
?>

<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($job['title'] ?? '') ?></h2>
        <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-primary">Edit Job</a>
    </div>

    <!-- Top Summary Card -->
    <div class="card mb-4">
        <div class="card-header">Summary</div>
        <div class="card-body">
            <p><strong>Company:</strong>
                <?php if (!empty($job['client_id'])): ?>
                    <a href="view_client.php?id=<?= $job['client_id'] ?>">
                        <?= htmlspecialchars($job['company_name'] ?? 'Unknown') ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">Not Assigned</span>
                <?php endif; ?>
            </p>
            <p><strong>Location:</strong> <?= htmlspecialchars($job['location'] ?? '–') ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($job['job_type'] ?? '–') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($job['status'] ?? '–') ?></p>
        </div>
    </div>

    <!-- Contacts Card -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Associated Contacts</span>
            <a href="associate.php?job_id=<?= $job['id'] ?>&return=view_job.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-success">+ Associate Contact</a>
        </div>
        <ul class="list-group list-group-flush">
            <?php if (empty($contacts)): ?>
                <li class="list-group-item text-muted">No contacts assigned to this job.</li>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <a href="view_contact.php?id=<?= $contact['id'] ?>">
                                <?= htmlspecialchars($contact['name'] ?? 'Unnamed Contact') ?>
                            </a>
                            <?php if (!empty($contact['title'])): ?>
                                – <?= htmlspecialchars($contact['title']) ?>
                            <?php endif; ?>
                            <br>
                            <small><?= htmlspecialchars($contact['email'] ?? '') ?></small>
                        </div>
                        <div class="text-nowrap">
                            <?= contact_status_badge($contact['contact_status'] ?? null, 'sm') ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Job Description -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Job Description</span>
            <a href="view_job_description.php?id=<?= $job['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Open Full View</a>
        </div>
        <div class="card-body" style="max-height: 250px; overflow-y: auto;">
            <p><?= nl2br(htmlspecialchars($job['description'] ?? 'No description provided.')) ?></p>
        </div>
    </div>

    <!-- Assigned Candidates -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Associated Candidates</span>
            <a href="associate.php?job_id=<?= $job['id'] ?>" class="btn btn-sm btn-success">+ Associate Candidate</a>
        </div>
        <div class="card-body">
            <?php if (empty($assigned)): ?>
                <p class="text-muted">No candidates associated with this job.</p>
            <?php else: ?>
                <?php foreach ($assigned as $a): ?>
                    <div class="mb-3 border rounded p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <a href="view_candidate.php?id=<?= $a['candidate_id'] ?>"><?= htmlspecialchars($a['name'] ?? '') ?></a>
                                <form method="POST" action="update_association_status.php" class="d-inline-flex gap-2 align-items-center ms-3">
                                    <input type="hidden" name="association_id" value="<?= $a['association_id'] ?>">
                                    <input type="hidden" name="candidate_id" value="<?= $a['candidate_id'] ?>">
                                    <input type="hidden" name="job_id" value="<?= $job_id ?>">

                                    <select name="new_status" class="form-select form-select-sm">
                                        <?php foreach ($status_options as $option): ?>
                                            <option value="<?= $option ?>" <?= ($a['status'] === $option ? 'selected' : '') ?>><?= $option ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                                </form>
                            </div>
                            <a href="delete_association.php?association_id=<?= $a['association_id'] ?>&job_id=<?= $job_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this candidate from the role?');">🗑️</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notes -->
    <div class="card mb-4">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <form method="POST" class="mb-3">
                <textarea name="note" class="form-control mb-2" rows="3" placeholder="Add a note..." required></textarea>
                <button type="submit" class="btn btn-sm btn-success">Save Note</button>
            </form>

            <?php if (empty($notes)): ?>
                <p class="text-muted mb-0">No notes yet.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notes as $note): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small class="text-muted">
                                        <?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?>
                                        <?php if ($note['source'] === 'association'): ?>
                                            — From association (<?= htmlspecialchars($note['candidate_name'] ?? '') ?>)
                                        <?php endif; ?>
                                    </small><br>
                                    <?= nl2br(htmlspecialchars($note['content'] ?? '')) ?>
                                </div>
                                <div class="d-flex align-items-start gap-2 mt-2">
                                    <a href="edit_note.php?id=<?= $note['id'] ?>&job_id=<?= $job_id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="delete_note.php?id=<?= $note['id'] ?>&return=job&id_return=<?= $job_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this note?');">Delete</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
