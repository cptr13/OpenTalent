<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>No valid client ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    echo "<div class='alert alert-danger'>Client not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Get jobs for this client
$job_stmt = $pdo->prepare("SELECT * FROM jobs WHERE client_id = ? ORDER BY created_at DESC");
$job_stmt->execute([$id]);
$jobs = $job_stmt->fetchAll();

// Get contacts for this client
$contact_stmt = $pdo->prepare("SELECT * FROM contacts WHERE client_id = ? ORDER BY last_name ASC");
$contact_stmt->execute([$id]);
$contacts = $contact_stmt->fetchAll();
?>

<h2 class="mb-4">Client: <?= htmlspecialchars($client['name']) ?></h2>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Contact Info</h5>
        <p><strong>Account Manager:</strong> <?= htmlspecialchars($client['account_manager']) ?></p>
        <p><strong>Contact Number:</strong> <?= htmlspecialchars($client['contact_number']) ?></p>
        <p><strong>Fax:</strong> <?= htmlspecialchars($client['fax']) ?></p>
        <p><strong>Website:</strong> <a href="<?= htmlspecialchars($client['website']) ?>" target="_blank"><?= htmlspecialchars($client['website']) ?></a></p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">About</h5>
        <p><?= nl2br(htmlspecialchars($client['about'])) ?></p>
    </div>
</div>

<h4 class="mt-4 mb-3">Job Orders</h4>
<a href="add_job.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-success mb-2">+ Add Job for <?= htmlspecialchars($client['name']) ?></a>

<?php if (count($jobs) === 0): ?>
    <p class="text-muted">No job orders found for this client.</p>
<?php else: ?>
    <table class="table table-sm table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Job Title</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><a href="view_job.php?id=<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></a></td>
                    <td><?= htmlspecialchars($job['status']) ?></td>
                    <td><?= date('Y-m-d', strtotime($job['created_at'])) ?></td>
                    <td>
                        <a href="edit_job.php?id=<?= $job['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h4 class="mt-5 mb-3">Contacts</h4>
<a href="add_contact.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-success mb-2">+ Add Contact</a>

<?php if (count($contacts) === 0): ?>
    <p class="text-muted">No contacts found for this client.</p>
<?php else: ?>
    <table class="table table-sm table-bordered">
        <thead class="thead-light">
            <tr>
                <th>Name</th>
                <th>Job Title</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contacts as $contact): ?>
                <tr>
                    <td>
                        <a href="view_contact.php?id=<?= $contact['id'] ?>">
                            <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                        </a>
                        <?php if ($contact['is_primary_contact']): ?>
                            <span class="badge badge-info">Primary</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($contact['job_title']) ?></td>
                    <td><?= htmlspecialchars($contact['email']) ?></td>
                    <td><?= htmlspecialchars($contact['phone_work']) ?></td>
                    <td>
                        <a href="edit_contact.php?id=<?= $contact['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<a href="clients.php" class="btn btn-secondary mt-4">← Back to Client List</a>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
