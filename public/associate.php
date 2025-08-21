<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/kpi_logger.php'; // <-- add KPI logger

$statusList = require __DIR__ . '/../config/status_list.php';

$prefill_candidate_id = $_GET['candidate_id'] ?? '';
$prefill_candidate_name = '';
$prefill_contact_id = $_GET['contact_id'] ?? '';
$prefill_contact_name = '';
$prefill_job_id = $_GET['job_id'] ?? '';
$prefill_job_title = '';
$return = $_GET['return'] ?? '';
$success_message = '';
$error_message = '';

// Fetch candidate name
if ($prefill_candidate_id) {
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM candidates WHERE id = ?");
    $stmt->execute([$prefill_candidate_id]);
    if ($row = $stmt->fetch()) {
        $prefill_candidate_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }
}

// Fetch contact name
if ($prefill_contact_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM contacts WHERE id = ?");
    $stmt->execute([$prefill_contact_id]);
    if ($row = $stmt->fetch()) {
        $prefill_contact_name = $row['full_name'] ?? '';
    }
}

// Fetch job title
if ($prefill_job_id) {
    $stmt = $pdo->prepare("SELECT title FROM jobs WHERE id = ?");
    $stmt->execute([$prefill_job_id]);
    if ($row = $stmt->fetch()) {
        $prefill_job_title = $row['title'] ?? '';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate_id = isset($_POST['candidate_id']) ? (int)$_POST['candidate_id'] : null;
    $contact_id   = isset($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
    $job_id       = isset($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    // Note: your default option text in the select is "Associated to Job" (no prefix).
    $status       = isset($_POST['status']) ? trim($_POST['status']) : 'Associated to Job';

    if ($candidate_id && $job_id) {
        try {
            // Create association
            $stmt = $pdo->prepare("
                INSERT INTO associations (candidate_id, job_id, status, assigned_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$candidate_id, $job_id, $status]);

            $association_id = (int)$pdo->lastInsertId();

            // Log initial recruiting KPI (only logs if mapped; skips if bucket = 'none')
            $user_id = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
            try {
                // kpi_logger expects: (pdo, association_id, candidate_id, job_id, new_status, old_status=null, changed_by)
                kpi_log_status_change($pdo, $association_id, $candidate_id, $job_id, $status, null, $user_id);
            } catch (Throwable $e) {
                // Don't break association flow if KPI logging fails
                // error_log('KPI recruiting log failed on associate.php: ' . $e->getMessage());
            }

            header("Location: view_candidate.php?id=" . urlencode($candidate_id) . "&open_assoc=" . urlencode($association_id));
            exit;
        } catch (PDOException $e) {
            $error_message = "Error associating candidate: " . htmlspecialchars($e->getMessage());
        }
    }

    if ($contact_id && $job_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO job_contacts (contact_id, job_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$contact_id, $job_id]);
            $success_message = "Contact associated successfully.";
        } catch (PDOException $e) {
            $error_message = "Error associating contact: " . htmlspecialchars($e->getMessage());
        }
    }

    if (empty($candidate_id) && empty($contact_id)) {
        $error_message = "Please select a candidate and/or a contact to associate.";
    }
}
?>

<div class="container my-4">
    <h2>Associate Candidate and/or Contact to Job</h2>

    <?php if ($return): ?>
        <div class="d-flex justify-content-end mb-3">
            <a href="<?= htmlspecialchars($return) ?>" class="btn btn-outline-secondary">&larr; Back</a>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php elseif ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- Candidate -->
        <div class="mb-3 position-relative">
            <label for="candidate_search" class="form-label">Candidate</label>
            <input type="text" id="candidate_search" class="form-control" placeholder="Search candidate..." autocomplete="off" value="<?= htmlspecialchars($prefill_candidate_name) ?>">
            <input type="hidden" name="candidate_id" id="candidate_id" value="<?= htmlspecialchars($prefill_candidate_id) ?>">
            <div id="candidate_results" class="autocomplete-results"></div>
        </div>

        <!-- Candidate Status -->
        <div class="mb-3">
            <label for="status" class="form-label">Initial Candidate Status</label>
            <select name="status" id="status" class="form-select">
                <?php foreach ($statusList as $category => $statuses): ?>
                    <optgroup label="<?= htmlspecialchars($category) ?>">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $s === 'Associated to Job' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                Note: Only KPI‑mapped statuses (e.g., “Attempted to Contact”, “Screening / Conversation”) will log to the tracker. “Associated to Job” won’t count (by design).
            </div>
        </div>

        <!-- Contact -->
        <div class="mb-3 position-relative">
            <label for="contact_search" class="form-label">Contact</label>
            <input type="text" id="contact_search" class="form-control" placeholder="Search contact..." autocomplete="off" value="<?= htmlspecialchars($prefill_contact_name) ?>">
            <input type="hidden" name="contact_id" id="contact_id" value="<?= htmlspecialchars($prefill_contact_id) ?>">
            <div id="contact_results" class="autocomplete-results"></div>
        </div>

        <!-- Job -->
        <div class="mb-3 position-relative">
            <label for="job_search" class="form-label">Job</label>
            <input type="text" id="job_search" class="form-control" placeholder="Search job..." autocomplete="off" value="<?= htmlspecialchars($prefill_job_title) ?>">
            <input type="hidden" name="job_id" id="job_id" value="<?= htmlspecialchars($prefill_job_id) ?>">
            <div id="job_results" class="autocomplete-results"></div>
        </div>

        <button type="submit" class="btn btn-success">Associate</button>
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
    setupAutocomplete('candidate_search', 'candidate_results', 'candidate_id', '/OT-Master/ajax/search_candidates.php');
    setupAutocomplete('job_search', 'job_results', 'job_id', '/OT-Master/ajax/search_jobs.php');
    setupAutocomplete('contact_search', 'contact_results', 'contact_id', '/OT-Master/ajax/search_contacts.php');
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
