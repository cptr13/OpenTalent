<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit;
}

// ---------------------------------------------------------
// Grab form fields
// ---------------------------------------------------------
$name               = isset($_POST['name']) ? $_POST['name'] : '';
$industry           = isset($_POST['industry']) ? $_POST['industry'] : '';
$location           = isset($_POST['location']) ? $_POST['location'] : '';
$account_manager    = isset($_POST['account_manager']) ? $_POST['account_manager'] : '';
$phone              = isset($_POST['phone']) ? $_POST['phone'] : '';
$website            = isset($_POST['website']) ? $_POST['website'] : '';   // from form input -> url column
$linkedin           = isset($_POST['linkedin']) ? $_POST['linkedin'] : '';  // optional
$company_size       = isset($_POST['company_size']) ? $_POST['company_size'] : ''; // optional
$about              = isset($_POST['about']) ? $_POST['about'] : '';
$primary_contact_id = isset($_POST['primary_contact_id']) ? $_POST['primary_contact_id'] : null;
$forceCreate        = (isset($_POST['force_create']) && $_POST['force_create'] === '1');

// Simple helper for escaping HTML
function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// US phone normalizer: keep digits, drop leading 1 if 11 digits
function normalize_phone($p) {
    $digits = preg_replace('/\D+/', '', (string)$p);
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return $digits;
    }
    // If it's not a 10-digit US number, we won't use it for matching
    return '';
}

// Normalize some inputs for matching
$nameTrim    = trim($name);
$phoneTrim   = trim($phone);
$websiteTrim = trim($website);
$phoneNorm   = normalize_phone($phoneTrim);

// ---------------------------------------------------------
// Derive tokens from the name for matching
//  - nameKey: distinctive token (shermco from "test shermco")
//  - firstToken: literally the first word (e.g., "test")
// ---------------------------------------------------------
$nameKey    = '';
$firstToken = '';

if ($nameTrim !== '') {
    // Split on space, dash, underscore, comma, dot, etc.
    $parts = preg_split('/[\s\-_,.]+/', $nameTrim);

    // Clean up any empty tokens
    $cleanParts = [];
    if (is_array($parts)) {
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $cleanParts[] = $p;
            }
        }
    }

    $stopwords = ['test', 'demo', 'sample'];

    if (!empty($cleanParts)) {
        // First token (for matching ANY "test ..." type records)
        $firstToken = $cleanParts[0];

        // Distinctive token logic (skip generic words like "test")
        $primary = '';
        $first   = $cleanParts[0];
        $firstLower = strtolower($first);

        if (strlen($first) >= 3 && !in_array($firstLower, $stopwords, true)) {
            $primary = $first;
        } elseif (isset($cleanParts[1]) && strlen($cleanParts[1]) >= 3) {
            $primary = $cleanParts[1];
        }

        if ($primary !== '') {
            $nameKey = $primary;
        }
    }
}

