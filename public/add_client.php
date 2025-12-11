<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Add New Client</h2>

    <form method="POST" action="save_client.php" enctype="multipart/form-data">

        <div class="mb-3">
            <label for="name" class="form-label">Client Name</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="industry" class="form-label">Industry</label>
            <input type="text" name="industry" id="industry" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <input type="text" name="location" id="location" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="account_manager" class="form-label">Account Manager</label>
            <input type="text" name="account_manager" id="account_manager" class="form-control">
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" name="phone" id="phone" class="form-control">
        </div>

        <div class="mb-3">
            <label for="website" class="form-label">Website</label>
            <input type="text" name="website" id="website" class="form-control">
        </div>

        <!-- NEW: LinkedIn URL -->
        <div class="mb-3">
            <label for="linkedin" class="form-label">LinkedIn URL</label>
            <input type="text" name="linkedin" id="linkedin" class="form-control">
        </div>

        <!-- NEW: Company Size -->
        <div class="mb-3">
            <label for="company_size" class="form-label">Company Size</label>
            <select name="company_size" id="company_size" class="form-select">
                <option value="">-- Select Company Size --</option>
                <option value="Myself Only">Myself Only</option>
                <option value="2–10 employees">2–10 employees</option>
                <option value="11–50 employees">11–50 employees</option>
                <option value="51–200 employees">51–200 employees</option>
                <option value="201–500 employees">201–500 employees</option>
                <option value="501–1,000 employees">501–1,000 employees</option>
                <option value="1,001–5,000 employees">1,001–5,000 employees</option>
                <option value="5,001–10,000 employees">5,001–10,000 employees</option>
                <option value="10,001+ employees">10,001+ employees</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="about" class="form-label">About / Notes</label>
            <textarea name="about" id="about" class="form-control" rows="4"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Client</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
