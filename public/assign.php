<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Fetch all candidates and jobs
$candidates = $pdo->query("SELECT id, name FROM candidates ORDER BY name ASC")->fetchAll();
$jobs = $pdo->query("SELECT id, title FROM jobs ORDER BY title ASC")->fetchAll();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidate_id = $_POST['candidate_id'] ?? null;
    $job_id = $_POST['job_id'] ?? null;

    if ($candidate_id && $job_id) {
        $stmt = $pdo->prepare("INSERT INTO applications (candidate_id, job_id, applied_at) VALUES (?, ?, NOW())");
        $stmt->execute([$candidate_id, $job_id]);
        $success = true;
    } else {
        $error = "Please select both a candidate and a job.";
    }
}
?>

<h2 class="mb-4">Assign Candidate to Job</h2>

<?php if ($success): ?>
    <div class="alert alert-success">Candidate successfully assigned to job.</div>
<?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post">
    <div class="form-group">
        <label>Candidate</label>
        <select name="candidate_id" class="form-control" required>
            <option value="">Select a candidate</option>
            <?php foreach ($candidates as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Job</label>
        <select name="job_id" class="form-control" required>
            <option value="">Select a job</option>
            <?php foreach ($jobs as $j): ?>
                <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Assign</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
