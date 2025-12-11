<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$query = trim($_GET['q'] ?? '');

if ($query === '') {
    echo "<div class='alert alert-warning'>No search query provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$searchTerm = "%{$query}%";

function highlight($text, $term) {
    return preg_replace('/(' . preg_quote($term, '/') . ')/i', '<strong>$1</strong>', $text);
}

/**
 * CANDIDATES
 * - New schema: first_name, last_name, email
 * - Legacy: name (kept for older records)
 */
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, name, email
    FROM candidates
    WHERE first_name LIKE ?
       OR last_name LIKE ?
       OR CONCAT(first_name, ' ', last_name) LIKE ?
       OR name LIKE ?
       OR email LIKE ?
");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * JOBS
 */
$stmt = $pdo->prepare("
    SELECT id, title, description
    FROM jobs
    WHERE title LIKE ?
       OR description LIKE ?
");
$stmt->execute([$searchTerm, $searchTerm]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * CLIENTS
 * - Current schema uses `name` for the client name.
 * - `company_name` is not used elsewhere; instead search name, industry, location.
 */
$stmt = $pdo->prepare("
    SELECT id, name, industry, location
    FROM clients
    WHERE name LIKE ?
       OR industry LIKE ?
       OR location LIKE ?
");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * CONTACTS
 * - New schema: first_name, last_name, email
 * - Use CONCAT for full-name style searching.
 */
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email
    FROM contacts
    WHERE first_name LIKE ?
       OR last_name LIKE ?
       OR CONCAT(first_name, ' ', ' ', last_name) LIKE ?
       OR CONCAT(first_name, ' ', last_name) LIKE ?
       OR email LIKE ?
");
$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <h2>Search Results for "<?= htmlspecialchars($query) ?>"</h2>
    <hr>

    <!-- Candidates -->
    <h4>Candidates</h4>
    <?php if (!empty($candidates)): ?>
        <ul class="list-group mb-4">
            <?php foreach ($candidates as $c): ?>
                <?php
                    $nameParts = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    $displayName = $nameParts !== '' ? $nameParts : ($c['name'] ?? '');
                ?>
                <li class="list-group-item">
                    <a href="view_candidate.php?id=<?= (int)$c['id'] ?>" class="fw-bold d-block">
                        <?= highlight(htmlspecialchars($displayName), $query) ?>
                    </a>
                    <?php if (!empty($c['email'])): ?>
                        <span class="text-muted small">
                            <?= highlight(htmlspecialchars($c['email']), $query) ?>
                        </span>
                    <?php endif; ?>
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
                    <a href="view_job.php?id=<?= (int)$j['id'] ?>">
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
                    <a href="view_client.php?id=<?= (int)$cl['id'] ?>">
                        <?= highlight(htmlspecialchars($cl['name']), $query) ?>
                    </a>
                    <div class="text-muted small mt-1">
                        <?php if (!empty($cl['industry'])): ?>
                            <span><?= highlight(htmlspecialchars($cl['industry']), $query) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($cl['location'])): ?>
                            <?php if (!empty($cl['industry'])): ?> • <?php endif; ?>
                            <span><?= highlight(htmlspecialchars($cl['location']), $query) ?></span>
                        <?php endif; ?>
                    </div>
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
                <?php
                    $contactName = trim(($ct['first_name'] ?? '') . ' ' . ($ct['last_name'] ?? ''));
                ?>
                <li class="list-group-item">
                    <a href="view_contact.php?id=<?= (int)$ct['id'] ?>" class="fw-bold d-block">
                        <?= highlight(htmlspecialchars($contactName), $query) ?>
                    </a>
                    <?php if (!empty($ct['email'])): ?>
                        <span class="text-muted small">
                            <?= highlight(htmlspecialchars($ct['email']), $query) ?>
                        </span>
                    <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
