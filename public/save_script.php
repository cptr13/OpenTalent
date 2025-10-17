<?php
// public/save_script.php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';

function post_str($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

$id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title     = post_str('title');
$context   = post_str('context', 'sales');          // sales|recruiting|general
$channel   = post_str('channel', 'phone');          // phone|email|linkedin|voicemail|sms|other
$subject   = post_str('subject');                   // only used if channel=email
$stageRaw  = post_str('stage');                     // '' or 1..12
$type      = post_str('type', 'script');            // script|rebuttal|template
$category  = post_str('category');
$tags      = post_str('tags');
$content   = post_str('content');
$is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

// Basic validation
if ($title === '' || $content === '' || $channel === '') {
    http_response_code(400);
    echo "Missing required fields (Title, Channel, Content).";
    exit;
}

// Normalize values
$stage = ($stageRaw === '' ? null : (int)$stageRaw);
if ($channel !== 'email') {
    // subject is irrelevant; store NULL to keep data clean
    $subject = '';
}
$subject = ($subject === '') ? null : $subject;
$category = ($category === '') ? null : $category;
$tags     = ($tags === '') ? null : $tags;

try {
    if ($id > 0) {
        // Update
        $sql = "UPDATE scripts
                   SET title = :title,
                       context = :context,
                       channel = :channel,
                       subject = :subject,
                       stage = :stage,
                       type = :type,
                       category = :category,
                       tags = :tags,
                       content = :content,
                       is_active = :is_active
                 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title'     => $title,
            ':context'   => $context,
            ':channel'   => $channel,
            ':subject'   => $subject,
            ':stage'     => $stage,
            ':type'      => $type,
            ':category'  => $category,
            ':tags'      => $tags,
            ':content'   => $content,
            ':is_active' => $is_active,
            ':id'        => $id,
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO scripts
                   (title, context, channel, subject, stage, type, category, tags, content, is_active)
                VALUES
                   (:title, :context, :channel, :subject, :stage, :type, :category, :tags, :content, :is_active)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title'     => $title,
            ':context'   => $context,
            ':channel'   => $channel,
            ':subject'   => $subject,
            ':stage'     => $stage,
            ':type'      => $type,
            ':category'  => $category,
            ':tags'      => $tags,
            ':content'   => $content,
            ':is_active' => $is_active,
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    header('Location: scripts.php');
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error saving script.";
    // Optionally log $e->getMessage() to your error log
}
