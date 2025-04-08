<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, cl.name AS client_name, cl.id AS client_id
    FROM contacts c
    LEFT JOIN clients cl ON c.client_id = cl.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contact = $stmt->fetch();

if (!$contact) {
    echo "<div class='alert alert-danger'>Contact not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>

<h2 class="mb-4">Contact: <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?></h2>

<div class="card mb-4">
    <div class="card-body">
        <?php if ($contact['client_id']): ?>
            <p><strong>Client:</strong> <a href="view_client.php?id=<?= $contact['client_id'] ?>"><?= htmlspecialchars($contact['client_name']) ?></a></p>
        <?php endif; ?>
        <p><strong>Job Title:</strong> <?= htmlspecialchars($contact['job_title']) ?></p>
        <p><strong>Department:</strong> <?= htmlspecialchars($contact['department']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($contact['email']) ?></p>
        <p><strong>Secondary Email:</strong> <?= htmlspecialchars($contact['secondary_email']) ?></p>
        <p><strong>Phone (Work):</strong> <?= htmlspecialchars($contact['phone_work']) ?></p>
        <p><strong>Phone (Mobile):</strong> <?= htmlspecialchars($contact['phone_mobile']) ?></p>
        <p><strong>Fax:</strong> <?= htmlspecialchars($contact['fax']) ?></p>
        <p><strong>Skype ID:</strong> <?= htmlspecialchars($contact['skype_id']) ?></p>
        <p><strong>Twitter:</strong> <?= htmlspecialchars($contact['twitter']) ?></p>
        <p><strong>LinkedIn:</strong> <a href="<?= htmlspecialchars($contact['linkedin']) ?>" target="_blank"><?= htmlspecialchars($contact['linkedin']) ?></a></p>
        <p><strong>Contact Owner:</strong> <?= htmlspecialchars($contact['contact_owner']) ?></p>
        <p><strong>Source:</strong> <?= htmlspecialchars($contact['source']) ?></p>
        <p><strong>Primary Contact:</strong> <?= $contact['is_primary_contact'] ? 'Yes' : 'No' ?></p>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($contact['description'])) ?></p>
    </div>
</div>

<a href="contacts.php" class="btn btn-secondary">← Back to Contacts</a>
<a href="edit_contact.php?id=<?= $contact['id'] ?>" class="btn btn-warning">Edit Contact</a>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
