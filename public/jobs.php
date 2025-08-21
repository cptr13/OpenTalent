<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/list_view.php';
require_once __DIR__ . '/../includes/sortable.php';
require_once __DIR__ . '/../includes/status_badge.php'; // contact_status_badge()

// Default to empty list in case of errors
$jobs = [];
$clientNames = [];
$jobContacts = []; // job_id => ['id' => contact_id, 'name' => contact_name, 'status' => contact_status]

// Config for Jobs list/filters
$config = [
    'table' => 'jobs',
    'default_columns' => ['title', 'location', 'status', 'created_at'], // displayed + sortable
    'column_labels' => [
        'title'      => 'Title',
        'location'   => 'Location',
        'status'     => 'Status',
        'created_at' => 'Created',
        // client_id not displayed in header; used for link mapping below
        // Contact column is rendered from a separate lookup (keeps this step isolated)
    ],
    'filter_types' => [
        'title'      => 'text',
        'location'   => 'text',
        'status'     => 'dropdown',
        'created_at' => 'date_range',
        // You can add 'client_id' => 'dropdown' later if you want client filter
    ],
];

/**
 * Sort controls for header links
 * Keys must match what list_view.php expects in $_GET['sort'].
 * NOTE: "Company" and "Contact" need JOIN-aware sorting in list_view.php to be sortable.
 */
$ALLOWED_COLUMNS = [
    'title'      => 'title',
    'location'   => 'location',
    'status'     => 'status',
    'created_at' => 'created_at',
];
// Defaults: title ASC
$S = ot_get_sort($ALLOWED_COLUMNS, 'title', 'asc');

try {
    // Get filtered/sorted rows + filter form HTML
    list($jobs, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta) = get_list_view_data($pdo, $config);

    // Bulk-lookup client names for the rendered table (preserves your existing UI)
    $clientIds = [];
    $jobIds = [];
    foreach ($jobs as $r) {
        if (!empty($r['client_id'])) {
            $clientIds[] = (int)$r['client_id'];
        }
        if (!empty($r['id'])) {
            $jobIds[] = (int)$r['id'];
        }
    }
    $clientIds = array_values(array_unique(array_filter($clientIds)));
    $jobIds    = array_values(array_unique(array_filter($jobIds)));

    if ($clientIds) {
        $in = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id IN ($in)");
        $stmt->execute($clientIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $clientNames[(int)$row['id']] = $row['name'];
        }
    }

    // Bulk-lookup FIRST associated contact per job (by earliest link), including contact_status
    if ($jobIds) {
        $in = implode(',', array_fill(0, count($jobIds), '?'));
        $sql = "
            SELECT t.job_id,
                   t.contact_id AS id,
                   CONCAT(c.first_name, ' ', c.last_name) AS name,
                   c.contact_status AS status
            FROM (
                SELECT jc.job_id, jc.contact_id,
                       ROW_NUMBER() OVER (PARTITION BY jc.job_id ORDER BY jc.id ASC) AS rn
                FROM job_contacts jc
                WHERE jc.job_id IN ($in)
            ) t
            JOIN contacts c ON c.id = t.contact_id
            WHERE t.rn = 1
        ";
        // MySQL 8+ supports ROW_NUMBER. If using MySQL 5.7, replace with MIN(id) subquery.
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($jobIds);
        } catch (Throwable $e) {
            // Fallback for older MySQL (no window functions)
            $sqlFallback = "
                SELECT jc.job_id,
                       jc.contact_id AS id,
                       CONCAT(c.first_name, ' ', c.last_name) AS name,
                       c.contact_status AS status
                FROM job_contacts jc
                JOIN (
                    SELECT job_id, MIN(id) AS min_link_id
                    FROM job_contacts
                    WHERE job_id IN ($in)
                    GROUP BY job_id
                ) first_link ON first_link.job_id = jc.job_id AND first_link.min_link_id = jc.id
                JOIN contacts c ON c.id = jc.contact_id
            ";
            $stmt = $pdo->prepare($sqlFallback);
            $stmt->execute($jobIds);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $jobContacts[(int)$row['job_id']] = [
                'id'     => (int)$row['id'],
                'name'   => (string)$row['name'],
                'status' => (string)($row['status'] ?? ''),
            ];
        }
    }
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>Error loading jobs: " . htmlspecialchars($e->getMessage()) . "</div>";
    $filter_html = '';
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Jobs</h2>
    <a href="add_job.php" class="btn btn-primary">+ Add Job</a>
