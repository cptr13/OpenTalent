<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <h2 class="mb-4">Add New Client</h2>

    <form method="POST" action="save_client.php" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="name" class="form-label">Client Name</label>
            <input type="text" name="name" id="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="account_manager" class="form-label">Account Manager</label>
            <input type="text" name="account_manager" id="account_manager" class="form-control">
        </div>

        <div class="mb-3">
            <label for="contact_number" class="form-label">Contact Number</label>
            <input type="text" name="contact_number" id="contact_number" class="form-control">
        </div>

        <div class="mb-3">
            <label for="fax" class="form-label">Fax</label>
            <input type="text" name="fax" id="fax" class="form-control">
        </div>

        <div class="mb-3">
            <label for="website" class="form-label">Website</label>
            <input type="url" name="website" id="website" class="form-control">
        </div>

        <div class="mb-3">
            <label for="about" class="form-label">About / Notes</label>
            <textarea name="about" id="about" class="form-control" rows="4"></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Save Client</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
