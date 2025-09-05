<?php
session_start();
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['review_queue']) || empty($_SESSION['review_queue'])) {
    echo "<div class='container mt-5'><div class='alert alert-info'>No resumes pending review. <a href='bulk_upload.php'>Back to Upload</a></div></div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$current = array_shift($_SESSION['review_queue']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO candidates (name, email, phone, city, state, zip, linkedin, resume_text, status, owner, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['city'],
        $_POST['state'],
        $_POST['zip'],
        $_POST['linkedin'],
        $_POST['resume_text'],
        $_POST['status'],
        $_POST['owner']
    ]);

    echo "<div class='container mt-4 alert alert-success'>Candidate saved successfully.</div>";

    if (!empty($_SESSION['review_queue'])) {
        header("Refresh:1");
        exit;
    } else {
        unset($_SESSION['review_queue']);
        echo "<div class='container mt-3'><a href='bulk_upload.php' class='btn btn-primary'>Back to Upload</a></div>";
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}
?>

<div class="container mt-4">
    <h2>Review Parsed Resume</h2>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($current['full_name']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($current['email']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($current['phone']) ?>">
        </div>

        <div class="row">
            <div class="col-md-4">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($current['city']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">State</label>
                <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($current['state']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Zip</label>
                <input type="text" name="zip" class="form-control" value="<?= htmlspecialchars($current['zip']) ?>">
            </div>
        </div>

        <div class="mb-3 mt-3">
            <label class="form-label">LinkedIn</label>
            <input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($current['linkedin']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Resume Text</label>
            <textarea name="resume_text" class="form-control" rows="10"><?= htmlspecialchars($current['resume_text']) ?></textarea>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <input type="text" name="status" class="form-control" value="New">
            </div>
            <div class="col-md-6">
                <label class="form-label">Owner</label>
                <input type="text" name="owner" class="form-control" value="<?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'System') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-success">Save & Next</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

