<?php
// Centralized upload config + helpers

// Base uploads path (outside web root)
/** @var string $UPLOAD_BASE */
$UPLOAD_BASE = realpath(__DIR__ . '/../uploads');
if ($UPLOAD_BASE === false) {
    $UPLOAD_BASE = __DIR__ . '/../uploads';
}
if (!is_dir($UPLOAD_BASE)) {
    mkdir($UPLOAD_BASE, 0755, true);
}

/**
 * Map of logical buckets -> subdirectories under /uploads
 * Add more as you introduce new attachment types
 */
$UPLOAD_BUCKETS = [
    'resumes'           => 'resumes',            // candidate resumes (raw or parsed)
    'formatted_resumes' => 'formatted_resumes',  // nicely formatted resumes
    'cover_letters'     => 'cover_letters',
    'contracts'         => 'contracts',
    'attachments'       => 'attachments',        // misc extras
    // client-side buckets we’ll wire next:
    'client_docs'       => 'client_docs',
];

/** Allowed extensions by bucket */
$ALLOWED_EXTS = [
    'resumes'           => ['pdf','docx','doc','txt','rtf','odt'],
    'formatted_resumes' => ['pdf','docx'],
    'cover_letters'     => ['pdf','docx','txt'],
    'contracts'         => ['pdf','docx'],
    'attachments'       => ['pdf','docx','doc','txt','rtf','odt','xlsx','xls','csv','png','jpg','jpeg'],
    'client_docs'       => ['pdf','docx','xlsx','xls','csv','png','jpg','jpeg'],
];

/** Max size per bucket (bytes) */
$MAX_BYTES = [
    'resumes'           => 5 * 1024 * 1024,   // 5MB
    'formatted_resumes' => 10 * 1024 * 1024,  // 10MB
    'cover_letters'     => 5 * 1024 * 1024,
    'contracts'         => 20 * 1024 * 1024,  // 20MB
    'attachments'       => 20 * 1024 * 1024,
    'client_docs'       => 20 * 1024 * 1024,
];

/** Ensure a directory exists (mkdir -p) and return its absolute path */
function ensure_upload_dir(string $base, string $subdir): string {
    $path = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($subdir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

/** Make a safe filename and add a timestamp prefix */
function make_safe_filename(string $original): string {
    $name = basename($original);
    $name = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $name);
    return time() . '_' . $name;
}

/**
 * Store an uploaded file from $_FILES[$field] into a bucket.
 * Returns the stored filename (string) on success, or null on failure.
 * Sets $error_msg (by reference) with a human-readable reason on failure.
 */
function store_upload(string $field, string $bucket, ?string &$error_msg = null): ?string {
    global $UPLOAD_BASE, $UPLOAD_BUCKETS, $ALLOWED_EXTS, $MAX_BYTES;

    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'No file uploaded or upload error.';
        return null;
    }

    if (!isset($UPLOAD_BUCKETS[$bucket])) {
        $error_msg = 'Invalid upload bucket.';
        return null;
    }

    $orig = $_FILES[$field]['name'] ?? '';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = $ALLOWED_EXTS[$bucket] ?? [];
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        $error_msg = 'Unsupported file type.';
        return null;
    }

    $max = $MAX_BYTES[$bucket] ?? (5 * 1024 * 1024);
    if (($_FILES[$field]['size'] ?? 0) > $max) {
        $error_msg = 'File exceeds maximum allowed size.';
        return null;
    }

    $targetDir = ensure_upload_dir($UPLOAD_BASE, $UPLOAD_BUCKETS[$bucket]);
    $safe = make_safe_filename($orig);
    $dest = $targetDir . $safe;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        $error_msg = 'Failed to save uploaded file.';
        return null;
    }

    // Success — return just the filename we’ll store in DB
    return $safe;
}

/**
 * Resolve a stored filename to an absolute path for a given bucket.
 * Returns absolute path or null if missing.
 */
function resolve_upload_path(string $bucket, string $filename): ?string {
    global $UPLOAD_BASE, $UPLOAD_BUCKETS;
    if (!isset($UPLOAD_BUCKETS[$bucket]) || $filename === '') return null;
    $dir = ensure_upload_dir($UPLOAD_BASE, $UPLOAD_BUCKETS[$bucket]);
    $path = realpath($dir . $filename);
    if ($path && strpos($path, realpath($dir)) === 0 && is_file($path)) {
        return $path;
    }
    return null;
}
