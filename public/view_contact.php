<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$contact_id = $_GET['id'] ?? null;

if (!$contact_id) {
    echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Load contact info
$stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->execute([$contact_id]);
$contact = $stmt->fetch();

if (!$contact) {
    echo "<div class='alert alert-warning'>Contact not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Load associated client info
$client = null;
if (!empty($contact['client_id'])) {
    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id = ?");
    $stmt->execute([$contact['client_id']]);
    $client = $stmt->fetch();
}

// Load notes
$stmt = $pdo->prepare("SELECT * FROM notes WHERE contact_id = ? ORDER BY created_at DESC");
$stmt->execute([$contact_id]);
$notes = $stmt->fetchAll();

// Load jobs (via client)
$jobs = [];
if (!empty($contact['client_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE client_id = ?");
    $stmt->execute([$contact['client_id']]);
    $jobs = $stmt->fetchAll();
}

// Load candidates (via jobs)
$candidates = [];
if (!empty($jobs)) {
    $job_ids = array_column($jobs, 'id');
    $placeholders = implode(',', array_fill(0, count($job_ids), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT c.id, c.name FROM candidates c JOIN applications a ON c.id = a.candidate_id WHERE a.job_id IN ($placeholders)");
    $stmt->execute($job_ids);
    $candidates = $stmt->fetchAll();
}

// Save a new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'], $_POST['candidate_id'], $_POST['job_id'], $_POST['client_id'])) {
    $note = trim($_POST['note']);
    $candidate_id = $_POST['candidate_id'] ?: null;
    $job_id = $_POST['job_id'] ?: null;
    $client_id = $_POST['client_id'] ?: null;

    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO notes (content, contact_id, candidate_id, job_id, client_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$note, $contact_id, $candidate_id, $job_id, $client_id]);
        header("Location: view_contact.php?id=$contact_id");
        exit;
    }
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= htmlspecialchars($contact['full_name']) ?></h2>
        <div>
            <a href="edit_contact.php?id=<?= $contact['id'] ?>" class="btn btn-sm btn-primary me-2">Edit Contact</a>
            <a href="delete_contact.php?id=<?= $contact['id'] ?>" 
               class="btn btn-sm btn-danger"
               onclick="return confirm('Are you sure you want to delete this contact?');">
               Delete Contact
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Position & Company</div>
                <div class="card-body">
                    <?php if (!empty($contact['job_title'])): ?>
                        <p><strong>Job Title:</strong> <?= htmlspecialchars($contact['job_title']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($client)): ?>
                        <p><strong>Company:</strong> 
                            <a href="view_client.php?id=<?= $client['id'] ?>">
                                <?= htmlspecialchars($client['name']) ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p><strong>Company:</strong> Not Assigned</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Contact Info</div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?= htmlspecialchars($contact['email']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($contact['phone']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <form method="POST" class="mb-3">
                <textarea name="note" class="form-control mb-2" rows="3" placeholder="Add a note..." required></textarea>
                <div class="row">
                    <div class="col">
                        <label>Candidate (optional)</label>
                        <select name="candidate_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($candidates as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>Job (optional)</label>
                        <select name="job_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($jobs as $j): ?>
                                <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label>Client (optional)</label>
                        <select name="client_id" class="form-select">
                            <option value="">—</option>
                            <?php if (!empty($client)): ?>
                                <option value="<?= $client['id'] ?>" selected><?= htmlspecialchars($client['name']) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Save Note</button>
            </form>

            <?php if (empty($notes)): ?>
                <p>No notes found.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notes as $note): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= $note['created_at'] ?></strong><br>
                                <?= nl2br(htmlspecialchars($note['content'])) ?>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <a href="edit_note.php?id=<?= $note['id'] ?>&contact_id=<?= $contact_id ?>" class="btn btn-outline-primary">Edit</a>
                                <a href="delete_note.php?id=<?= $note['id'] ?>&contact_id=<?= $contact_id ?>" class="btn btn-outline-danger">Delete</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>