// ---------------------------------------------------------
// 1) DUPLICATE CHECK (NAME TOKENS OR URL OR NORMALIZED PHONE)
// ---------------------------------------------------------
if (
    !$forceCreate
    && (
        $nameKey !== '' ||
        $firstToken !== '' ||
        $phoneNorm !== '' ||
        $websiteTrim !== ''
    )
) {
    $duplicates = [];

    // --- Name + URL based matches (SQL) ---
    $checkSql = "
        SELECT id, name, location, phone, url, status 
        FROM clients 
        WHERE
            (
                :name_key <> '' 
                AND name <> '' 
                AND LOWER(name) LIKE LOWER(:name_key_like)
            )
            OR (
                :first_token <> ''
                AND name <> ''
                AND LOWER(name) LIKE LOWER(:first_token_like)
            )
            OR (
                :url <> '' 
                AND url <> '' 
                AND LOWER(TRIM(url)) = LOWER(TRIM(:url))
            )
        ORDER BY name ASC
    ";

    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':name_key'         => $nameKey,
        ':name_key_like'    => $nameKey !== '' ? '%' . $nameKey . '%' : '',
        ':first_token'      => $firstToken,
        ':first_token_like' => $firstToken !== '' ? '%' . $firstToken . '%' : '',
        ':url'              => $websiteTrim,
    ]);

    $rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

    // Index by id so we can merge in phone matches later without dupes
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
    }

    // --- Phone-based matches (PHP-level normalization) ---
    if ($phoneNorm !== '') {
        $phoneSql = "
            SELECT id, name, location, phone, url, status
            FROM clients
            WHERE phone IS NOT NULL AND phone <> ''
        ";
        $phoneStmt = $pdo->query($phoneSql);
        $phoneRows = $phoneStmt ? $phoneStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($phoneRows as $r) {
            $existingNorm = normalize_phone($r['phone']);
            if ($existingNorm !== '' && $existingNorm === $phoneNorm) {
                $byId[(int)$r['id']] = $r;
            }
        }
    }

    $duplicates = array_values($byId);

    if (!empty($duplicates)) {
        // Show a warning page with possible duplicates and "Create Anyway"
        require_once __DIR__ . '/../includes/header.php';
        ?>
        <div class="container my-4">
            <div class="alert alert-warning">
                <strong>Possible duplicate clients found.</strong><br>
                One or more existing clients appear to match by name, phone, or website.
                Review the records below. If this is truly a new client, you can still create it.
            </div>

            <h4 class="mb-3">
                Existing clients that may match
                "<?=
                    h(
                        $nameTrim !== ''
                            ? $nameTrim
                            : ($nameKey !== ''
                                ? $nameKey
                                : ($firstToken !== '' ? $firstToken : 'this record'))
                    )
                ?>":
            </h4>

            <ul class="list-group mb-4">
                <?php foreach ($duplicates as $dup): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <a href="view_client.php?id=<?= (int)$dup['id'] ?>">
                                <strong><?= h($dup['name']) ?></strong>
                            </a>
                            <?php if (!empty($dup['status'])): ?>
                                <span class="badge bg-secondary ms-2"><?= h($dup['status']) ?></span>
                            <?php endif; ?>
                            <div class="text-muted small mt-1">
                                <?php if (!empty($dup['location'])): ?>
                                    <span>Location: <?= h($dup['location']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($dup['phone'])): ?>
                                    <span class="ms-3">Phone: <?= h($dup['phone']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($dup['url'])): ?>
                                    <span class="ms-3">Website: <?= h($dup['url']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <a href="view_client.php?id=<?= (int)$dup['id'] ?>" class="btn btn-sm btn-outline-primary">
                                View Client
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <hr>

            <h5 class="mb-3">
                Still want to create "<span class="fw-bold"><?= h($nameTrim) ?></span>" as a new client?
            </h5>
            <p class="text-muted mb-3">
                If you're sure this is a different company, click "Create Anyway" below.
                All the details you entered will be saved as a new client.
            </p>

            <form method="POST" action="save_client.php" class="mb-3">
                <input type="hidden" name="force_create" value="1">

                <input type="hidden" name="name" value="<?= h($name) ?>">
                <input type="hidden" name="industry" value="<?= h($industry) ?>">
                <input type="hidden" name="location" value="<?= h($location) ?>">
                <input type="hidden" name="account_manager" value="<?= h($account_manager) ?>">
                <input type="hidden" name="phone" value="<?= h($phone) ?>">
                <input type="hidden" name="website" value="<?= h($website) ?>">
                <input type="hidden" name="linkedin" value="<?= h($linkedin) ?>">
                <input type="hidden" name="company_size" value="<?= h($company_size) ?>">
                <input type="hidden" name="about" value="<?= h($about) ?>">
                <input type="hidden" name="primary_contact_id" value="<?= h((string)$primary_contact_id) ?>">

                <button type="submit" class="btn btn-primary">Create Anyway</button>
                <a href="add_client.php" class="btn btn-outline-secondary ms-2">Go Back to Add Client</a>
            </form>
        </div>
        <?php
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}

// ---------------------------------------------------------
// 2) NO DUPLICATE (OR USER CONFIRMED) -> INSERT ROW
// ---------------------------------------------------------
try {
    $sql = "
        INSERT INTO clients (
            name,
            industry,
            location,
            account_manager,
            phone,
            url,
            linkedin,
            company_size,
            about,
            primary_contact_id,
            created_at
        ) VALUES (
            :name,
            :industry,
            :location,
            :account_manager,
            :phone,
            :url,
            :linkedin,
            :company_size,
            :about,
            :primary_contact_id,
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'               => $name,
        ':industry'           => $industry,
        ':location'           => $location,
        ':account_manager'    => $account_manager,
        ':phone'              => $phone,
        ':url'                => $website,  // bound to "url" column
        ':linkedin'           => $linkedin,
        ':company_size'       => $company_size !== '' ? $company_size : null,
        ':about'              => $about,
        ':primary_contact_id' => $primary_contact_id ?: null
    ]);

    header("Location: clients.php?success=1");
    exit;
} catch (PDOException $e) {
    echo "Error saving client: " . h($e->getMessage());
    exit;
}
