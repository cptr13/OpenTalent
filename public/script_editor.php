<?php
// public/script_editor.php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM scripts WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        echo '<div class="container my-4"><div class="alert alert-danger">Script not found.</div></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$channels = ['phone','email','linkedin','voicemail','sms','other'];
$contexts = ['sales','recruiting','general'];
$types    = ['script','rebuttal','template'];

$currentContext = $item['context'] ?? 'sales';
$currentChannel = $item['channel'] ?? 'phone';
$currentType    = $item['type'] ?? 'script';
$currentStage   = isset($item['stage']) ? (string)$item['stage'] : '';
$isActive       = isset($item['is_active']) ? (int)$item['is_active'] : 1;
?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= $id ? 'Edit Script' : 'New Script' ?></h3>
    <div class="d-flex gap-2">
      <a href="scripts.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
  </div>

  <form action="save_script.php" method="post" class="card card-body">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required value="<?= h($item['title'] ?? '') ?>">
      </div>

      <div class="col-md-3">
        <label class="form-label">Context</label>
        <select name="context" class="form-select">
          <?php foreach ($contexts as $c): ?>
            <option value="<?= h($c) ?>" <?= $currentContext === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Channel <span class="text-danger">*</span></label>
        <select name="channel" class="form-select" required id="channelSelect">
          <?php foreach ($channels as $c): ?>
            <option value="<?= h($c) ?>" <?= $currentChannel === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6 email-only" style="display:none;">
        <label class="form-label">Email Subject (when Channel = Email)</label>
        <input type="text" name="subject" class="form-control" value="<?= h($item['subject'] ?? '') ?>" placeholder="e.g., Quick help with {{company}}’s ops hiring">
      </div>

      <div class="col-md-2">
        <label class="form-label">Stage</label>
        <select name="stage" class="form-select">
          <option value="">Any</option>
          <?php for ($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>" <?= $currentStage === (string)$i ? 'selected' : '' ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <?php foreach ($types as $t): ?>
            <option value="<?= h($t) ?>" <?= $currentType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" value="<?= h($item['category'] ?? '') ?>" placeholder="Intro, Discovery, Objection, Closing...">
      </div>

      <div class="col-md-6">
        <label class="form-label">Tags</label>
        <input type="text" name="tags" class="form-control" value="<?= h($item['tags'] ?? '') ?>" placeholder="comma,separated,tags">
      </div>

      <div class="col-12">
        <label class="form-label">Content <span class="text-danger">*</span></label>
        <textarea name="content" class="form-control" rows="12" required><?= h($item['content'] ?? '') ?></textarea>
        <div class="form-text">
          Placeholders you can use:
          <code>{{first_name}}</code>,
          <code>{{last_name}}</code>,
          <code>{{full_name}}</code>,
          <code>{{company_name}}</code>,
          <code>{{job_title}}</code>,
          <code>{{user_name}}</code>,
          <code>{{user_email}}</code>,
          <code>{{user_phone}}</code>,
          <code>{{today}}</code>
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-select">
          <option value="1" <?= $isActive === 1 ? 'selected' : '' ?>>Active (visible)</option>
          <option value="0" <?= $isActive === 0 ? 'selected' : '' ?>>Retired (hidden)</option>
        </select>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button class="btn btn-primary" type="submit">Save</button>
      <a href="scripts.php" class="btn btn-outline-secondary" type="button">Cancel</a>
    </div>
  </form>
</div>

<script>
function toggleEmailSubject() {
  const sel = document.getElementById('channelSelect');
  const blocks = document.querySelectorAll('.email-only');
  const show = sel && sel.value === 'email';
  blocks.forEach(b => b.style.display = show ? '' : 'none');
}

// Initialize on load and on change
document.addEventListener('DOMContentLoaded', toggleEmailSubject);
document.getElementById('channelSelect')?.addEventListener('change', toggleEmailSubject);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
