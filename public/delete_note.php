<?php
require_once __DIR__ . '/../config/database.php';

// Accept one of the following: candidate_id, client_id, contact_id, job_id
$note_id = $_GET['id'] ?? null;
$redirect_id = null;
$redirect_url = null;

if ($note_id) {
    if (isset($_GET['candidate_id'])) {
        $redirect_id = (int) $_GET['candidate_id'];
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND candidate_id = ?");
        $stmt->execute([$note_id, $redirect_id]);
        $redirect_url = "view_candidate.php?id=$redirect_id";
    } elseif (isset($_GET['client_id'])) {
        $redirect_id = (int) $_GET['client_id'];
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND client_id = ?");
        $stmt->execute([$note_id, $redirect_id]);
        $redirect_url = "view_client.php?id=$redirect_id";
    } elseif (isset($_GET['contact_id'])) {
        $redirect_id = (int) $_GET['contact_id'];
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND contact_id = ?");
        $stmt->execute([$note_id, $redirect_id]);
        $redirect_url = "view_contact.php?id=$redirect_id";
    } elseif (isset($_GET['job_id'])) {
        $redirect_id = (int) $_GET['job_id'];
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND job_id = ?");
        $stmt->execute([$note_id, $redirect_id]);
        $redirect_url = "view_job.php?id=$redirect_id";
    }
}

if ($redirect_url) {
    header("Location: $redirect_url");
    exit;
} else {
    echo "Invalid request.";
}