</div>

<!-- FLEX LAYOUT WITH DRAG HANDLE (Sidebar Filters + Table) -->
<div id="lv-wrap" class="lv-wrap">
    <!-- LEFT: Filters sidebar (resizable) -->
    <aside id="lv-sidebar" class="lv-sidebar">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Filters</span>
                <a href="jobs.php?reset=1" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            <div class="card-body">
                <?= $filter_html ?: '<div class="text-muted">No filters available.</div>' ?>
            </div>
        </div>
    </aside>

    <!-- VERTICAL DRAG HANDLE -->
    <div id="lv-dragbar" class="lv-dragbar" title="Drag to resize"></div>

    <!-- RIGHT: Jobs table -->
    <main id="lv-content" class="lv-content">
        <div style="overflow-x: auto;">
            <table class="table table-striped table-bordered resizable" id="resizableJobs">
                <thead class="table-dark">
                    <tr>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('title')) ?>">
                                Title<?= htmlspecialchars(($S['arrow'])('title')) ?>
                            </a>
                        </th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('location')) ?>">
                                Location<?= htmlspecialchars(($S['arrow'])('location')) ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('status')) ?>">
                                Status<?= htmlspecialchars(($S['arrow'])('status')) ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('created_at')) ?>">
                                Created<?= htmlspecialchars(($S['arrow'])('created_at')) ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($jobs)): ?>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td>
                                    <a href="view_job.php?id=<?= (int)$job['id'] ?>">
                                        <?= htmlspecialchars($job['title'] ?? '') ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                        $cid = isset($job['client_id']) ? (int)$job['client_id'] : 0;
                                        $cname = $cid && isset($clientNames[$cid]) ? $clientNames[$cid] : null;
                                    ?>
                                    <?php if ($cid && $cname): ?>
                                        <a href="view_client.php?id=<?= $cid ?>"><?= htmlspecialchars($cname) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php
                                        $jid = (int)($job['id'] ?? 0);
                                        $assoc = $jid && isset($jobContacts[$jid]) ? $jobContacts[$jid] : null;
                                    ?>
                                    <?php if ($assoc && !empty($assoc['name'])): ?>
                                        <a href="view_contact.php?id=<?= (int)$assoc['id'] ?>"><?= htmlspecialchars($assoc['name']) ?></a>
                                        <span class="ms-2"><?= contact_status_badge($assoc['status'] ?? null, 'sm') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($job['location'] ?? '') ?></td>
                                <td><?= htmlspecialchars($job['status'] ?? '') ?></td>
                                <td><?= htmlspecialchars($job['created_at'] ?? '') ?></td>
                                <td>
                                    <a href="edit_job.php?id=<?= (int)$job['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No jobs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?= $pager_html ?? '' ?>
        </div>
    </main>
</div>

<style>
    /* Existing column-resize styles */
    th { position: relative; }
    th .resizer {
        position: absolute;
        right: 0;
        top: 0;
        width: 5px;
        height: 100%;
        cursor: col-resize;
        user-select: none;
        z-index: 1;
    }

    /* New: flex layout for resizable sidebar */
    .lv-wrap {
        display: flex;
        align-items: stretch;
        gap: 0;
        width: 100%;
        min-height: 0;
    }
    .lv-sidebar {
        width: 320px;            /* default width */
        min-width: 220px;
        max-width: 600px;
        overflow: auto;
        transition: width 0.05s;
    }
    .lv-dragbar {
        width: 6px;
        cursor: col-resize;
        background: rgba(0,0,0,0.05);
        border-left: 1px solid rgba(0,0,0,0.08);
        border-right: 1px solid rgba(0,0,0,0.08);
    }
    .lv-dragbar:hover,
    .lv-dragbar.lv-active {
        background: rgba(0,0,0,0.12);
    }
    .lv-content {
        flex: 1 1 auto;
        min-width: 0;
        padding-left: 12px;
    }

    /* Mobile stack */
    @media (max-width: 767.98px) {
        .lv-wrap { display: block; }
        .lv-sidebar { width: 100% !important; max-width: none; }
        .lv-dragbar { display: none; }
        .lv-content { padding-left: 0; }
    }
