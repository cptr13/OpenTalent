<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$contact_id = $_GET['id'] ?? null;

if (!$contact_id) {
    echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch the contact
$stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->execute([$contact_id]);
$contact = $stmt->fetch();

if (!$contact) {
    echo "<div class='alert alert-warning'>Contact not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch all clients for dropdown
$clientStmt = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC");
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <h2 class="mb-4">Edit Contact</h2>

    <form method="POST" action="update_contact.php">
        <input type="hidden" name="id" value="<?= $contact['id'] ?>">

        <div class="mb-3">
            <label for="client_id" class="form-label">Associated Client</label>
            <select name="client_id" id="client_id" class="form-select" required>
                <option value="">Select a client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= $client['id'] ?>" <?= ($contact['client_id'] == $client['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">Full Name</label>
            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($contact['full_name']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title / Position</label>
            <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($contact['job_title']) ?>">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($contact['email']) ?>">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($contact['phone']) ?>">
        </div>

        <button type="submit" class="btn btn-success">Update Contact</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
