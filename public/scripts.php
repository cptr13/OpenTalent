<?php
// public/scripts.php
require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Filters
$ctx    = $_GET['context'] ?? 'sales';      // sales|recruiting|general|''
$chan   = $_GET['channel'] ?? '';           // phone|email|linkedin|...
$stage  = $_GET['stage']   ?? '';           // '' or 1..12
$show   = $_GET['show']    ?? 'active';     // active|all|retired
$q      = trim($_GET['q'] ?? '');

// Build query
$where  = [];
$params = [];

if ($show === 'active') {
    $where[] = "is_active = 1";
} elseif ($show === 'retired') {
    $where[] = "is_active = 0";
}
if ($ctx !== '') {
    $where[] = "context = :context";
    $params[':context'] = $ctx;
}
if ($chan !== '') {
    $where[] = "channel = :channel";
    $params[':channel'] = $chan;
}
if ($stage !== '') {
    $where[] = "stage = :stage";
    $params[':stage'] = (int)$stage;
}
if ($q !== '') {
    $where[] = "(title LIKE :like OR tags LIKE :like OR content LIKE :like)";
    $params[':like'] = "%{$q}%";
}

$sql = "SELECT id, title, context, channel, subject, stage, category, type, tags, is_active, updated_at
        FROM scripts";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY updated_at DESC LIMIT 500";

$rows = [];
$errorMsg = '';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errorMsg = 'Unable to load scripts. If this is a fresh install, ensure the Scripts table exists in schema.sql.';
}
?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Scripts</h3>
    <div class="d-flex gap-2">
      <a href="script_editor.php" class="btn btn-primary">New Script</a>
      <button
        class="btn btn-outline-secondary"
        onclick="OpenTalentScripts.open({
          context:'<?= htmlspecialchars($ctx, ENT_QUOTES) ?>',
          channel:'<?= htmlspecialchars($chan, ENT_QUOTES) ?>',
          stage:'<?= htmlspecialchars((string)$stage, ENT_QUOTES) ?>',
          query:'<?= htmlspecialchars($q, ENT_QUOTES) ?>'
        })">
        Open Modal
      </button>
    </div>
  </div>

  <form method="get" class="card card-body mb-3">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">Context</label>
        <select name="context" class="form-select">
          <option value="">Any</option>
          <option value="sales" <?= $ctx==='sales'?'selected':''; ?>>Sales</option>
          <option value="recruiting" <?= $ctx==='recruiting'?'selected':''; ?>>Recruiting</option>
          <option value="general" <?= $ctx==='general'?'selected':''; ?>>General</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Channel</label>
        <select name="channel" class="form-select">
          <option value="">All</option>
          <?php
            $channels = ['phone','email','linkedin','voicemail','sms','other'];
            foreach ($channels as $c) {
              $sel = ($chan===$c)?'selected':'';
              echo "<option value=\"$c\" $sel>".ucfirst($c)."</option>";
            }
          ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Stage</label>
        <select name="stage" class="form-select">
          <option value="">Any</option>
          <?php for ($i=1; $i<=12; $i++): ?>
            <option value="<?= $i ?>" <?= ((string)$stage===(string)$i)?'selected':''; ?>><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Show</label>
        <select name="show" class="form-select">
          <option value="active"  <?= $show==='active'?'selected':''; ?>>Active</option>
          <option value="all"     <?= $show==='all'?'selected':''; ?>>All</option>
          <option value="retired" <?= $show==='retired'?'selected':''; ?>>Retired</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Search</label>
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="keywords…">
      </div>
    </div>

    <div class="text-end mt-3">
      <button class="btn btn-primary">Filter</button>
    </div>
  </form>

  <?php if ($errorMsg): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th>Title</th>
              <th>Context</th>
              <th>Channel</th>
              <th>Stage</th>
              <th>Type</th>
              <th>Category</th>
              <th>Updated</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="9" class="text-muted p-4">No scripts found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= htmlspecialchars($r['context']) ?></td>
                <td><?= htmlspecialchars($r['channel']) ?></td>
                <td><?= $r['stage'] ? (int)$r['stage'] : 'Any' ?></td>
                <td><?= htmlspecialchars($r['type']) ?></td>
                <td><?= htmlspecialchars($r['category'] ?? '') ?></td>
                <td><span class="small text-muted"><?= htmlspecialchars($r['updated_at']) ?></span></td>
                <td><?= $r['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Retired</span>' ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="script_editor.php?id=<?= (int)$r['id'] ?>">Edit</a>
                  <?php if ($r['is_active']): ?>
                    <a class="btn btn-sm btn-outline-warning" href="delete_script.php?id=<?= (int)$r['id'] ?>&action=retire">Retire</a>
                  <?php else: ?>
                    <a class="btn btn-sm btn-outline-success" href="delete_script.php?id=<?= (int)$r['id'] ?>&action=activate">Activate</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
// Include the Scripts modal so the "Open Modal" button works on this page
include __DIR__ . '/../includes/modal_scripts.php';

require_once __DIR__ . '/../includes/footer.php';
?>
