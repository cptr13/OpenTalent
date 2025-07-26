<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../config/database.php';
require_once '../includes/header.php';

$query = trim($_GET['q'] ?? '');

if (!$query) {
    echo "<div class='alert alert-warning'>No search query provided.</div>";
    require_once '../includes/footer.php';
    exit;
}

$searchTerm = "%$query%";

function highlight($text, $term) {
    return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<strong>$1</strong>', $text);
}

// Candidates (using 'name' and 'email' only)
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE name LIKE ? OR email LIKE ?");
$stmt->execute([$searchTerm, $searchTerm]);
$candidates = $stmt->fetchAll();

// Jobs
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE title LIKE ? OR description LIKE ?");
$stmt->execute([$searchTerm, $searchTerm]);
$jobs = $stmt->fetchAll();

// Clients
$stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ? OR company_name LIKE ?");
$stmt->execute([$searchTerm, $searchTerm]);
$clients = $stmt->fetchAll();

// Contacts
$stmt = $pdo->prepare("
    SELECT * FROM contacts 
    WHERE full_name LIKE ? OR email LIKE ?
");
$stmt->execute([$searchTerm, $searchTerm]);
$contacts = $stmt->fetchAll();
?>

<div class="container mt-4">
    <h2>Search Results for "<?= htmlspecialchars($query) ?>"</h2>
    <hr>

    <!-- Candidates -->
    <h4>Candidates</h4>
    <?php if (!empty($candidates)): ?>
        <ul class="list-group mb-4">
            <?php foreach ($candidates as $c): ?>
                <li class="list-group-item">
                    <a href="view_candidate.php?id=<?= $c['id'] ?>" class="fw-bold d-block">
                        <?= highlight(htmlspecialchars($c['name']), $query) ?>
                    </a>
                    <span class="text-muted small">
                        <?= highlight(htmlspecialchars($c['email']), $query) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No candidates found.</p>
    <?php endif; ?>

    <!-- Jobs -->
    <h4>Jobs</h4>
    <?php if (!empty($jobs)): ?>
        <ul class="list-group mb-4">
            <?php foreach ($jobs as $j): ?>
                <li class="list-group-item">
                    <a href="view_job.php?id=<?= $j['id'] ?>">
                        <?= highlight(htmlspecialchars($j['title']), $query) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No jobs found.</p>
    <?php endif; ?>

    <!-- Clients -->
    <h4>Clients</h4>
    <?php if (!empty($clients)): ?>
        <ul class="list-group mb-4">
            <?php foreach ($clients as $cl): ?>
                <li class="list-group-item">
                    <a href="view_client.php?id=<?= $cl['id'] ?>">
                        <?= highlight(htmlspecialchars($cl['name']), $query) ?>
                        <?php if (!empty($cl['company_name'])): ?>
                            <span class="text-muted small">
                                (<?= highlight(htmlspecialchars($cl['company_name']), $query) ?>)
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No clients found.</p>
    <?php endif; ?>

    <!-- Contacts -->
    <h4>Contacts</h4>
    <?php if (!empty($contacts)): ?>
        <ul class="list-group mb-4">
            <?php foreach ($contacts as $ct): ?>
                <li class="list-group-item">
                    <a href="view_contact.php?id=<?= $ct['id'] ?>" class="fw-bold d-block">
                        <?= highlight(htmlspecialchars($ct['full_name']), $query) ?>
                    </a>
                    <span class="text-muted small">
                        <?= highlight(htmlspecialchars($ct['email']), $query) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No contacts found.</p>
    <?php endif; ?>
</div>

<style>
    strong {
        background-color: yellow;
        font-weight: bold;
        padding: 0 2px;
        border-radius: 2px;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
