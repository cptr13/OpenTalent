<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/header.php';

// Optional prefills from query string
$to_email     = $_GET['to']           ?? '';
$to_name      = $_GET['name']         ?? '';
$related_type = $_GET['related_type'] ?? 'none';   // candidate|client|job|contact|none
$related_id   = (int)($_GET['related_id'] ?? 0);
$return_to    = $_GET['return_to']    ?? '';

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// SMTP status + current config
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$status  = ot_mailer_status();           // ['ok'=>bool,'reason'=>string|null]
$cfg     = ot_get_email_config();        // may be empty array
$fromEmail = $cfg['from_email'] ?? '';
$fromName  = $cfg['from_name']  ?? '';
$host      = $cfg['smtp_host']  ?? '';
$port      = $cfg['smtp_port']  ?? '';
$enc       = $cfg['encryption'] ?? '';
$encLabel  = ($enc === 'starttls' ? 'STARTTLS' : ($enc === 'smtps' ? 'SMTPS' : 'None'));
?>
<div class="container my-4">
  <?php if (!$status['ok']): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-start">
      <div>
        <strong>SMTP not ready.</strong>
        <div class="mt-1">
          <?= h($status['reason'] ?? 'Email is not configured.') ?>
        </div>
        <div class="small text-muted mt-2">
          Emails will not send until SMTP is configured.
        </div>
      </div>
      <div>
        <?php if ($isAdmin): ?>
          <a class="btn btn-sm btn-primary" href="installer_smtp.php">Configure SMTP</a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      <div><strong>From:</strong> <?= h(($fromName ? $fromName . ' ' : '') . "<{$fromEmail}>") ?></div>
      <div class="small mt-1">
        <strong>Transport:</strong> <?= h($host) ?>:<?= h((string)$port) ?> &middot; <strong>Encryption:</strong> <?= h($encLabel) ?>
      </div>
    </div>
  <?php endif; ?>

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
          <div class="form-text">Max size: 10&nbsp;MB</div>
        </div>

        <?php if ($isAdmin): ?>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="__debug" name="__debug" value="1">
            <label class="form-check-label" for="__debug">
              Enable SMTP debug (admin-only)
            </label>
          </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
          <button class="btn btn-primary" type="submit" <?= !$status['ok'] ? 'disabled title="SMTP not configured"' : '' ?>>Send</button>
          <?php if (!$status['ok'] && $isAdmin): ?>
            <a class="btn btn-outline-primary" href="installer_smtp.php">Fix SMTP</a>
          <?php endif; ?>
          <a class="btn btn-outline-secondary" href="<?= $return_to ? h($return_to) : 'javascript:history.back()' ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
