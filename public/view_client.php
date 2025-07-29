<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
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

$error = '';

// Save note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note'])) {
    $note = trim($_POST['note']);
    if (!empty($note)) {
        $stmt = $pdo->prepare("INSERT INTO notes (content, module_type, module_id, created_at) VALUES (?, 'client', ?, NOW())");
        $stmt->execute([$note, (int)$client_id]);
        header("Location: view_client.php?id=$client_id&msg=Note+added+successfully");
        exit;
    } else {
        $error = "Note cannot be empty.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE module_type = 'client' AND module_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$notes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT n.*, c.name AS candidate_name, j.title AS job_title FROM notes n JOIN associations a ON n.module_type = 'association' AND n.module_id = a.id JOIN jobs j ON a.job_id = j.id JOIN candidates c ON a.candidate_id = c.id WHERE j.client_id = ? ORDER BY n.created_at DESC");
$stmt->execute([$client_id]);
$association_notes = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE client_id = ?");
$stmt->execute([$client_id]);
$jobs = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM contacts WHERE client_id = ?");
$stmt->execute([$client_id]);
$contacts = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT DISTINCT c.* FROM candidates c JOIN associations a ON a.candidate_id = c.id JOIN jobs j ON j.id = a.job_id WHERE j.client_id = ?");
$stmt->execute([$client_id]);
$candidates = $stmt->fetchAll();

$primary_contact = null;
if (!empty($client['primary_contact_id'])) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, job_title, email FROM contacts WHERE id = ?");
    $stmt->execute([$client['primary_contact_id']]);
    $primary_contact = $stmt->fetch();
}

$flash_message = $_GET['msg'] ?? null;
?>

<div class="container my-4">
    <?php if ($flash_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($client['name']) ?></h2>
        <div>
            <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-primary me-2">Edit Client</a>
            <a href="delete_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete Client</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 d-flex flex-column">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Company Info</span>
                    <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body">
                    <p><strong>Industry:</strong> <?= htmlspecialchars($client['industry']) ?></p>
                    <p><strong>Website:</strong> <?= htmlspecialchars($client['url']) ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($client['location']) ?></p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">Account Details</div>
                <div class="card-body">
                    <form method="POST" action="update_client_status.php" class="row g-2 align-items-center mb-3">
                        <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                        <div class="col-auto">
                            <label class="col-form-label"><strong>Status:</strong></label>
                        </div>
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm">
                                <option value="Lead" <?= $client['status'] === 'Lead' ? 'selected' : '' ?>>Lead</option>
                                <option value="Prospect" <?= $client['status'] === 'Prospect' ? 'selected' : '' ?>>Prospect</option>
                                <option value="Customer" <?= $client['status'] === 'Customer' ? 'selected' : '' ?>>Customer</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                        </div>
                    </form>
                    <p><strong>Account Manager:</strong> <?= htmlspecialchars($client['account_manager']) ?></p>
                    <p><strong>Created At:</strong> <?= htmlspecialchars($client['created_at']) ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-6 d-flex flex-column">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>About</span>
                    <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                </div>
                <div class="card-body overflow-auto" style="max-height: 340px;">
                    <p><?= nl2br(htmlspecialchars($client['about'])) ?></p>
                </div>
            </div>

            <?php if (!empty($primary_contact)): ?>
                <div class="card mt-4">
                    <div class="card-header">Primary Contact</div>
                    <div class="card-body">
                        <h5>
                            <a href="view_contact.php?id=<?= $primary_contact['id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($primary_contact['first_name'] . ' ' . $primary_contact['last_name']) ?>
                            </a>
                        </h5>
                        <?php if (!empty($primary_contact['job_title'])): ?>
                            <p><strong>Title:</strong> <?= htmlspecialchars($primary_contact['job_title']) ?></p>
                        <?php endif; ?>
                        <p><strong>Email:</strong> <?= htmlspecialchars($primary_contact['email']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $sections = [
        'Associated Contacts' => ['items' => $contacts, 'url' => "add_contact.php?client_id={$client['id']}", 'label' => '+ Associate Contact', 'view' => 'contact'],
        'Associated Job Orders' => ['items' => $jobs, 'url' => "add_job.php?client_id={$client['id']}&client_name=" . urlencode($client['name']), 'label' => '+ Create Job Order', 'view' => 'job'],
        'Associated Candidates' => ['items' => $candidates, 'url' => "assign.php?client_id={$client['id']}", 'label' => '+ Associate Candidate', 'view' => 'candidate'],
    ];
    foreach ($sections as $title => $data):
    ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= $title ?></span>
                <a href="<?= $data['url'] ?>" class="btn btn-sm btn-outline-secondary"><?= $data['label'] ?></a>
            </div>
            <div class="card-body">
                <?php if (empty($data['items'])): ?>
                    <p>No <?= strtolower($title) ?> found for this client.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($data['items'] as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="view_<?= $data['view'] ?>.php?id=<?= $item['id'] ?>">
                                        <strong><?= htmlspecialchars(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? $item['name'] ?? $item['title'])) ?></strong>
                                    </a>
                                    <?php if (!empty($item['job_title'])): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($item['job_title']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($item['status'])): ?>
                                    <span class="badge bg-secondary">...</span>
                                <?php elseif (!empty($item['email'])): ?>
                                    <span><?= htmlspecialchars($item['email']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card mb-5">
        <div class="card-header">Notes</div>
        <div class="card-body">
            <form method="POST" class="mb-3">
                <textarea name="note" class="form-control" rows="3" placeholder="Add a note..." required></textarea>
                <button type="submit" class="btn btn-sm btn-success mt-2">Save Note</button>
            </form>

            <?php if (empty($notes) && empty($association_notes)): ?>
                <p class="text-muted">No notes found.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notes as $note): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?> (Client)</small><br>
                                    <?= nl2br(htmlspecialchars($note['content'])) ?>
                                </div>
                                <div class="d-flex align-items-start gap-2 mt-2">
                                    <a href="edit_note.php?id=<?= $note['id'] ?>&client_id=<?= $client_id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="delete_note.php?id=<?= $note['id'] ?>&return=client&id_return=<?= $client_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach ($association_notes as $note): ?>
                        <li class="list-group-item">
                            <div>
                                <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?> (Association: <?= htmlspecialchars($note['candidate_name']) ?> → <?= htmlspecialchars($note['job_title']) ?>)</small><br>
                                <?= nl2br(htmlspecialchars($note['content'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
