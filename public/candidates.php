<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/list_view.php';
require_once __DIR__ . '/../includes/sortable.php';

// Default to empty list in case of errors
$candidates = [];

// Config for Candidates list/filters
$config = [
    'table' => 'candidates',
    'default_columns' => ['first_name', 'last_name', 'email', 'phone', 'status', 'owner', 'created_at'],
    'column_labels' => [
        'first_name' => 'First Name',
        'last_name'  => 'Last Name',
        'email'      => 'Email',
        'phone'      => 'Phone',
        'status'     => 'Status',
        'owner'      => 'Owner',
        'created_at' => 'Added',
    ],
    'filter_types' => [
        'first_name' => 'text',
        'last_name'  => 'text',
        'email'      => 'text',
        'phone'      => 'text',
        'status'     => 'dropdown',   // Filter still uses candidates.status; fine for now.
        'owner'      => 'text',
        'created_at' => 'date_range',
    ],
];

/**
 * Sort controls for header links
 * Keys must match what list_view.php accepts via $_GET['sort'].
 */
$ALLOWED_COLUMNS = [
    'first_name' => 'first_name',
    'last_name'  => 'last_name',
    'email'      => 'email',
    'phone'      => 'phone',
    'status'     => 'status',
    'owner'      => 'owner',
    'created_at' => 'created_at',
];
// Default sort
$S = ot_get_sort($ALLOWED_COLUMNS, 'last_name', 'asc');

try {
    list($candidates, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta) = get_list_view_data($pdo, $config);
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>Error loading candidates: " . htmlspecialchars($e->getMessage()) . "</div>";
    $filter_html = '';
}

/**
 * Override displayed status with the latest association status per candidate.
 * - Primary attempt prefers associations.updated_at (most recently updated)
 * - Fallback uses highest associations.id when updated_at isn't available
 */
