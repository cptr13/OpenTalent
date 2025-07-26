<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Load status list
$statusList = require __DIR__ . '/../config/status_list.php';

$application_id = $_GET['id'] ?? null;

if (!$application_id) {
    echo "<div class='alert alert-danger'>Missing application ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Load application details
$stmt = $pdo->prepare("
    SELECT a.*, c.name AS candidate_name, c.id AS candidate_id, c.email, c.phone, j.title AS job_title, j.id AS job_id 
    FROM applications a
    JOIN candidates c ON a.candidate_id = c.id
    JOIN jobs j ON a.job_id = j.id
    WHERE a.id = ?
");
$stmt->execute([$application_id]);
$app = $stmt->fetch();

if (!$app) {
    echo "<div class='alert alert-warning'>Application not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Load application-specific notes
$stmt = $pdo->prepare("SELECT * FROM notes WHERE module_type = 'application' AND module_id = ? ORDER BY created_at DESC");
$stmt->execute([$application_id]);
$notes = $stmt->fetchAll();
?>

<div class="container my-4">
    <h2>Application Details</h2>

    <div class="card mb-4">
        <div class="card-header fw-bold">Candidate & Job Info</div>
        <div class="card-body">
            <p><strong>Candidate:</strong> <a href="view_candidate.php?id=<?= $app['candidate_id'] ?>"><?= htmlspecialchars($app['candidate_name']) ?></a></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($app['email']) ?> | <strong>Phone:</strong> <?= htmlspecialchars($app['phone']) ?></p>
            <p><strong>Job:</strong> <a href="view_job.php?id=<?= $app['job_id'] ?>"><?= htmlspecialchars($app['job_title']) ?></a></p>
            <p><strong>Status:</strong> <span class="badge bg-info"><?= htmlspecialchars($app['status']) ?></span></p>
            <a href="edit_application.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary mt-2">Edit Application</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Notes</span>
            <a href="edit_application.php?id=<?= $application_id ?>" class="btn btn-sm btn-outline-secondary">Add Note</a>
        </div>
        <div class="card-body">
            <?php if (empty($notes)): ?>
                <p class="text-muted mb-0">No notes available for this application.</p>
            <?php else: ?>
                <ul class="list-group">
                    <?php foreach ($notes as $note): ?>
                        <li class="list-group-item">
                            <small class="text-muted"><?= date('F j, Y \a\t g:i A', strtotime($note['created_at'])) ?></small><br>
                            <?= nl2br(htmlspecialchars($note['content'])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

