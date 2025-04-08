<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Get dashboard stats
try {
    $candidate_count = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    $job_count = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
    $application_count = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
} catch (Throwable $e) {
    die("Error loading dashboard: " . $e->getMessage());
}
?>

<h2 class="mb-4">Dashboard</h2>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Candidates</h5>
                <p class="card-text display-4"><?= $candidate_count ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Jobs</h5>
                <p class="card-text display-4"><?= $job_count ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Applications</h5>
                <p class="card-text display-4"><?= $application_count ?></p>
            </div>
        </div>
    </div>
</div>

<p class="lead">Use the navigation bar above to manage candidates, jobs, and application workflows.</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
