<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php'; // for getStatusList('contact')

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

// Build contact status list (grouped by category)
$contactStatusList = getStatusList('contact'); // ['Category' => ['Sub1', ...], ...]
$currentContactStatus = $contact['contact_status'] ?? '';
?>

<div class="container mt-5">
    <h2 class="mb-4">Edit Contact</h2>

    <form method="POST" action="update_contact.php">
        <input type="hidden" name="id" value="<?= htmlspecialchars($contact['id']) ?>">

        <div class="mb-3">
            <label for="client_id" class="form-label">Associated Client</label>
            <select name="client_id" id="client_id" class="form-select" required>
                <option value="">Select a client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars($client['id']) ?>" <?= ($contact['client_id'] == $client['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" id="first_name" class="form-control" value="<?= htmlspecialchars($contact['first_name']) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" id="last_name" class="form-control" value="<?= htmlspecialchars($contact['last_name']) ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title / Position</label>
            <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($contact['title']) ?>">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($contact['email']) ?>">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($contact['phone']) ?>">
        </div>

        <!-- Contact Status (entity-specific) -->
        <div class="mb-3">
            <label for="contact_status" class="form-label">Contact Status</label>
            <select name="contact_status" id="contact_status" class="form-select" required>
                <option value="">-- Select Status --</option>
                <?php foreach ($contactStatusList as $category => $subs): ?>
                    <optgroup label="<?= htmlspecialchars($category) ?>">
                        <?php foreach ($subs as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>" <?= ($currentContactStatus === $sub ? 'selected' : '') ?>>
                                <?= htmlspecialchars($sub) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Tracks the relationship state (separate from the outreach cadence).</div>
        </div>

        <div class="mb-3">
            <label for="follow_up_date" class="form-label">Follow-Up Date</label>
            <input type="date" name="follow_up_date" id="follow_up_date" class="form-control" value="<?= htmlspecialchars($contact['follow_up_date'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="follow_up_notes" class="form-label">Follow-Up Notes</label>
            <textarea name="follow_up_notes" id="follow_up_notes" class="form-control" rows="4"><?= htmlspecialchars($contact['follow_up_notes'] ?? '') ?></textarea>
        </div>

        <div class="mb-3">
            <label for="outreach_stage" class="form-label">Outreach Stage</label>
            <input type="number" name="outreach_stage" id="outreach_stage" class="form-control" min="1" max="12" value="<?= htmlspecialchars($contact['outreach_stage'] ?? 1) ?>">
        </div>

        <div class="mb-3">
            <label for="last_touch_date" class="form-label">Last Touch Date</label>
            <input type="date" name="last_touch_date" id="last_touch_date" class="form-control" value="<?= htmlspecialchars($contact['last_touch_date'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label for="outreach_status" class="form-label">Outreach Status</label>
            <select name="outreach_status" id="outreach_status" class="form-select">
                <option value="Active" <?= ($contact['outreach_status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Paused" <?= ($contact['outreach_status'] === 'Paused') ? 'selected' : '' ?>>Paused</option>
                <option value="Do Not Contact" <?= ($contact['outreach_status'] === 'Do Not Contact') ? 'selected' : '' ?>>Do Not Contact</option>
                <option value="Completed" <?= ($contact['outreach_status'] === 'Completed') ? 'selected' : '' ?>>Completed</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Update Contact</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
