<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "<div class='alert alert-danger'>Invalid request method.</div>";
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo "<div class='alert alert-warning'>Missing or invalid client ID. Update cannot proceed.</div>";
    exit;
}

// Helper to trim and convert empty strings to null
$val = function ($key) {
    if (!isset($_POST[$key])) return null;
    $v = trim((string)$_POST[$key]);
    return ($v === '') ? null : $v;
};

$name            = $val('name');            // input named "name"
$industry        = $val('industry');
$url             = $val('website');         // input named 'website', maps to 'url' column
$location        = $val('location');
$phone           = $val('phone');
$account_manager = $val('account_manager');
$status          = $val('status');
$about           = $val('about');
$company_size    = $val('company_size');    // LinkedIn-style company size
$linkedin        = $val('linkedin');        // NEW: LinkedIn URL

// Optional: light normalization for website (keep as-is if you prefer)
/*
if ($url && !preg_match('~^https?://~i', $url)) {
    $url = 'https://' . $url;
}
*/

try {
    $sql = "UPDATE clients SET
                name = :name,
                industry = :industry,
                url = :url,
                location = :location,
                phone = :phone,
                account_manager = :account_manager,
                status = :status,
                about = :about,
                company_size = :company_size,
                linkedin = :linkedin,
                updated_at = NOW()
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name'             => $name,
        ':industry'         => $industry,
        ':url'              => $url,
        ':location'         => $location,
        ':phone'            => $phone,
        ':account_manager'  => $account_manager,
        ':status'           => $status,
        ':about'            => $about,
        ':company_size'     => $company_size,
        ':linkedin'         => $linkedin,
        ':id'               => $id
    ]);

    header("Location: view_client.php?id=" . urlencode((string)$id));
    exit;

} catch (PDOException $e) {
    error_log("Client update failed (id=$id): " . $e->getMessage());
    echo "<div class='alert alert-danger'>An error occurred while updating the client. Please try again later.</div>";
}
