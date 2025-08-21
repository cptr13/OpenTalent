<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/status.php'; // for getStatusList('contact')

// Build contact status list (grouped by category)
$contactStatusList = getStatusList('contact'); // ['Category' => ['Sub1', ...], ...]
$defaultContactStatus = 'New Contact';

$prefill_client_name = '';
$prefill_client_id = $_GET['client_id'] ?? '';

if ($prefill_client_id) {
    $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
    $stmt->execute([$prefill_client_id]);
    $client = $stmt->fetch();
    if ($client) {
        $prefill_client_name = $client['name'];
    } else {
        $prefill_client_id = '';
    }
}
?>

<div class="container mt-5">
    <h2 class="mb-4">Add New Contact</h2>

    <form method="POST" action="save_contact.php">
        <div class="mb-3">
            <label for="client_search" class="form-label">Associated Client</label>
            <input type="text" id="client_search" class="form-control" placeholder="Start typing client name..." autocomplete="off" required value="<?= htmlspecialchars($prefill_client_name) ?>">
            <input type="hidden" name="client_id" id="client_id" value="<?= htmlspecialchars($prefill_client_id) ?>">
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" id="first_name" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" id="last_name" class="form-control" required>
            </div>
        </div>

        <div class="mb-3">
            <label for="title" class="form-label">Title / Position</label>
            <input type="text" name="title" id="title" class="form-control">
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="text" name="phone" id="phone" class="form-control">
        </div>

        <!-- Contact Status (entity-specific) -->
        <div class="mb-3">
            <label for="contact_status" class="form-label">Contact Status</label>
            <select name="contact_status" id="contact_status" class="form-select" required>
                <option value="">-- Select Status --</option>
                <?php foreach ($contactStatusList as $category => $subs): ?>
                    <optgroup label="<?= htmlspecialchars($category) ?>">
                        <?php foreach ($subs as $sub): ?>
                            <option value="<?= htmlspecialchars($sub) ?>" <?= ($sub === $defaultContactStatus ? 'selected' : '') ?>>
                                <?= htmlspecialchars($sub) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="form-text">This is separate from outreach cadence; it reflects the overall relationship state.</div>
        </div>

        <div class="mb-3">
            <label for="follow_up_date" class="form-label">Follow-Up Date</label>
            <input type="date" name="follow_up_date" id="follow_up_date" class="form-control">
        </div>

        <div class="mb-3">
            <label for="follow_up_notes" class="form-label">Follow-Up Notes</label>
            <textarea name="follow_up_notes" id="follow_up_notes" class="form-control" rows="4"></textarea>
        </div>

        <div class="mb-3">
            <label for="outreach_stage" class="form-label">Outreach Stage</label>
            <input type="number" name="outreach_stage" id="outreach_stage" class="form-control" min="1" max="12" value="1">
        </div>

        <div class="mb-3">
            <label for="last_touch_date" class="form-label">Last Touch Date</label>
            <input type="date" name="last_touch_date" id="last_touch_date" class="form-control">
        </div>

        <div class="mb-3">
            <label for="outreach_status" class="form-label">Outreach Status</label>
            <select name="outreach_status" id="outreach_status" class="form-select">
                <option value="Active" selected>Active</option>
                <option value="Paused">Paused</option>
                <option value="Do Not Contact">Do Not Contact</option>
                <option value="Completed">Completed</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Save Contact</button>
    </form>
</div>

<!-- jQuery & jQuery UI for Autocomplete -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<script>
$(function() {
    $('#client_search').autocomplete({
        source: function(request, response) {
            $.getJSON('../ajax/search_clients.php', { q: request.term }, response);
        },
        minLength: 2,
        select: function(event, ui) {
            $('#client_search').val(ui.item.label);
            $('#client_id').val(ui.item.value);
            return false;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
