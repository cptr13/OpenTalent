<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "<div class='alert alert-danger'>No job ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    echo "<div class='alert alert-warning'>Job not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Status options
$status_options = [
    'Open',
    'In Progress',
    'Closed',
    'On Hold',
    'Canceled',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Job</h2>
    <a href="delete_job.php?id=<?= $job['id'] ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this job?');">Delete</a>
</div>

<form method="POST" action="save_job.php">
    <input type="hidden" name="id" value="<?= $job['id'] ?>">

    <div class="mb-3">
        <label for="title" class="form-label">Job Title</label>
        <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($job['title']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="location" class="form-label">Location</label>
        <input type="text" name="location" id="location" class="form-control" value="<?= htmlspecialchars($job['location']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="type" class="form-label">Job Type</label>
        <select name="type" id="type" class="form-select" required>
            <option value="">Select Job Type</option>
            <option value="Direct Hire" <?= $job['type'] === 'Direct Hire' ? 'selected' : '' ?>>Direct Hire</option>
            <option value="Contract/Project" <?= $job['type'] === 'Contract/Project' ? 'selected' : '' ?>>Contract/Project</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Status</label>
        <select name="status" id="status" class="form-select" required>
            <?php foreach ($status_options as $option): ?>
                <option value="<?= $option ?>" <?= $job['status'] === $option ? 'selected' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3 position-relative">
        <label for="client_search" class="form-label">Client</label>
        <input type="text" id="client_search" class="form-control" placeholder="Start typing client name..." autocomplete="off" value="<?= htmlspecialchars(getClientName($pdo, $job['client_id'])) ?>">
        <input type="hidden" name="client_id" id="client_id" value="<?= htmlspecialchars($job['client_id']) ?>">
        <div id="client_results" class="autocomplete-results"></div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea name="description" id="description" class="form-control" rows="5"><?= htmlspecialchars($job['description']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-success">Update Job</button>
    <a href="delete_job.php?id=<?= $job['id'] ?>" class="btn btn-danger float-end" onclick="return confirm('Are you sure you want to delete this job?');">Delete</a>
</form>

<style>
.autocomplete-results {
    position: absolute;
    z-index: 1000;
    background: white;
    border: 1px solid #ced4da;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    width: 100%;
}
.autocomplete-results div {
    padding: 8px 12px;
    cursor: pointer;
}
.autocomplete-results div:hover {
    background-color: #f8f9fa;
}
</style>

<script>
function setupAutocomplete(inputId, resultsId, hiddenId, endpoint) {
    const input = document.getElementById(inputId);
    const resultsBox = document.getElementById(resultsId);
    const hiddenInput = document.getElementById(hiddenId);

    input.addEventListener('input', function () {
        const query = this.value.trim();
        resultsBox.innerHTML = '';
        hiddenInput.value = '';

        if (query.length < 2) return;

        fetch(endpoint + '?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                resultsBox.innerHTML = '';
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.textContent = item.label;
                    div.dataset.id = item.id;
                    div.addEventListener('click', () => {
                        input.value = item.label;
                        hiddenInput.value = item.id;
                        resultsBox.innerHTML = '';
                    });
                    resultsBox.appendChild(div);
                });
            });
    });

    document.addEventListener('click', function (e) {
        if (!resultsBox.contains(e.target) && e.target !== input) {
            resultsBox.innerHTML = '';
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    setupAutocomplete('client_search', 'client_results', 'client_id', '/OT-Master/ajax/search_clients.php');
});
</script>

<?php
function getClientName($pdo, $client_id) {
    if (!$client_id) return '';
    $stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    return $stmt->fetchColumn() ?: '';
}
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
