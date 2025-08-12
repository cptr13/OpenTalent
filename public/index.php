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
