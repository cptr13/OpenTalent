<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';

$prefill_client_id = $_GET['client_id'] ?? null;
$prefill_client_name = $_GET['client_name'] ?? '';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Add Job</h2>
    </div>

    <form method="POST" action="save_job.php">
        <div class="mb-3">
            <label class="form-label">Job Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3 position-relative">
            <label class="form-label">Client</label>
            <input type="text" id="client_search" class="form-control" placeholder="Start typing client name..." autocomplete="off" value="<?= htmlspecialchars($prefill_client_name) ?>" required>
            <input type="hidden" name="client_id" id="client_id" value="<?= is_numeric($prefill_client_id) ? htmlspecialchars($prefill_client_id) : '' ?>">
            <div id="client_results" class="autocomplete-results"></div>
        </div>

        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control">
        </div>

        <div class="mb-3">
            <label class="form-label">Job Type</label>
            <select name="type" class="form-select" required>
                <option value="">Select Job Type</option>
                <option value="Direct Hire">Direct Hire</option>
                <option value="Contract/Project">Contract/Project</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="5"></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Initial Status</label>
            <select name="status" class="form-control">
                <option value="Open">Open</option>
                <option value="On Hold">On Hold</option>
                <option value="Closed">Closed</option>
                <option value="Filled">Filled</option>
                <option value="Cancelled">Cancelled</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Save Job</button>
    </form>
</div>

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
                    div.dataset.id = item.value;
                    div.addEventListener('click', () => {
                        input.value = item.label;
                        hiddenInput.value = div.dataset.id;
                        console.log('✅ [Client] client_id set to:', hiddenInput.value);
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

<script>
document.querySelector('form').addEventListener('submit', function (e) {
    const clientId = document.getElementById('client_id').value;
    console.log('📤 Submitting with client_id:', clientId);
});
</script>

<?php require_once '../includes/footer.php'; ?>
