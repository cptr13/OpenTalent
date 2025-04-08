<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>No valid candidate ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-danger'>Candidate not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'name', 'email', 'phone', 'linkedin', 'facebook', 'twitter', 'website',
        'job_title', 'employer', 'experience', 'current_salary', 'expected_salary',
        'skills', 'status', 'source', 'notes'
    ];

    $update = [];
    $params = [];

    foreach ($fields as $field) {
        $value = trim($_POST[$field] ?? '');
        $update[] = "$field = ?";
        $params[] = $value;
    }

    if (!empty($_FILES['resume']['name'])) {
        $uploads_dir = __DIR__ . '/uploads/';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

        $resume = time() . "_" . basename($_FILES['resume']['name']);
        move_uploaded_file($_FILES['resume']['tmp_name'], $uploads_dir . $resume);
        $update[] = "resume_filename = ?";
        $params[] = $resume;
    }

    $params[] = $id;

    $sql = "UPDATE candidates SET " . implode(', ', $update) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Redirect to view page
    header("Location: view_candidate.php?id=$id");
    exit;
}
?>

<h2 class="mb-4">Edit Candidate</h2>

<form method="post" enctype="multipart/form-data">
    <div class="form-row">
        <div class="form-group col-md-6"><label>Full Name</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($candidate['name']) ?>" required></div>
        <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($candidate['email']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($candidate['phone']) ?>"></div>
        <div class="form-group col-md-6"><label>Website</label><input type="text" name="website" class="form-control" value="<?= htmlspecialchars($candidate['website']) ?>"></div>
    </div>

    <h5>Social</h5>
    <div class="form-row">
        <div class="form-group col-md-4"><label>LinkedIn</label><input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($candidate['linkedin']) ?>"></div>
        <div class="form-group col-md-4"><label>Facebook</label><input type="text" name="facebook" class="form-control" value="<?= htmlspecialchars($candidate['facebook']) ?>"></div>
        <div class="form-group col-md-4"><label>Twitter</label><input type="text" name="twitter" class="form-control" value="<?= htmlspecialchars($candidate['twitter']) ?>"></div>
    </div>

    <h5>Professional</h5>
    <div class="form-row">
        <div class="form-group col-md-6"><label>Job Title</label><input type="text" name="job_title" class="form-control" value="<?= htmlspecialchars($candidate['job_title']) ?>"></div>
        <div class="form-group col-md-6"><label>Employer</label><input type="text" name="employer" class="form-control" value="<?= htmlspecialchars($candidate['employer']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-4"><label>Experience (yrs)</label><input type="number" name="experience" class="form-control" value="<?= htmlspecialchars($candidate['experience']) ?>"></div>
        <div class="form-group col-md-4"><label>Current Salary</label><input type="text" name="current_salary" class="form-control" value="<?= htmlspecialchars($candidate['current_salary']) ?>"></div>
        <div class="form-group col-md-4"><label>Expected Salary</label><input type="text" name="expected_salary" class="form-control" value="<?= htmlspecialchars($candidate['expected_salary']) ?>"></div>
    </div>
    <div class="form-group"><label>Skills</label><input type="text" name="skills" class="form-control" value="<?= htmlspecialchars($candidate['skills']) ?>"></div>

    <h5>Resume</h5>
    <div class="form-group">
        <?php if (!empty($candidate['resume_filename'])): ?>
            <p>Current file: <a href="uploads/<?= urlencode($candidate['resume_filename']) ?>" target="_blank"><?= htmlspecialchars($candidate['resume_filename']) ?></a></p>
        <?php endif; ?>
        <label>Upload New Resume</label>
        <input type="file" name="resume" class="form-control-file">
    </div>

    <div class="form-row">
        <div class="form-group col-md-4"><label>Status</label>
            <select name="status" class="form-control">
                <?php
                $statuses = ['New', 'Interviewing', 'Offer Extended', 'Hired', 'Rejected'];
                foreach ($statuses as $status) {
                    $selected = ($candidate['status'] === $status) ? 'selected' : '';
                    echo "<option $selected>$status</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group col-md-8"><label>Source</label><input type="text" name="source" class="form-control" value="<?= htmlspecialchars($candidate['source']) ?>"></div>
    </div>

    <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($candidate['notes']) ?></textarea></div>

    <button type="submit" class="btn btn-primary">Update Candidate</button>
    <a href="view_candidate.php?id=<?= $candidate['id'] ?>" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
