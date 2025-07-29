<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Query counts
$candidateCount = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$jobCount = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$clientCount = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$contactCount = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();

// Status Buckets
$statusBuckets = [
    'Screening' => [
        'New',
        'Associated to Job',
        'Attempted to Contact',
        'Contacted',
        'Screening / Conversation',
        'No-Show',
    ],
    'Interview' => [
        'Interview to be Scheduled',
        'Interview Scheduled',
        'Waiting on Client Feedback',
        'Second Interview to be Scheduled',
        'Second Interview Scheduled',
        'Submitted to Client',
        'Approved by Client',
    ],
    'Offer' => [
        'To be Offered',
        'Offer Made',
        'Offer Accepted',
        'Offer Declined',
        'Offer Withdrawn',
    ],
    'Hired' => ['Hired'],
    'Status Change / Other' => [
        'On Hold',
        'Position Closed',
        'Contact in Future',
    ],
    'Rejected' => [
        'Rejected',
        'Rejected – By Client',
        'Rejected – For Interview',
        'Rejected – Hirable',
        'Unqualified',
        'Not Interested',
    ],
    'Candidate Action / Limbo' => [
        'Ghosted',
        'Paused by Candidate',
        'Withdrawn by Candidate',
    ],
];

// Fetch jobs with associations
$jobsWithAssociations = $pdo->query("SELECT j.id, j.title FROM jobs j
    JOIN associations a ON j.id = a.job_id
    GROUP BY j.id
    ORDER BY j.title ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all association statuses
$assocStatuses = $pdo->query("SELECT job_id, status FROM associations")->fetchAll(PDO::FETCH_ASSOC);

// Build count map: [job_id][status] = count
$statusCounts = [];
foreach ($assocStatuses as $row) {
    $jobId = $row['job_id'];
    $status = $row['status'];
    if (!isset($statusCounts[$jobId][$status])) {
        $statusCounts[$jobId][$status] = 0;
    }
    $statusCounts[$jobId][$status]++;
}
?>

<div class="container mt-4">
    <h2 class="mb-2">Dashboard</h2>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <a href="candidates.php" class="text-decoration-none">
                <div class="card text-bg-primary text-center shadow rounded-4">
                    <div class="card-body">
                        <h4 class="card-title"><?= $candidateCount ?></h4>
                        <p class="card-text">Candidates</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="jobs.php" class="text-decoration-none">
                <div class="card text-bg-success text-center shadow rounded-4">
                    <div class="card-body">
                        <h4 class="card-title"><?= $jobCount ?></h4>
                        <p class="card-text">Jobs</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="clients.php" class="text-decoration-none">
                <div class="card text-bg-warning text-center shadow rounded-4">
                    <div class="card-body">
                        <h4 class="card-title"><?= $clientCount ?></h4>
                        <p class="card-text">Clients</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="contacts.php" class="text-decoration-none">
                <div class="card text-bg-dark text-center shadow rounded-4">
                    <div class="card-body">
                        <h4 class="card-title"><?= $contactCount ?></h4>
                        <p class="card-text">Contacts</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Associations Dashboard Card -->
    <div class="card shadow mb-5">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Pipeline Activity</h5>
        </div>
        <div class="card-body">
            <?php if (count($jobsWithAssociations) === 0): ?>
                <p class="text-muted">No associations found.</p>
            <?php else: ?>
                <?php foreach ($jobsWithAssociations as $job): ?>
                    <div class="mb-4">
                        <h6>
                            <a href="view_job.php?id=<?= $job['id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($job['title']) ?>
                            </a>
                        </h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($statusBuckets as $bucket => $statuses): ?>
                                <?php
                                    $count = 0;
                                    foreach ($statuses as $s) {
                                        if (!empty($statusCounts[$job['id']][$s])) {
                                            $count += $statusCounts[$job['id']][$s];
                                        }
                                    }
                                ?>
                                <div class="border rounded p-2 bg-light text-center" style="min-width: 120px;">
                                    <strong><?= $bucket ?></strong><br>
                                    <span class="badge bg-primary"><?= $count ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <hr>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

