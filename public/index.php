<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/require_login.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

/** Counts for tiles */
$candidateCount = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$jobCount       = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
$clientCount    = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$contactCount   = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();

/** Pipeline Activity data */
$statusBuckets = [
    'Screening' => [
        'New',
        'Associated to Job',
        'Attempted to Contact',
        'Contacted',
        'Screening / Conversation',
        'No-Show',
    ],
    'Interview' => [
        'Interview to be Scheduled',
        'Interview Scheduled',
        'Waiting on Client Feedback',
        'Second Interview to be Scheduled',
        'Second Interview Scheduled',
        'Submitted to Client',
        'Approved by Client',
    ],
    'Offer' => [
        'To be Offered',
        'Offer Made',
        'Offer Accepted',
        'Offer Declined',
        'Offer Withdrawn',
    ],
    'Hired' => ['Hired'],
    'Status Change / Other' => [
        'On Hold',
        'Position Closed',
        'Contact in Future',
    ],
    'Rejected' => [
        'Rejected',
        'Rejected – By Client',
        'Rejected – For Interview',
        'Rejected – Hirable',
        'Unqualified',
        'Not Interested',
    ],
    'Candidate Action / Limbo' => [
        'Ghosted',
        'Paused by Candidate',
        'Withdrawn by Candidate',
    ],
];

