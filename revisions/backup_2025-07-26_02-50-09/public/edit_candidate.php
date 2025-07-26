<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';

// Get candidate ID
$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    echo "<div class='alert alert-danger'>Invalid candidate ID.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Load candidate data
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    echo "<div class='alert alert-danger'>Candidate not found.</div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Candidate</h2>
    <a href="paste_resume.php?redirect=edit&id=<?= $candidate['id'] ?>" class="btn btn-outline-warning">📋 Paste Resume</a>
</div>

<form method="POST" action="update_candidate.php" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $candidate['id'] ?>">

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($candidate['first_name'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($candidate['last_name'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($candidate['email'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label>Secondary Email</label>
            <input type="email" name="secondary_email" class="form-control" value="<?= htmlspecialchars($candidate['secondary_email'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label>LinkedIn</label>
            <input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($candidate['linkedin'] ?? '') ?>">
        </div>
    </div>

    <h5 class="mt-4">Address</h5>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Street</label>
            <input type="text" name="street" class="form-control" value="<?= htmlspecialchars($candidate['street'] ?? '') ?>">
            <label>State</label>
            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($candidate['state'] ?? '') ?>">
            <label>Country</label>
            <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($candidate['country'] ?? '') ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label>City</label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($candidate['city'] ?? '') ?>">
            <label>Zip</label>
            <input type="text" name="zip" class="form-control" value="<?= htmlspecialchars($candidate['zip'] ?? '') ?>">
        </div>
    </div>

    <h5 class="mt-4">Professional</h5>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Experience in Years</label>
            <input type="text" name="experience_years" class="form-control" value="<?= htmlspecialchars($candidate['experience_years'] ?? '') ?>">
            <label>Current Job Title</label>
            <input type="text" name="current_job" class="form-control" value="<?= htmlspecialchars($candidate['current_job'] ?? '') ?>">
            <label>Current Pay</label>
            <input type="text" name="current_pay" class="form-control" value="<?= htmlspecialchars($candidate['current_pay'] ?? '') ?>">
            <label>Current Pay Type</label>
            <select name="current_pay_type" class="form-control">
                <option value="">-- Select --</option>
                <option value="Salary" <?= $candidate['current_pay_type'] === 'Salary' ? 'selected' : '' ?>>Salary</option>
                <option value="Hourly" <?= $candidate['current_pay_type'] === 'Hourly' ? 'selected' : '' ?>>Hourly</option>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label>Current Employer</label>
            <input type="text" name="employer" class="form-control" value="<?= htmlspecialchars($candidate['current_employer'] ?? '') ?>">
            <label>Expected Pay</label>
            <input type="text" name="expected_pay" class="form-control" value="<?= htmlspecialchars($candidate['expected_pay'] ?? '') ?>">
            <label>Expected Pay Type</label>
            <select name="expected_pay_type" class="form-control">
                <option value="">-- Select --</option>
                <option value="Salary" <?= $candidate['expected_pay_type'] === 'Salary' ? 'selected' : '' ?>>Salary</option>
                <option value="Hourly" <?= $candidate['expected_pay_type'] === 'Hourly' ? 'selected' : '' ?>>Hourly</option>
            </select>
            <label>Additional Info</label>
            <textarea name="additional_info" class="form-control"><?= htmlspecialchars($candidate['additional_info'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Status</label>
            <select name="status" class="form-control">
                <option <?= $candidate['status'] === 'New' ? 'selected' : '' ?>>New</option>
                <option <?= $candidate['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                <option <?= $candidate['status'] === 'Placed' ? 'selected' : '' ?>>Placed</option>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label>Source</label>
            <input type="text" name="source" class="form-control" value="<?= htmlspecialchars($candidate['source'] ?? '') ?>">
        </div>
    </div>

    <h5 class="mt-4">Attachments</h5>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Resume File</label>
            <input type="file" name="resume_file" class="form-control">
            <label>Formatted Resume</label>
            <input type="file" name="formatted_resume_file" class="form-control">
            <label>Cover Letter</label>
            <input type="file" name="cover_letter_file" class="form-control">
        </div>
        <div class="col-md-6 mb-3">
            <label>Contract</label>
            <input type="file" name="contract_file" class="form-control">
            <label>Other Attachment 1</label>
            <input type="file" name="other_attachment_1" class="form-control">
            <label>Other Attachment 2</label>
            <input type="file" name="other_attachment_2" class="form-control">
        </div>
    </div>

    <button type="submit" class="btn btn-success">Update Candidate</button>
</form>

<?php require_once '../includes/footer.php'; ?>
