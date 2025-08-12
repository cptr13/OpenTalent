<?php

require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/sortable.php';

// pull in reusable list view helper
require_once __DIR__ . '/../includes/list_view.php';

// Default to empty list in case of errors
$clients = [];

// configuration for filters/labels/columns
$config = [
    'table' => 'clients',
    'default_columns' => ['name', 'industry', 'location', 'account_manager', 'created_at'],
    'column_labels' => [
        'name' => 'Client Name',
        'industry' => 'Industry',
        'location' => 'Location',
        'account_manager' => 'Account Manager',
        'created_at' => 'Created',
    ],
    'filter_types' => [
        'name' => 'text',
        'industry' => 'dropdown',
        'location' => 'text',
        'account_manager' => 'text',
        'created_at' => 'date_range',
    ],
];

/**
 * Sort controls for header links
 * We only need the keys to match what list_view.php accepts in $_GET['sort'].
 * The SQL fragment returned by ot_get_sort()'s mapping isn't used on this page,
 * because list_view.php handles ORDER BY internally. Mapping each key to itself
 * allows the helper to validate/emit links + arrows safely.
 */
$ALLOWED_COLUMNS = [
    'name'            => 'name',
    'industry'        => 'industry',
    'location'        => 'location',
    'account_manager' => 'account_manager',
    'created_at'      => 'created_at',
];
// Defaults: name ASC
$S = ot_get_sort($ALLOWED_COLUMNS, 'name', 'asc');

try {
    list($clients, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta) = get_list_view_data($pdo, $config);
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>Error loading clients: " . htmlspecialchars($e->getMessage()) . "</div>";
    $filter_html = '';
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Clients</h2>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            + Add
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="add_client.php">Add New Client</a></li>
            <li><a class="dropdown-item" href="import_clients_contacts.php">Import Clients & Contacts</a></li>
        </ul>
    </div>
</div>

<!-- FLEX LAYOUT WITH DRAG HANDLE -->
<div id="lv-wrap" class="lv-wrap">
    <!-- LEFT: Filters sidebar (resizable) -->
    <aside id="lv-sidebar" class="lv-sidebar">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Filters</span>
                <a href="clients.php?reset=1" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            <div class="card-body">
                <?= $filter_html ?: '<div class="text-muted">No filters available.</div>' ?>
            </div>
        </div>
    </aside>

    <!-- VERTICAL DRAG HANDLE -->
    <div id="lv-dragbar" class="lv-dragbar" title="Drag to resize"></div>

    <!-- RIGHT: Table -->
    <main id="lv-content" class="lv-content">
        <div style="overflow-x: auto;">
            <table class="table table-striped table-bordered resizable" id="resizableTable">
                <thead class="table-dark">
                    <tr>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('name')) ?>">
                                Name<?= htmlspecialchars(($S['arrow'])('name')) ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('industry')) ?>">
                                Industry<?= htmlspecialchars(($S['arrow'])('industry')) ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('location')) ?>">
                                Location<?= htmlspecialchars(($S['arrow'])('location')) ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('account_manager')) ?>">
                                Account Manager<?= htmlspecialchars(($S['arrow'])('account_manager')) ?>
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
                    <?php if (!empty($clients)): ?>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><a href="view_client.php?id=<?= $client['id'] ?>"><?= htmlspecialchars($client['name'] ?? '') ?></a></td>
                                <td><?= htmlspecialchars($client['industry'] ?? '') ?></td>
                                <td><?= htmlspecialchars($client['location'] ?? '') ?></td>
                                <td><?= htmlspecialchars($client['account_manager'] ?? '') ?></td>
                                <td><?= htmlspecialchars($client['created_at'] ?? '') ?></td>
                                <td>
                                    <a href="edit_client.php?id=<?= $client['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No clients found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?= $pager_html ?>
        </div>
    </main>
</div>

<style>
    /* Existing column-resize styles */
    th { position: relative; }
    th.resizer { cursor: col-resize; user-select: none; }
    th .resizer { position: absolute; right: 0; top: 0; width: 5px; height: 100%; cursor: col-resize; z-index: 1; }

    /* New: flex layout for resizable sidebar */
    .lv-wrap {
        display: flex;
        align-items: stretch;
        gap: 0;
        width: 100%;
        min-height: 0; /* prevent overflow issues in flex parent */
    }
    .lv-sidebar {
        width: 320px;            /* default width */
        min-width: 220px;        /* prevent collapsing too far */
        max-width: 600px;        /* optional: cap very wide */
        overflow: auto;          /* ensures inner scroll rather than growing page */
        transition: width 0.05s; /* slight smoothness */
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
        min-width: 0; /* allows horizontal scroll of table when needed */
        padding-left: 12px; /* small spacing from dragbar */
    }

    /* Mobile: stack gracefully */
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
        const table = document.getElementById('resizableTable');
        if (table) {
            const cols = table.querySelectorAll('th');
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
        }
    });

    // New: sidebar drag-to-resize with persistence
    (function () {
        const sidebar = document.getElementById('lv-sidebar');
        const dragbar = document.getElementById('lv-dragbar');
        if (!sidebar || !dragbar) return;

        const STORAGE_KEY = 'clients_sidebar_width_px';
        const MIN_W = 220;
        const MAX_W = 600;

        // restore saved width
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const w = parseInt(saved, 10);
                if (!isNaN(w)) sidebar.style.width = Math.min(MAX_W, Math.max(MIN_W, w)) + 'px';
            }
        } catch (e) {
            // ignore storage errors
        }

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
            // persist width
            try {
                const currentW = Math.round(sidebar.getBoundingClientRect().width);
                localStorage.setItem(STORAGE_KEY, String(currentW));
            } catch (e) {
                // ignore storage errors
            }
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }

        dragbar.addEventListener('mousedown', onMouseDown);
        // touch support (optional)
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