$jobsWithAssociations = $pdo->query("SELECT j.id, j.title FROM jobs j
    JOIN associations a ON j.id = a.job_id
    GROUP BY j.id
    ORDER BY j.title ASC")->fetchAll(PDO::FETCH_ASSOC);

$assocStatuses = $pdo->query("SELECT job_id, status FROM associations")->fetchAll(PDO::FETCH_ASSOC);

$statusCounts = [];
foreach ($assocStatuses as $row) {
    $jobId  = $row['job_id'];
    $status = $row['status'];
    if (!isset($statusCounts[$jobId][$status])) {
        $statusCounts[$jobId][$status] = 0;
    }
    $statusCounts[$jobId][$status]++;
}

/** ─────────────────────────────────────────────────────────────
 *  Upcoming Outreach (from contacts table) + Overdue inclusion
 *  ───────────────────────────────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$scope = (isset($_GET['scope']) && $_GET['scope'] === 'agency') ? 'agency' : 'my';
$range = $_GET['range'] ?? 'week';
if (!in_array($range, ['today','week','next'], true)) {
    $range = 'week';
}

$userId      = $_SESSION['user_id']   ?? null;
$ownerName   = $_SESSION['username']  ?? ($_SESSION['user_name'] ?? null);
$ownerIdStr  = $userId !== null ? (string)$userId : null;

$tz   = new DateTimeZone('Asia/Manila');
$now  = new DateTimeImmutable('now', $tz);
$today = $now->format('Y-m-d');

$weekStart = (new DateTimeImmutable('monday this week', $tz))->format('Y-m-d');
$weekEnd   = (new DateTimeImmutable('sunday this week', $tz))->format('Y-m-d');
$nextStart = (new DateTimeImmutable('monday next week', $tz))->format('Y-m-d');
$nextEnd   = (new DateTimeImmutable('sunday next week', $tz))->format('Y-m-d');

/** Date window:
 *  Always include overdue (follow_up_date < today).
 *  PLUS the selected window depending on $range.
 */
$params = [':today' => $today];
switch ($range) {
    case 'today':
        $windowSql = '(c.follow_up_date = :startDate)';
        $params[':startDate'] = $today;
        break;
    case 'week':
        $windowSql = '(c.follow_up_date BETWEEN :startDate AND :endDate)';
        $params[':startDate'] = $weekStart;
        $params[':endDate']   = $weekEnd;
        break;
    case 'next':
        $windowSql = '(c.follow_up_date BETWEEN :startDate AND :endDate)';
        $params[':startDate'] = $nextStart;
        $params[':endDate']   = $nextEnd;
        break;
}
$dateWhere = '((c.follow_up_date < :today) OR ' . $windowSql . ')';

/** Scope filter:
 *  - Agency: no filter
 *  - My: assigned to me (by name or id-string) OR unassigned (NULL/empty)
 */
$scopeSql = '';
$showUnassignedNote = false;
if ($scope !== 'agency') {
    $ownerConds = ["c.contact_owner IS NULL", "c.contact_owner = ''"]; // include unassigned
    $showUnassignedNote = true;
    if ($ownerName) {
        $ownerConds[] = 'c.contact_owner = :owner_name';
        $params[':owner_name'] = $ownerName;
    }
    if ($ownerIdStr !== null) {
        $ownerConds[] = 'c.contact_owner = :owner_id_str';
        $params[':owner_id_str'] = $ownerIdStr;
    }
    $scopeSql = ' AND (' . implode(' OR ', $ownerConds) . ')';
}

/** Query:
 *  - Always includes overdue
 *  - Orders with overdue first, then by date asc, stage asc, name asc
 */
$outreachSql = "
    SELECT
        c.id AS contact_id,
        CONCAT_WS(' ', c.first_name, c.last_name) AS contact_name,
        c.client_id,
        c.outreach_stage,
        c.outreach_status,
        c.follow_up_date,
        cl.name AS client_name
    FROM contacts c
    LEFT JOIN clients cl ON cl.id = c.client_id
    WHERE {$dateWhere}
          {$scopeSql}
    ORDER BY (c.follow_up_date < :today) DESC,
             c.follow_up_date ASC,
             c.outreach_stage ASC,
             contact_name ASC
";

$stm = $pdo->prepare($outreachSql);
$stm->execute($params);
$outreachRows = $stm->fetchAll(PDO::FETCH_ASSOC);

function niceDate(?string $ymd): string {
    if (!$ymd) return '';
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    return $dt ? $dt->format('D, M j') : $ymd;
}

function dateBadgeClass(string $dateYmd, string $todayYmd): string {
    if ($dateYmd <  $todayYmd) return 'badge bg-danger-subtle text-danger-emphasis border border-danger-subtle';   // overdue
    if ($dateYmd === $todayYmd) return 'badge bg-success-subtle text-success-emphasis border border-success-subtle'; // today
    return 'badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle';                        // upcoming
}

function buildToggleUrl(string $key, string $val): string {
    $query = $_GET;
    $query[$key] = $val;
    $path = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?');
    return $path . '?' . http_build_query($query);
}

function isActive(string $current, string $target): string {
    return $current === $target ? 'active' : '';
}
?>

<style>
  /* Drag & drop polish */
  .dash-section { user-select: none; }
  .dash-draggable { cursor: move; }
  .dash-drop-target { outline: 2px dashed var(--bs-secondary); outline-offset: 4px; }
  .dash-handle { font-weight: 700; font-size: 1.1rem; line-height: 1; cursor: move; }
</style>

<div class="container mt-4">
  <h2 class="mb-2">Dashboard</h2>

  <!-- Dashboard sections wrapper (for reordering) -->
  <div id="dashboard-sections">

    <!-- KPI / Quota Tracker -->
    <section class="dash-section" data-key="kpi">
      <div class="card shadow mb-4">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">KPI / Quota Tracker</h5>
          <span class="dash-handle dash-draggable" draggable="true" title="Drag to reorder">↕</span>
        </div>
        <div class="card-body">
          <?php require_once __DIR__ . '/../includes/kpi_card.php'; ?>
        </div>
      </div>
    </section>

    <!-- Upcoming Outreach (contacts-based, includes Overdue) -->
    <section class="dash-section" data-key="outreach">
      <div class="card shadow mb-4">
        <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0">Upcoming Outreach</h5>
          </div>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Scope toggle -->
            <div class="btn-group" role="group" aria-label="Scope">
              <a class="btn btn-sm btn-outline-secondary <?= isActive($scope, 'my'); ?>" href="<?= h(buildToggleUrl('scope','my')); ?>">My</a>
              <a class="btn btn-sm btn-outline-secondary <?= isActive($scope, 'agency'); ?>" href="<?= h(buildToggleUrl('scope','agency')); ?>">Agency</a>
            </div>
            <!-- Range toggle -->
            <div class="btn-group" role="group" aria-label="Range">
              <a class="btn btn-sm btn-outline-secondary <?= isActive($range, 'today'); ?>" href="<?= h(buildToggleUrl('range','today')); ?>">Today</a>
              <a class="btn btn-sm btn-outline-secondary <?= isActive($range, 'week'); ?>" href="<?= h(buildToggleUrl('range','week')); ?>">This Week</a>
              <a class="btn btn-sm btn-outline-secondary <?= isActive($range, 'next'); ?>" href="<?= h(buildToggleUrl('range','next')); ?>">Next Week</a>
            </div>
            <span class="dash-handle dash-draggable ms-2" draggable="true" title="Drag to reorder">↕</span>
          </div>
        </div>

        <?php if ($scope !== 'agency' && $showUnassignedNote): ?>
          <div class="px-3 pt-3">
            <div class="alert alert-info py-2 px-3 mb-0">
              <small>“My” view includes <strong>overdue</strong> and <strong>unassigned</strong> contacts so nothing slips. Set <code>contact_owner</code> to narrow your list.</small>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$outreachRows): ?>
          <div class="card-body">
            <div class="text-center text-muted py-4">
              <div class="mb-2">Nothing due 🎉</div>
              <small>No overdue items and nothing in the selected window.</small>
            </div>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 24%;">Contact</th>
                  <th style="width: 30%;">Company</th>
                  <th style="width: 28%;">Step</th>
                  <th style="width: 18%;">Next Touch</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($outreachRows as $row):
                  $contactUrl = 'view_contact.php?id=' . urlencode((string)$row['contact_id']);
                  $clientUrl  = $row['client_id'] ? 'view_client.php?id=' . urlencode((string)$row['client_id']) : null;

                  $stepNum   = is_null($row['outreach_stage']) ? null : (int)$row['outreach_stage'];
                  $stepLabel = $stepNum ? ('Step #' . $stepNum) : '—';
                  if (!empty($row['outreach_status'])) {
                      $stepLabel .= ' (' . $row['outreach_status'] . ')';
                  }

                  $nice       = niceDate($row['follow_up_date']);
                  $badgeClass = dateBadgeClass($row['follow_up_date'], $today);
              ?>
                <tr>
                  <td>
                    <a href="<?= h($contactUrl); ?>" class="text-decoration-none">
                      <?= h($row['contact_name'] ?: 'Unnamed Contact'); ?>
                    </a>
                  </td>
                  <td>
                    <?php if ($clientUrl && $row['client_name']): ?>
                      <a href="<?= h($clientUrl); ?>" class="text-decoration-none">
                        <?= h($row['client_name']); ?>
                      </a>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="fw-semibold"><?= h($stepLabel); ?></span>
                  </td>
                  <td>
                    <span class="<?= h($badgeClass); ?> px-2 py-1 rounded-pill"><?= h($nice); ?></span>
                    <div class="small text-muted"><?= h($row['follow_up_date']); ?></div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- At a Glance (tiles inside a card) -->
    <section class="dash-section" data-key="stats">
      <div class="card shadow mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <h5 class="mb-0">At a Glance</h5>
          <span class="dash-handle dash-draggable" draggable="true" title="Drag to reorder">↕</span>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6 col-lg-3">
              <a href="candidates.php" class="text-decoration-none">
                <div class="card text-bg-primary text-center shadow rounded-4">
                  <div class="card-body">
                    <h4 class="card-title"><?= $candidateCount ?></h4>
                    <p class="card-text">Candidates</p>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-md-6 col-lg-3">
              <a href="jobs.php" class="text-decoration-none">
                <div class="card text-bg-success text-center shadow rounded-4">
                  <div class="card-body">
                    <h4 class="card-title"><?= $jobCount ?></h4>
                    <p class="card-text">Jobs</p>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-md-6 col-lg-3">
              <a href="clients.php" class="text-decoration-none">
                <div class="card text-bg-warning text-center shadow rounded-4">
                  <div class="card-body">
                    <h4 class="card-title"><?= $clientCount ?></h4>
                    <p class="card-text">Clients</p>
                  </div>
                </div>
              </a>
            </div>

            <div class="col-md-6 col-lg-3">
              <a href="contacts.php" class="text-decoration-none">
                <div class="card text-bg-dark text-center shadow rounded-4">
                  <div class="card-body">
                    <h4 class="card-title"><?= $contactCount ?></h4>
                    <p class="card-text">Contacts</p>
                  </div>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pipeline Activity -->
    <section class="dash-section" data-key="pipeline">
      <div class="card shadow mb-5">
        <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Pipeline Activity</h5>
          <span class="dash-handle dash-draggable" draggable="true" title="Drag to reorder">↕</span>
        </div>
        <div class="card-body">
          <?php if (count($jobsWithAssociations) === 0): ?>
            <p class="text-muted">No associations found.</p>
          <?php else: ?>
            <?php foreach ($jobsWithAssociations as $job): ?>
              <div class="mb-4">
                <h6>
                  <a href="view_job.php?id=<?= $job['id'] ?>" class="text-decoration-none">
                    <?= htmlspecialchars($job['title']) ?>
                  </a>
                </h6>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($statusBuckets as $bucket => $statuses): ?>
                    <?php
                      $count = 0;
                      foreach ($statuses as $s) {
                        if (!empty($statusCounts[$job['id']][$s])) {
                          $count += $statusCounts[$job['id']][$s];
                        }
                      }
                    ?>
                    <div class="border rounded p-2 bg-light text-center" style="min-width: 120px;">
                      <strong><?= $bucket ?></strong><br>
                      <span class="badge bg-primary"><?= $count ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <hr>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

  </div> <!-- /#dashboard-sections -->
