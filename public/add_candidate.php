<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Create Candidate</h2>
    <form method="POST" action="save_candidate.php" enctype="multipart/form-data">
        <h5>Basic Info</h5>
        <div class="row">
            <div class="col-md-6">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control">
                <label>Secondary Email</label>
                <input type="email" name="secondary_email" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" required>
                <label>Mobile</label>
                <input type="text" name="mobile" class="form-control">
                <label>Website</label>
                <input type="text" name="website" class="form-control">
            </div>
        </div>

        <h5 class="mt-4">Address Information</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Street</label>
                <input type="text" name="street" class="form-control">
                <label>City</label>
                <input type="text" name="city" class="form-control">
                <label>Country</label>
                <input type="text" name="country" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Zip/Postal Code</label>
                <input type="text" name="zip" class="form-control">
                <label>State/Province</label>
                <input type="text" name="state" class="form-control">
            </div>
        </div>

        <h5 class="mt-4">Professional Details</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Experience in Years</label>
                <input type="text" name="experience" class="form-control">
                <label>Current Job Title</label>
                <input type="text" name="current_job" class="form-control">
                <label>Expected Salary</label>
                <input type="text" name="expected_salary" class="form-control">
                <label>Skill Set</label>
                <input type="text" name="skills" class="form-control">
                <label>Skype ID</label>
                <input type="text" name="skype_id" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Highest Qualification Held</label>
                <input type="text" name="qualification" class="form-control">
                <label>Current Employer</label>
                <input type="text" name="employer" class="form-control">
                <label>Current Salary</label>
                <input type="text" name="current_salary" class="form-control">
                <label>Additional Info</label>
                <textarea name="additional_info" class="form-control"></textarea>
                <label>Twitter</label>
                <input type="text" name="twitter" class="form-control">
            </div>
        </div>

        <h5 class="mt-4">Social Links</h5>
        <div class="row">
            <div class="col-md-6">
                <label>LinkedIn</label>
                <input type="text" name="linkedin" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Facebook</label>
                <input type="text" name="facebook" class="form-control">
            </div>
        </div>

        <h5 class="mt-4">Attachment Information</h5>
        <div class="row">
            <div class="col-md-6">
                <label>Resume</label>
                <input type="file" name="resume" class="form-control">
                <label>Formatted Resume</label>
                <input type="file" name="formatted_resume" class="form-control">
                <label>Cover Letter</label>
                <input type="file" name="cover_letter" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Others</label>
                <input type="file" name="others" class="form-control">
                <label>Offer</label>
                <input type="file" name="offer" class="form-control">
                <label>Contracts</label>
                <input type="file" name="contracts" class="form-control">
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
            </div>
            <div class="col-md-6">
                <label>Candidate Owner</label>
                <input type="text" name="owner" class="form-control" value="Stacey Boyer">
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-4">Save Candidate</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
