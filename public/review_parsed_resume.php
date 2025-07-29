<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

function extract_field($pattern, $text) {
    if (preg_match($pattern, $text, $matches)) {
        return trim($matches[1] ?? $matches[0]);
    }
    return '';
}

// If form was submitted to create candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $linkedin = trim($_POST['linkedin']);
    $resume_text = trim($_POST['resume_text']);
    $source_file = trim($_POST['source_file']);

    // Attempt to split location into city, state, zip
    $city = $state = $zip = '';
    if (preg_match('/^(.+?),\s*([A-Z]{2})(?:\s+(\d{5}))?$/', $location, $matches)) {
        $city = $matches[1];
        $state = $matches[2];
        $zip = $matches[3] ?? '';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO candidates (first_name, last_name, email, phone, city, state, zip, linkedin, resume_text, resume_filename, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$first_name, $last_name, $email, $phone, $city, $state, $zip, $linkedin, $resume_text, $source_file]);
        $candidate_id = $pdo->lastInsertId();
        header("Location: view_candidate.php?id=" . urlencode($candidate_id));
        exit;
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error saving candidate: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Resume text and filename passed from parse_resume
$resumeText = $_POST['resume_text'] ?? '';
$sourceFile = $_POST['source_file'] ?? '';

// Extract basic fields from resume
$name = extract_field('/(?m)[\[\(]\s*([A-Z][a-z\.\'-]+(?:\s+[A-Z][a-z\.\'-]+){1,2})\s*[\]\)]/', $resumeText);
if (!$name) {
    $name = extract_field('/(?m)^([A-Z]{2,}(?:\s+[A-Z]{2,}){1,2})\s*$/', $resumeText);
}
if (!$name) {
    $name = extract_field('/(?m)^([A-Z][a-z\.\'-]+(?:\s+[A-Z][a-z\.\'-]+){1,2})\s*$/', $resumeText);
}
if (!$name) {
    $name = extract_field('/(?m)^([a-z][a-z\.\'-]+(?:\s+[a-z][a-z\.\'-]+){1,2})\s*$/', $resumeText);
}

$name_parts = explode(' ', $name, 2);
$first_name = $name_parts[0] ?? '';
$last_name = $name_parts[1] ?? '';

$email = extract_field('/([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+)/', $resumeText);
$phone = extract_field('/(?:(?:\+?1[\s.\-\/]?)?\(?\d{3}\)?[\s.\-\/]?\d{3}[\s.\-\/]?\d{4})/', $resumeText);
$phone = preg_replace('/[^0-9\+\(\)\s\.\-]/', '', $phone);

$location = extract_field('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*,\s+(?:Alabama|Alaska|Arizona|Arkansas|California|Colorado|Connecticut|Delaware|Florida|Georgia|Hawaii|Idaho|Illinois|Indiana|Iowa|Kansas|Kentucky|Louisiana|Maine|Maryland|Massachusetts|Michigan|Minnesota|Mississippi|Missouri|Montana|Nebraska|Nevada|New Hampshire|New Jersey|New Mexico|New York|North Carolina|North Dakota|Ohio|Oklahoma|Oregon|Pennsylvania|Rhode Island|South Carolina|South Dakota|Tennessee|Texas|Utah|Vermont|Virginia|Washington|West Virginia|Wisconsin|Wyoming|AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY)(?:\s+\d{5})?)/', $resumeText);

$linkedin = extract_field('/(https?:\/\/(www\.)?linkedin\.com\/in\/[a-zA-Z0-9\-_%]+)/', $resumeText);
?>

<div class="container mt-4">
    <h2>Review Parsed Resume Fields</h2>
    <form method="POST">
        <input type="hidden" name="source_file" value="<?= htmlspecialchars($sourceFile) ?>">

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($first_name) ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($last_name) ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($location) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">LinkedIn</label>
            <input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($linkedin) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Raw Resume Text (Reference)</label>
            <textarea name="resume_text" class="form-control" rows="12"><?= htmlspecialchars($resumeText) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success">Create Candidate</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
