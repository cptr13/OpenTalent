<?php
session_start();
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$prefill = [
    'first_name' => $_POST['first_name'] ?? '',
    'last_name' => $_POST['last_name'] ?? '',
    'email' => $_POST['email'] ?? '',
    'secondary_email' => $_POST['secondary_email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'street' => $_POST['street'] ?? '',
    'city' => $_POST['city'] ?? ($_POST['location'] ?? ''),
    'state' => $_POST['state'] ?? '',
    'zip' => $_POST['zip'] ?? '',
    'country' => $_POST['country'] ?? '',
    'linkedin' => $_POST['linkedin'] ?? '',
    'resume_text' => $_POST['resume_text'] ?? '',
];
?>

<div class="container mt-5">
    <h2 class="mb-4">Create Candidate</h2>

    <form method="POST" action="save_candidate.php" enctype="multipart/form-data" id="candidateForm">
        <textarea name="resume_text" id="resumeText" style="display:none;"><?= htmlspecialchars($prefill['resume_text']) ?></textarea>

        <h5>Information</h5>
        <div class="row">
            <div class="col-md-6">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($prefill['first_name']) ?>">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($prefill['phone']) ?>">
                <label>Secondary Email</label>
                <input type="email" name="secondary_email" class="form-control" value="<?= htmlspecialchars($prefill['secondary_email']) ?>">
            </div>
            <div class="col-md-6">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($prefill['last_name']) ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($prefill['email']) ?>">
            </div>
        </div>

        <h5 class="mt-4">Address</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Street</label>
                <input type="text" name="street" class="form-control" value="<?= htmlspecialchars($prefill['street']) ?>">
                <label>State/Province</label>
                <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($prefill['state']) ?>">
                <label>Country</label>
                <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($prefill['country']) ?>">
            </div>
            <div class="col-md-6">
                <label>City</label>
                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($prefill['city']) ?>">
                <label>Zip/Postal Code</label>
                <input type="text" name="zip" class="form-control" value="<?= htmlspecialchars($prefill['zip']) ?>">
            </div>
        </div>

        <h5 class="mt-4">Professional Details</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Experience in Years</label>
                <input type="text" name="experience_years" class="form-control">
                <label>Current Job Title</label>
                <input type="text" name="current_job" class="form-control">
                <label>Current Pay</label>
                <input type="text" name="current_pay" class="form-control">
                <label>Current Pay Type</label>
                <select name="current_pay_type" class="form-control">
                    <option value="Salary">Salary</option>
                    <option value="Hourly">Hourly</option>
                </select>
            </div>
            <div class="col-md-6">
                <label>Current Employer</label>
                <input type="text" name="employer" class="form-control">
                <label>Expected Pay</label>
                <input type="text" name="expected_pay" class="form-control">
                <label>Expected Pay Type</label>
                <select name="expected_pay_type" class="form-control">
                    <option value="Salary">Salary</option>
                    <option value="Hourly">Hourly</option>
                </select>
                <label>Additional Info</label>
                <textarea name="additional_info" class="form-control"></textarea>
            </div>
        </div>

        <h5 class="mt-4">Social Links</h5>
        <div class="row">
            <div class="col-md-6">
                <label>LinkedIn</label>
                <input type="text" name="linkedin" class="form-control" value="<?= htmlspecialchars($prefill['linkedin']) ?>">
            </div>
        </div>

        <h5 class="mt-4">Attachment Information</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Resume</label>
                <input type="file" name="resume" id="resumeFile" class="form-control">
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="parseResume()">Parse Resume</button>
                <div id="parsedPreview" class="mt-3"></div>
                <label class="mt-3">Formatted Resume</label>
                <input type="file" name="formatted_resume" class="form-control">
                <label>Cover Letter</label>
                <input type="file" name="cover_letter" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Other Attachment 1</label>
                <input type="file" name="other1" class="form-control">
                <label>Other Attachment 2</label>
                <input type="file" name="other2" class="form-control">
                <label>Contract</label>
                <input type="file" name="contract" class="form-control">
            </div>
        </div>

        <h5 class="mt-4">Other Info</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Candidate Status</label>
                <select name="status" class="form-control">
                    <option>New</option>
                    <option>Active</option>
                    <option>Placed</option>
                </select>
                <label class="mt-3">Source</label>
                <input type="text" name="source" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Candidate Owner</label>
                <input type="text" name="owner" class="form-control"
                       value="<?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?>" readonly>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-4">Save Candidate</button>
    </form>
</div>

<script>
function parseResume() {
    const fileInput = document.getElementById('resumeFile');
    const preview = document.getElementById('parsedPreview');
    const resumeTextField = document.getElementById('resumeText');

    if (fileInput.files.length === 0) {
        alert("Please select a resume file first.");
        return;
    }

    const formData = new FormData();
    formData.append("resume", fileInput.files[0]);

    fetch("parse_resume.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            resumeTextField.value = data.raw_text || '';

            let html = '<h6>Parsed Work Experience</h6><ul>';
            data.work_experience.forEach(item => {
                html += `<li><strong>${item.title || ''}</strong> at ${item.company || ''} (${item.start_date || ''} - ${item.end_date || ''})<br><small>${item.description || ''}</small></li>`;
            });
            html += '</ul><h6 class="mt-3">Parsed Education</h6><ul>';
            data.education.forEach(item => {
                html += `<li><strong>${item.degree || ''}</strong> at ${item.institution || ''} (${item.start_date || ''} - ${item.end_date || ''})<br><small>${item.field_of_study || ''}</small></li>`;
            });
            html += '</ul>';
            preview.innerHTML = html;
        } else {
            preview.innerHTML = `<div class="text-danger">${data.message}</div>`;
        }
    })
    .catch(err => {
        preview.innerHTML = `<div class="text-danger">Error parsing resume.</div>`;
        console.error(err);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
