<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>Invalid contact ID.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch the contact
$stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
$stmt->execute([$id]);
$contact = $stmt->fetch();

if (!$contact) {
    echo "<div class='alert alert-danger'>Contact not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch client list for dropdown
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name ASC")->fetchAll();

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update = $pdo->prepare("
        UPDATE contacts SET
            client_id = :client_id,
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            secondary_email = :secondary_email,
            department = :department,
            job_title = :job_title,
            phone_work = :phone_work,
            phone_mobile = :phone_mobile,
            fax = :fax,
            skype_id = :skype_id,
            twitter = :twitter,
            linkedin = :linkedin,
            address_street = :address_street,
            address_city = :address_city,
            address_state = :address_state,
            address_zip = :address_zip,
            address_country = :address_country,
            alt_street = :alt_street,
            alt_city = :alt_city,
            alt_state = :alt_state,
            alt_zip = :alt_zip,
            alt_country = :alt_country,
            source = :source,
            contact_owner = :contact_owner,
            is_primary_contact = :is_primary_contact,
            description = :description
        WHERE id = :id
    ");

    $update->execute([
        ':client_id' => $_POST['client_id'],
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':email' => $_POST['email'],
        ':secondary_email' => $_POST['secondary_email'],
        ':department' => $_POST['department'],
        ':job_title' => $_POST['job_title'],
        ':phone_work' => $_POST['phone_work'],
        ':phone_mobile' => $_POST['phone_mobile'],
        ':fax' => $_POST['fax'],
        ':skype_id' => $_POST['skype_id'],
        ':twitter' => $_POST['twitter'],
        ':linkedin' => $_POST['linkedin'],
        ':address_street' => $_POST['address_street'],
        ':address_city' => $_POST['address_city'],
        ':address_state' => $_POST['address_state'],
        ':address_zip' => $_POST['address_zip'],
        ':address_country' => $_POST['address_country'],
        ':alt_street' => $_POST['alt_street'],
        ':alt_city' => $_POST['alt_city'],
        ':alt_state' => $_POST['alt_state'],
        ':alt_zip' => $_POST['alt_zip'],
        ':alt_country' => $_POST['alt_country'],
        ':source' => $_POST['source'],
        ':contact_owner' => $_POST['contact_owner'],
        ':is_primary_contact' => isset($_POST['is_primary_contact']) ? 1 : 0,
        ':description' => $_POST['description'],
        ':id' => $id
    ]);

    $success = true;
}
?>

<h2 class="mb-4">Edit Contact</h2>

<?php if ($success): ?>
    <div class="alert alert-success">Contact updated successfully!</div>
<?php endif; ?>

<form method="post">
    <div class="form-group">
        <label>Client</label>
        <select name="client_id" class="form-control" required>
            <option value="">— Select Client —</option>
            <?php foreach ($clients as $client): ?>
                <option value="<?= $client['id'] ?>" <?= $client['id'] == $contact['client_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($client['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>First Name</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($contact['first_name']) ?>"></div>
        <div class="form-group col-md-6"><label>Last Name *</label><input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($contact['last_name']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($contact['email']) ?>"></div>
        <div class="form-group col-md-6"><label>Secondary Email</label><input type="email" name="secondary_email" class="form-control" value="<?= htmlspecialchars($contact['secondary_email']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Department</label><input type="text" name="department" class="form-control" value="<?= htmlspecialchars($contact['department']) ?>"></div>
        <div class="form-group col-md-6"><label>Job Title</label><input type="text" name="job_title" class="form-control" value="<?= htmlspecialchars($contact['job_title']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Work Phone</label><input type="text" name="phone_work" class="form-control" value="<?= htmlspecialchars($contact['phone_work']) ?>"></div>
        <div class="form-group col-md-6"><label>Mobile</label><input type="text" name="phone_mobile" class="form-control" value="<?= htmlspecialchars($contact['phone_mobile']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Fax</label><input type="text" name="fax" class="form-control" value="<?= htmlspecialchars($contact['fax']) ?>"></div>
        <div class="form-group col-md-6"><label>Skype ID</label><input type="text" name="skype_id" class="form-control" value="<?= htmlspecialchars($contact['skype_id']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Twitter</label><input type="text" name="twitter" class="form-control" value="<?= htmlspecialchars($contact['twitter']) ?>"></div>
        <div class="form-group col-md-6"><label>LinkedIn</label><input type="url" name="linkedin" class="form-control" value="<?= htmlspecialchars($contact['linkedin']) ?>"></div>
    </div>

    <h5 class="mt-4">Mailing Address</h5>
    <div class="form-row">
        <div class="form-group col-md-6"><label>Street</label><input type="text" name="address_street" class="form-control" value="<?= htmlspecialchars($contact['address_street']) ?>"></div>
        <div class="form-group col-md-6"><label>City</label><input type="text" name="address_city" class="form-control" value="<?= htmlspecialchars($contact['address_city']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-6"><label>State</label><input type="text" name="address_state" class="form-control" value="<?= htmlspecialchars($contact['address_state']) ?>"></div>
        <div class="form-group col-md-6"><label>Zip</label><input type="text" name="address_zip" class="form-control" value="<?= htmlspecialchars($contact['address_zip']) ?>"></div>
    </div>
    <div class="form-group"><label>Country</label><input type="text" name="address_country" class="form-control" value="<?= htmlspecialchars($contact['address_country']) ?>"></div>

    <h5 class="mt-4">Alternate Address</h5>
    <div class="form-row">
        <div class="form-group col-md-6"><label>Street</label><input type="text" name="alt_street" class="form-control" value="<?= htmlspecialchars($contact['alt_street']) ?>"></div>
        <div class="form-group col-md-6"><label>City</label><input type="text" name="alt_city" class="form-control" value="<?= htmlspecialchars($contact['alt_city']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-6"><label>State</label><input type="text" name="alt_state" class="form-control" value="<?= htmlspecialchars($contact['alt_state']) ?>"></div>
        <div class="form-group col-md-6"><label>Zip</label><input type="text" name="alt_zip" class="form-control" value="<?= htmlspecialchars($contact['alt_zip']) ?>"></div>
    </div>
    <div class="form-group"><label>Country</label><input type="text" name="alt_country" class="form-control" value="<?= htmlspecialchars($contact['alt_country']) ?>"></div>

    <h5 class="mt-4">Additional Details</h5>
    <div class="form-row">
        <div class="form-group col-md-6"><label>Source</label><input type="text" name="source" class="form-control" value="<?= htmlspecialchars($contact['source']) ?>"></div>
        <div class="form-group col-md-6"><label>Contact Owner</label><input type="text" name="contact_owner" class="form-control" value="<?= htmlspecialchars($contact['contact_owner']) ?>"></div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="is_primary_contact" id="primaryContact" <?= $contact['is_primary_contact'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="primaryContact">Is Primary Contact</label>
    </div>

    <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($contact['description']) ?></textarea></div>

    <button type="submit" class="btn btn-primary">Update Contact</button>
    <a href="view_contact.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