</div>

<script>
/** Simple drag & drop reordering for sections, persisted to localStorage */
(function(){
  const WRAP_ID = 'dashboard-sections';
  const KEY = 'dashOrder';
  const wrap = document.getElementById(WRAP_ID);

  if (!wrap) return;

  // Restore saved order
  const saved = localStorage.getItem(KEY);
  if (saved) {
    try {
      const keys = JSON.parse(saved);
      const current = Array.from(wrap.children);
      keys.forEach(k => {
        const el = wrap.querySelector(`.dash-section[data-key="${k}"]`);
        if (el) wrap.appendChild(el);
      });
      // Append any new sections not in saved list
      current.forEach(el => {
        if (!keys.includes(el.getAttribute('data-key'))) wrap.appendChild(el);
      });
    } catch(e) {}
  }

  // Enable dragging by the handle
  let draggingEl = null;

  wrap.addEventListener('dragstart', (e) => {
    const handle = e.target.closest('.dash-draggable');
    if (!handle) { e.preventDefault(); return; }
    const section = handle.closest('.dash-section');
    if (!section) { e.preventDefault(); return; }
    draggingEl = section;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', section.getAttribute('data-key'));
    setTimeout(() => section.classList.add('opacity-50'));
  });

  wrap.addEventListener('dragover', (e) => {
    if (!draggingEl) return;
    e.preventDefault();
    const targetSection = e.target.closest('.dash-section');
    if (!targetSection || targetSection === draggingEl) return;

    // Visual hint
    targetSection.classList.add('dash-drop-target');

    // Insert before/after based on mouse position
    const rect = targetSection.getBoundingClientRect();
    const before = (e.clientY - rect.top) < (rect.height / 2);
    wrap.insertBefore(draggingEl, before ? targetSection : targetSection.nextSibling);
  });

  wrap.addEventListener('dragleave', (e) => {
    const sec = e.target.closest('.dash-section');
    if (sec) sec.classList.remove('dash-drop-target');
  });

  wrap.addEventListener('drop', (e) => {
    e.preventDefault();
    const secs = wrap.querySelectorAll('.dash-section');
    secs.forEach(s => s.classList.remove('dash-drop-target'));
  });

  wrap.addEventListener('dragend', () => {
    if (!draggingEl) return;
    draggingEl.classList.remove('opacity-50');
    draggingEl = null;

    // Persist order
    const order = Array.from(wrap.querySelectorAll('.dash-section'))
      .map(el => el.getAttribute('data-key'));
    localStorage.setItem('dashOrder', JSON.stringify(order));
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
