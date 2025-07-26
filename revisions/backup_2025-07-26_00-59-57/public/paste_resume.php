<?php
session_start();

require_once __DIR__ . '/../includes/require_login.php';
require_once '../config/database.php';
require_once '../includes/header.php';

$redirect = $_GET['redirect'] ?? 'add';
$id = $_GET['id'] ?? null;

$rawText = '';
$parsed = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'location' => '',
    'linkedin' => '',
    'skills' => '',
];
$generatedFilename = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawText = trim($_POST['resume_text'] ?? '');

    if ($rawText) {
        $rawText = str_replace(['–', '—', "\xC2\xA0"], '-', $rawText);
        $rawText = preg_replace('/[^\P{C}\n\r]+/u', '', $rawText);
        $rawText = preg_replace('/[ \t]+/', ' ', $rawText);
        $lines = preg_split("/\r\n|\n|\r/", $rawText);

        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $rawText, $m)) {
            $parsed['email'] = $m[0];
        }

        if (
            preg_match('/\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $rawText, $m) ||
            preg_match('/\b\d{10}\b/', $rawText, $m)
        ) {
            $parsed['phone'] = trim($m[0]);
        }

        if (preg_match('/(https?:\/\/)?(www\.)?linkedin\.com\/[^\s)]+/i', $rawText, $m)) {
            $parsed['linkedin'] = $m[0];
        } elseif (preg_match('/https?:\/\/[^\s)]+/i', $rawText, $m)) {
            $parsed['linkedin'] = $m[0];
        }

        foreach (array_slice($lines, 0, 5) as $line) {
            $line = trim($line);
            if (
                strlen($line) > 2 &&
                !preg_match('/\d/', $line) &&
                strpos($line, '@') === false &&
                !preg_match('/https?:\/\//i', $line) &&
                substr_count($line, ' ') <= 4 &&
                (
                    preg_match('/^[A-Z][a-zA-Z\'\.\-]+(\s[A-Z][a-zA-Z\'\.\-]+)+$/', $line) ||
                    preg_match('/^[A-Z]{2,}(\s[A-Z]{2,})+$/', $line)
                )
            ) {
                $parsed['full_name'] = ucwords(strtolower($line));
                break;
            }
        }

        if (preg_match('/skills\s*[:\n]\s*(.+?)(\n|$)/i', $rawText, $m)) {
            $parsed['skills'] = trim($m[1]);
        }

        if (preg_match('/\b([A-Z][a-z]+(?:\s[A-Z][a-z]+)?)\s*,?\s*(AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY|Alabama|Alaska|Arizona|Arkansas|California|Colorado|Connecticut|Delaware|Florida|Georgia|Hawaii|Idaho|Illinois|Indiana|Iowa|Kansas|Kentucky|Louisiana|Maine|Maryland|Massachusetts|Michigan|Minnesota|Mississippi|Missouri|Montana|Nebraska|Nevada|New Hampshire|New Jersey|New Mexico|New York|North Carolina|North Dakota|Ohio|Oklahoma|Oregon|Pennsylvania|Rhode Island|South Carolina|South Dakota|Tennessee|Texas|Utah|Vermont|Virginia|Washington|West Virginia|Wisconsin|Wyoming)\b/', $rawText, $m)) {
            $parsed['location'] = trim($m[0]);
        }

        // Save the raw resume as a .txt file
        $safeName = strtolower(preg_replace('/[^a-z0-9]/i', '_', $parsed['full_name'] ?: 'resume'));
        $filename = "resume_" . $safeName . "_" . time() . ".txt";
        $destination = __DIR__ . '/../uploads/resumes/' . $filename;

        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0777, true);
        }

        file_put_contents($destination, $rawText);
        $generatedFilename = $filename;
    }
}
?>

<div class="container-fluid px-4 mt-4">
    <h2>Paste Resume Text</h2>
    <hr>

    <form method="POST">
        <div class="mb-3">
            <label for="resume_text" class="form-label">Paste Resume Content</label>
            <textarea name="resume_text" id="resume_text" class="form-control" rows="10" required><?= htmlspecialchars($rawText) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Parse Resume</button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rawText): ?>
        <hr>
        <h4>Review Parsed Info</h4>
        <form action="save_candidate.php" method="POST">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <?php if (!empty($id)): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
            <?php endif; ?>
            <?php if (!empty($generatedFilename)): ?>
                <input type="hidden" name="resume_filename" value="<?= htmlspecialchars($generatedFilename) ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($parsed['full_name']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($parsed['email']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($parsed['phone']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($parsed['location']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">LinkedIn / Website</label>
                <input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($parsed['linkedin']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Skills</label>
                <textarea name="skills" class="form-control" rows="4"><?= htmlspecialchars($parsed['skills']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-success">Save Candidate</button>
            <a href="paste_resume.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