if (!empty($candidates)) {
    $ids = array_map(fn($r) => (int)($r['id'] ?? 0), $candidates);
    $ids = array_values(array_filter($ids, fn($v) => $v > 0));

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $latestStatusByCandidate = [];

        // Try using updated_at if present
        try {
            $sql = "
                SELECT a.candidate_id, a.status
                FROM associations a
                JOIN (
                    SELECT candidate_id, MAX(updated_at) AS max_updated
                    FROM associations
                    WHERE candidate_id IN ($placeholders)
                    GROUP BY candidate_id
                ) x ON x.candidate_id = a.candidate_id AND a.updated_at = x.max_updated
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $latestStatusByCandidate[(int)$row['candidate_id']] = (string)$row['status'];
            }
        } catch (Throwable $e) {
            // Fallback: use highest id per candidate
            try {
                $sql = "
                    SELECT a.candidate_id, a.status
                    FROM associations a
                    JOIN (
                        SELECT candidate_id, MAX(id) AS max_id
                        FROM associations
                        WHERE candidate_id IN ($placeholders)
                        GROUP BY candidate_id
                    ) x ON x.candidate_id = a.candidate_id AND a.id = x.max_id
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($ids);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $latestStatusByCandidate[(int)$row['candidate_id']] = (string)$row['status'];
                }
            } catch (Throwable $e2) {
                // If associations table is missing/invalid, silently keep original candidate.status
            }
        }

        // Apply overrides to the rows we render
        if ($latestStatusByCandidate) {
            foreach ($candidates as &$cand) {
                $cid = (int)($cand['id'] ?? 0);
                if ($cid > 0 && isset($latestStatusByCandidate[$cid]) && $latestStatusByCandidate[$cid] !== '') {
                    $cand['status'] = $latestStatusByCandidate[$cid];
                }
            }
            unset($cand);
        }
    }
}

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Candidates</h2>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                + Add
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="add_candidate.php">Add Manually</a></li>
                <li><a class="dropdown-item" href="parse_resume.php">Parse Resume</a></li>
                <li><a class="dropdown-item" href="bulk_upload.php">Bulk Upload Resumes</a></li>
                <li><a class="dropdown-item" href="import_candidates.php">Import from Excel</a></li>
            </ul>
        </div>
    </div>

    <!-- FLEX LAYOUT WITH DRAG HANDLE (Sidebar Filters + Table) -->
    <div id="lv-wrap" class="lv-wrap">
        <!-- LEFT: Filters sidebar (resizable) -->
        <aside id="lv-sidebar" class="lv-sidebar">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Filters</span>
                    <a href="candidates.php?reset=1" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
                <div class="card-body">
                    <?= $filter_html ?: '<div class="text-muted">No filters available.</div>' ?>
                </div>
            </div>
        </aside>

        <!-- VERTICAL DRAG HANDLE -->
        <div id="lv-dragbar" class="lv-dragbar" title="Drag to resize"></div>

        <!-- RIGHT: Candidates table -->
        <main id="lv-content" class="lv-content">
            <div class="table-responsive">
                <table class="table table-striped table-bordered draggable-table">
                    <thead class="table-dark">
                        <tr>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('first_name')) ?>">
                                    First Name<?= htmlspecialchars(($S['arrow'])('first_name')) ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('last_name')) ?>">
                                    Last Name<?= htmlspecialchars(($S['arrow'])('last_name')) ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('email')) ?>">
                                    Email<?= htmlspecialchars(($S['arrow'])('email')) ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('phone')) ?>">
                                    Phone<?= htmlspecialchars(($S['arrow'])('phone')) ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('status')) ?>">
                                    Status<?= htmlspecialchars(($S['arrow'])('status')) ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('owner')) ?>">
                                    Owner<?= htmlspecialchars(($S['arrow'])('owner')) ?>
                                </a>
                            </th>
                            <th>
                                <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('created_at')) ?>">
                                    Added<?= htmlspecialchars(($S['arrow'])('created_at')) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($candidates)): ?>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td>
                                        <a href="view_candidate.php?id=<?= (int)$candidate['id'] ?>">
                                            <?= htmlspecialchars($candidate['first_name'] ?? '') ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($candidate['last_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($candidate['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($candidate['phone'] ?? '') ?></td>
                                    <td>
                                        <?php $st = trim((string)($candidate['status'] ?? '')); ?>
                                        <span class="badge <?= $st === '' ? 'bg-secondary' : 'bg-secondary' ?>">
                                            <?= htmlspecialchars($st !== '' ? $st : 'N/A') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($candidate['owner'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars(!empty($candidate['created_at']) ? date("Y-m-d", strtotime($candidate['created_at'])) : '') ?></td>
                                    <td>
                                        <a href="edit_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <a href="delete_candidate.php?id=<?= (int)$candidate['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this candidate?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No candidates found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?= $pager_html ?>
        </main>
    </div>
</div>

<style>
    /* Existing column-resize styles from your page */
    .draggable-table th { position: relative; }

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
// Keep your existing column resize behavior for draggable-table
document.querySelectorAll(".draggable-table").forEach(table => {
    const ths = table.querySelectorAll("th");
    ths.forEach(th => {
        const resizer = document.createElement("div");
        resizer.style.width = "5px";
        resizer.style.height = "100%";
        resizer.style.position = "absolute";
        resizer.style.top = 0;
        resizer.style.right = 0;
        resizer.style.cursor = "col-resize";
        resizer.style.userSelect = "none";

        resizer.addEventListener("mousedown", function (e) {
            const startX = e.pageX;
            const startWidth = th.offsetWidth;

            const onMouseMove = (e) => {
                const newWidth = startWidth + (e.pageX - startX);
                th.style.width = newWidth + "px";
            };

            const onMouseUp = () => {
                document.removeEventListener("mousemove", onMouseMove);
                document.removeEventListener("mouseup", onMouseUp);
            };

            document.addEventListener("mousemove", onMouseMove);
            document.addEventListener("mouseup", onMouseUp);
        });

        th.style.position = "relative";
        th.appendChild(resizer);
    });
});

// Sidebar drag-to-resize with persistence (module-specific key)
(function () {
    const sidebar = document.getElementById('lv-sidebar');
    const dragbar = document.getElementById('lv-dragbar');
    if (!sidebar || !dragbar) return;

    const STORAGE_KEY = 'candidates_sidebar_width_px';
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