</style>

<script>
    // Existing table column resize
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById('resizableJobs');
        const cols = table ? table.querySelectorAll('th') : [];
        cols.forEach(th => {
            const resizer = document.createElement('div');
            resizer.classList.add('resizer');
            th.appendChild(resizer);
            resizer.addEventListener('mousedown', initResize);
        });

        let startX, startWidth, currentCol;

        function initResize(e) {
            currentCol = e.target.parentElement;
            startX = e.clientX;
            startWidth = currentCol.offsetWidth;
            document.addEventListener('mousemove', resizeColumn);
            document.addEventListener('mouseup', stopResize);
        }

        function resizeColumn(e) {
            const width = startWidth + (e.clientX - startX);
            currentCol.style.width = width + 'px';
        }

        function stopResize() {
            document.removeEventListener('mousemove', resizeColumn);
            document.removeEventListener('mouseup', stopResize);
        }
    });

    // Sidebar drag-to-resize with persistence
    (function () {
        const sidebar = document.getElementById('lv-sidebar');
        const dragbar = document.getElementById('lv-dragbar');
        if (!sidebar || !dragbar) return;

        const STORAGE_KEY = 'jobs_sidebar_width_px';
        const MIN_W = 220;
        const MAX_W = 600;

        // restore saved width
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const w = parseInt(saved, 10);
                if (!isNaN(w)) sidebar.style.width = Math.min(MAX_W, Math.max(MIN_W, w)) + 'px';
            }
        } catch (e) {}

        let dragging = false;
        let startX = 0;
        let startW = 0;

        function onMouseDown(e) {
            dragging = true;
            startX = e.clientX;
            startW = sidebar.getBoundingClientRect().width;
            dragbar.classList.add('lv-active');
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            e.preventDefault();
        }

        function onMouseMove(e) {
            if (!dragging) return;
            const delta = e.clientX - startX;
            let newW = Math.round(startW + delta);
            if (newW < MIN_W) newW = MIN_W;
            if (newW > MAX_W) newW = MAX_W;
            sidebar.style.width = newW + 'px';
        }

        function onMouseUp() {
            if (!dragging) return;
            dragging = false;
            dragbar.classList.remove('lv-active');
            try {
                const currentW = Math.round(sidebar.getBoundingClientRect().width);
                localStorage.setItem(STORAGE_KEY, String(currentW));
            } catch (e) {}
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }

        dragbar.addEventListener('mousedown', onMouseDown);

        // touch support
        dragbar.addEventListener('touchstart', (e) => {
            if (!e.touches || !e.touches[0]) return;
            dragging = true;
            startX = e.touches[0].clientX;
            startW = sidebar.getBoundingClientRect().width;
            dragbar.classList.add('lv-active');
            document.addEventListener('touchmove', onTouchMove, { passive: false });
            document.addEventListener('touchend', onTouchEnd);
        }, { passive: true });

        function onTouchMove(e) {
            if (!dragging || !e.touches || !e.touches[0]) return;
            e.preventDefault();
            const delta = e.touches[0].clientX - startX;
            let newW = Math.round(startW + delta);
            if (newW < MIN_W) newW = MIN_W;
            if (newW > MAX_W) newW = MAX_W;
            sidebar.style.width = newW + 'px';
        }
        function onTouchEnd() {
            if (!dragging) return;
            dragging = false;
            dragbar.classList.remove('lv-active');
            try {
                const currentW = Math.round(sidebar.getBoundingClientRect().width);
                localStorage.setItem(STORAGE_KEY, String(currentW));
            } catch (e) {}
            document.removeEventListener('touchmove', onTouchMove);
            document.removeEventListener('touchend', onTouchEnd);
        }
    })();
</script>

<?php require_once '../includes/footer.php'; ?>
