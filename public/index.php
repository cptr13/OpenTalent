<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

// Query counts
$candidateCount = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$jobCount = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$clientCount = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$contactCount = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
?>

<div class="container mt-4">
    <h2 class="mb-2">Dashboard</h2>

    <div class="row g-4">
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
</div>

<?php require_once '../includes/footer.php'; ?>
