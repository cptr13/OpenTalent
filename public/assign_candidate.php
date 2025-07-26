<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$client_id = $_GET['client_id'] ?? null;

// Fetch all candidates
$candidates = $pdo->query("SELECT id, name FROM candidates ORDER BY name")->fetchAll();

// Fetch jobs (filter by client_id if provided)
if ($client_id) {
    $stmt = $pdo->prepare("SELECT id, title FROM jobs WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->execute([$client_id]);
    $jobs = $stmt->fetchAll();
} else {
    $jobs = $pdo->query("SELECT id, title FROM jobs ORDER BY created_at DESC")->fetchAll();
}
?>

<div class="container mt-4">
    <h2>Assign Candidate to Job</h2>
    <form method="POST" action="assign.php">
        <?php if ($client_id): ?>
            <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id) ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label for="candidate_id" class="form-label">Candidate</label>
            <select name="candidate_id" id="candidate_id" class="form-select" required>
                <option value="">-- Choose a Candidate --</option>
                <?php foreach ($candidates as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="job_id" class="form-label">Job</label>
            <select name="job_id" id="job_id" class="form-select" required>
                <option value="">-- Choose a Job --</option>
                <?php foreach ($jobs as $j): ?>
                    <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($jobs)): ?>
                <div class="text-muted mt-2">No jobs available for this client.</div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">Initial Status</label>
            <select name="status" id="status" class="form-select">
                <option selected>Screening: Associated to Job</option>
                <option>Screening: Attempted to Contact</option>
                <option>Screening: Sent Text Message</option>
                <option>Screening: Received Text Message</option>
                <option>Screening: Left Voicemail</option>
                <option>Screening: Sent Email</option>
                <option>Screening: Received Email</option>
                <option>Screening: Conversation/Screening</option>
                <option>Screening: Not interested-Screening Call</option>
                <option>Screening: Qualified-Screening Call</option>
                <option>Screening: Unqualified-Screening Call</option>
                <option>Screening: Submitted to client</option>
                <option>Interview: Client Interview to be scheduled</option>
                <option>Offered: Offer To Be Made</option>
                <option>Hired</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Assign Candidate</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
