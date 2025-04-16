<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$client_id = $_GET['id'] ?? null;

if (!$client_id) {
    echo "<div class='alert alert-danger'>No valid client ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    echo "<div class='alert alert-warning'>Client not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Save a new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    $note = trim($_POST['note']);

    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO notes (content, client_id) VALUES (?, ?)");
        $stmt->execute([$note, $client_id]);
        header("Location: view_client.php?id=$client_id");
        exit;
    }
}

// Load notes
$stmt = $pdo->prepare("SELECT * FROM notes WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$notes = $stmt->fetchAll();

// Load jobs
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE client_id = ?");
$stmt->execute([$client_id]);
$jobs = $stmt->fetchAll();

// Load contacts
$stmt = $pdo->prepare("SELECT * FROM contacts WHERE client_id = ?");
$stmt->execute([$client_id]);
$contacts = $stmt->fetchAll();

// Load candidates
$stmt = $pdo->prepare("
    SELECT c.* FROM candidates c
    JOIN applications a ON c.id = a.candidate_id
    JOIN jobs j ON a.job_id = j.id
    WHERE j.client_id = ?
    GROUP BY c.id
");
$stmt->execute([$client_id]);
$candidates = $stmt->fetchAll();

// Load primary contact
$primary_contact = null;
if (!empty($client['primary_contact_id'])) {
    $stmt = $pdo->prepare("SELECT id, full_name, job_title, email FROM contacts WHERE id = ?");
    $stmt->execute([$client['primary_contact_id']]);
    $primary_contact = $stmt->fetch();
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= htmlspecialchars($client['name']) ?></h2>
        <div>
            <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-primary">Edit Client</a>
            <a href="delete_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this client?');">Delete Client</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Company Info</div>
                <div class="card-body">
                    <p><strong>Industry:</strong> <?= htmlspecialchars($client['industry']) ?></p>
                    <p><strong>Website:</strong> <?= htmlspecialchars($client['website']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($client['location']) ?></p>
                    <p><strong>Size:</strong> <?= htmlspecialchars($client['company_size']) ?></p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Account Details</div>
                <div class="card-body">
                    <form method="POST" action="update_client_status.php" class="d-flex align-items-center mb-2">
                        <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                        <label for="status" class="me-2"><strong>Status:</strong></label>
                        <select name="status" class="form-select form-select-sm w-auto me-3">
                            <option value="Lead" <?= $client['status'] === 'Lead' ? 'selected' : '' ?>>Lead</option>
                            <option value="Prospect" <?= $client['status'] === 'Prospect' ? 'selected' : '' ?>>Prospect</option>
                            <option value="Customer" <?= $client['status'] === 'Customer' ? 'selected' : '' ?>>Customer</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                    </form>
                    <p><strong>Account Manager:</strong> <?= htmlspecialchars($client['account_manager']) ?></p>
                    <p><strong>Created At:</strong> <?= htmlspecialchars($client['created_at']) ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">About</div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($client['about'])) ?></p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Notes</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <textarea name="note" class="form-control mb-2" rows="3" placeholder="Add a note..." required></textarea>
                        <button type="submit" class="btn btn-primary mt-1">Save Note</button>
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
                                        <a href="edit_note.php?id=<?= $note['id'] ?>&client_id=<?= $client_id ?>" class="btn btn-outline-primary">Edit</a>
                                        <a href="delete_note.php?id=<?= $note['id'] ?>&client_id=<?= $client_id ?>" class="btn btn-outline-danger">Delete</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($primary_contact)): ?>
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">Primary Contact</div>
            <div class="card-body">
                <h5><a href="view_contact.php?id=<?= $primary_contact['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($primary_contact['full_name']) ?></a></h5>
                <?php if (!empty($primary_contact['job_title'])): ?>
                    <p><strong>Title:</strong> <?= htmlspecialchars($primary_contact['job_title']) ?></p>
                <?php endif; ?>
                <p><strong>Email:</strong> <?= htmlspecialchars($primary_contact['email']) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Associated Contacts</span>
            <a href="add_contact.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-secondary">+ Add Contact</a>
        </div>
        <div class="card-body">
            <?php if (empty($contacts)): ?>
                <p>No contacts found for this client.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($contacts as $contact): ?>
                        <li class="list-group-item">
                            <a href="view_contact.php?id=<?= $contact['id'] ?>">
                                <?= htmlspecialchars($contact['full_name']) ?>
                                <?= !empty($contact['job_title']) ? ' – ' . htmlspecialchars($contact['job_title']) : '' ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Associated Job Orders</span>
            <a href="add_job.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-success">+ Create Job Order</a>
        </div>
        <div class="card-body">
            <?php if (empty($jobs)): ?>
                <p>No job orders found for this client.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($jobs as $job): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="view_job.php?id=<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></a>
                            <span class="badge bg-secondary"><?= htmlspecialchars($job['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Associated Candidates</span>
            <a href="assign_candidate.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-info">+ Associate Candidate</a>
        </div>
        <div class="card-body">
            <?php if (empty($candidates)): ?>
                <p>No candidates found for this client’s job orders.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($candidates as $candidate): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="view_candidate.php?id=<?= $candidate['id'] ?>">
                                <?= htmlspecialchars($candidate['name']) ?>
                            </a>
                            <span><?= htmlspecialchars($candidate['email']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
