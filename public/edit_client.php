<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo "<div class='alert alert-danger'>No valid client ID provided.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    echo "<div class='alert alert-danger'>Client not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'name', 'contact_number', 'account_manager', 'parent_client', 'fax', 'website',
        'industry', 'about', 'source',
        'billing_street', 'billing_city', 'billing_state', 'billing_code', 'billing_country',
        'shipping_street', 'shipping_city', 'shipping_state', 'shipping_code', 'shipping_country'
    ];

    $update = [];
    $params = [];

    foreach ($fields as $field) {
        $value = trim($_POST[$field] ?? '');
        $update[] = "$field = ?";
        $params[] = $value;
    }

    if (!empty($_FILES['client_contract']['name'])) {
        $uploads_dir = __DIR__ . '/uploads/';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $contract_filename = time() . "_" . basename($_FILES['client_contract']['name']);
        move_uploaded_file($_FILES['client_contract']['tmp_name'], $uploads_dir . $contract_filename);
        $update[] = "contract_filename = ?";
        $params[] = $contract_filename;
    }

    if (!empty($_FILES['other_attachment']['name'])) {
        $uploads_dir = __DIR__ . '/uploads/';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $other_filename = time() . "_" . basename($_FILES['other_attachment']['name']);
        move_uploaded_file($_FILES['other_attachment']['tmp_name'], $uploads_dir . $other_filename);
        $update[] = "other_filename = ?";
        $params[] = $other_filename;
    }

    $params[] = $id;

    $sql = "UPDATE clients SET " . implode(', ', $update) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    header("Location: view_client.php?id=" . $id);
    exit;
}
?>

<h2 class="mb-4">Edit Client</h2>

<form method="post" enctype="multipart/form-data">
    <div class="form-row">
        <div class="form-group col-md-6"><label>Client Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($client['name']) ?>" required></div>
        <div class="form-group col-md-6"><label>Parent Client</label><input type="text" name="parent_client" class="form-control" value="<?= htmlspecialchars($client['parent_client']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($client['contact_number']) ?>"></div>
        <div class="form-group col-md-6"><label>Fax</label><input type="text" name="fax" class="form-control" value="<?= htmlspecialchars($client['fax']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Account Manager</label><input type="text" name="account_manager" class="form-control" value="<?= htmlspecialchars($client['account_manager']) ?>"></div>
        <div class="form-group col-md-6"><label>Website</label><input type="text" name="website" class="form-control" value="<?= htmlspecialchars($client['website']) ?>"></div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6"><label>Industry</label><input type="text" name="industry" class="form-control" value="<?= htmlspecialchars($client['industry']) ?>"></div>
        <div class="form-group col-md-6"><label>Source</label><input type="text" name="source" class="form-control" value="<?= htmlspecialchars($client['source']) ?>"></div>
    </div>

    <div class="form-group"><label>About</label><textarea name="about" class="form-control" rows="3"><?= htmlspecialchars($client['about']) ?></textarea></div>

    <h5 class="mt-4">Address Information</h5>
    <div class="form-row">
        <div class="form-group col-md-6"><label>Billing Street</label><input type="text" name="billing_street" class="form-control" value="<?= htmlspecialchars($client['billing_street']) ?>"></div>
        <div class="form-group col-md-6"><label>Shipping Street</label><input type="text" name="shipping_street" class="form-control" value="<?= htmlspecialchars($client['shipping_street']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-3"><label>Billing City</label><input type="text" name="billing_city" class="form-control" value="<?= htmlspecialchars($client['billing_city']) ?>"></div>
        <div class="form-group col-md-3"><label>Billing State</label><input type="text" name="billing_state" class="form-control" value="<?= htmlspecialchars($client['billing_state']) ?>"></div>
        <div class="form-group col-md-3"><label>Billing Code</label><input type="text" name="billing_code" class="form-control" value="<?= htmlspecialchars($client['billing_code']) ?>"></div>
        <div class="form-group col-md-3"><label>Billing Country</label><input type="text" name="billing_country" class="form-control" value="<?= htmlspecialchars($client['billing_country']) ?>"></div>
    </div>
    <div class="form-row">
        <div class="form-group col-md-3"><label>Shipping City</label><input type="text" name="shipping_city" class="form-control" value="<?= htmlspecialchars($client['shipping_city']) ?>"></div>
        <div class="form-group col-md-3"><label>Shipping State</label><input type="text" name="shipping_state" class="form-control" value="<?= htmlspecialchars($client['shipping_state']) ?>"></div>
        <div class="form-group col-md-3"><label>Shipping Code</label><input type="text" name="shipping_code" class="form-control" value="<?= htmlspecialchars($client['shipping_code']) ?>"></div>
        <div class="form-group col-md-3"><label>Shipping Country</label><input type="text" name="shipping_country" class="form-control" value="<?= htmlspecialchars($client['shipping_country']) ?>"></div>
    </div>

    <h5 class="mt-4">Attachment Information</h5>
    <div class="form-group">
        <label>Client Contract</label>
        <?php if (!empty($client['contract_filename'])): ?>
            <p><a href="uploads/<?= htmlspecialchars($client['contract_filename']) ?>" target="_blank">Download current</a></p>
        <?php endif; ?>
        <input type="file" name="client_contract" class="form-control-file">
    </div>

    <div class="form-group">
        <label>Other Attachment</label>
        <?php if (!empty($client['other_filename'])): ?>
            <p><a href="uploads/<?= htmlspecialchars($client['other_filename']) ?>" target="_blank">Download current</a></p>
        <?php endif; ?>
        <input type="file" name="other_attachment" class="form-control-file">
    </div>

    <button type="submit" class="btn btn-primary">Update Client</button>
    <a href="view_client.php?id=<?= $client['id'] ?>" class="btn btn-secondary">Cancel</a>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
