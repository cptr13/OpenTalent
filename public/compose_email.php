<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

// Optional prefills from query string
$to_email     = $_GET['to']           ?? '';
$to_name      = $_GET['name']         ?? '';
$related_type = $_GET['related_type'] ?? 'none';   // candidate|client|job|contact|none
$related_id   = (int)($_GET['related_id'] ?? 0);
$return_to    = $_GET['return_to']    ?? '';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<div class="container my-4">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Compose Email</span>
    </div>
    <div class="card-body">
      <form action="send_email.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="related_type" value="<?= h($related_type) ?>">
        <input type="hidden" name="related_id" value="<?= (int)$related_id ?>">
        <input type="hidden" name="return_to" value="<?= h($return_to) ?>">

        <div class="mb-3">
          <label class="form-label">To</label>
          <input type="email" name="to_email" class="form-control" value="<?= h($to_email) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">To Name (optional)</label>
          <input type="text" name="to_name" class="form-control" value="<?= h($to_name) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Subject</label>
          <input type="text" name="subject" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Message (HTML allowed)</label>
          <textarea name="body_html" class="form-control" rows="10" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Attachment (optional)</label>
          <input type="file" name="attachment" class="form-control">
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit">Send</button>
          <a class="btn btn-outline-secondary" href="<?= $return_to ? h($return_to) : 'javascript:history.back()' ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
