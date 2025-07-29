<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gather and sanitize input
    $id          = $_POST['id'] ?? null;
    $title       = trim($_POST['title'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $status      = trim($_POST['status'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $client_id   = (is_numeric($_POST['client_id'] ?? null)) ? (int)$_POST['client_id'] : null;

    // Validate required fields
    if ($title === '' || $location === '' || $type === '' || $status === '' || !$client_id) {
        echo "<div class='alert alert-danger'>Missing required fields.</div>";
        exit;
    }

    try {
        if ($id) {
            // UPDATE existing job
            $stmt = $pdo->prepare("UPDATE jobs SET title = ?, location = ?, type = ?, status = ?, description = ?, client_id = ? WHERE id = ?");
            $stmt->execute([$title, $location, $type, $status, $description, $client_id, $id]);
            header("Location: view_job.php?id=$id");
            exit;
        } else {
            // INSERT new job
            $stmt = $pdo->prepare("INSERT INTO jobs (title, location, type, status, description, client_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $location, $type, $status, $description, $client_id]);
            $new_id = $pdo->lastInsertId();
            header("Location: view_job.php?id=$new_id");
            exit;
        }

    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error saving job: " . htmlspecialchars($e->getMessage()) . "</div>";
        exit;
    }
} else {
    echo "<div class='alert alert-warning'>Invalid request method.</div>";
    exit;
}
