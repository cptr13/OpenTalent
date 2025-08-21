<?php
require_once __DIR__ . '/../includes/require_login.php';
require_once '../includes/header.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/list_view.php';
require_once __DIR__ . '/../includes/sortable.php';
require_once __DIR__ . '/../config/status.php'; // <-- Needed for getStatusList('contact')

// Default to empty list in case of errors
$contacts = [];
$clientNames = [];

// Build contact status dropdown options from config/status.contact.php
$contactStatusOptions = [];
try {
    $statusList = getStatusList('contact'); // returns ['Category' => ['Sub1','Sub2',...]]
    foreach ($statusList as $cat => $subs) {
        foreach ($subs as $s) {
            $contactStatusOptions[] = $s;
        }
    }
    // Deduplicate just in case
    $contactStatusOptions = array_values(array_unique($contactStatusOptions));
} catch (Throwable $e) {
    // If loader fails for any reason, leave options empty so list_view falls back to DISTINCT query
    $contactStatusOptions = [];
}

// Config for Contacts list/filters
$config = [
    'table' => 'contacts',
    // Include contact_status so rows have it available for display/sort
    'default_columns' => ['first_name', 'last_name', 'email', 'phone', 'contact_status', 'created_at'],
    'column_labels' => [
        'first_name'      => 'First Name',
        'last_name'       => 'Last Name',
        'email'           => 'Email',
        'phone'           => 'Phone',
        'created_at'      => 'Created',
        'company'         => 'Company',       // Virtual column via JOIN in list_view (sort-enabled)
        'contact_status'  => 'Status',
    ],
    'filter_types' => [
        'first_name'      => 'text',
        'last_name'       => 'text',
        'email'           => 'text',
        'phone'           => 'text',
        'created_at'      => 'date_range',
        'contact_status'  => 'dropdown',      // <-- Renders as a select
    ],
    // Provide static dropdown choices so it’s a curated select (no typing)
    'filter_options' => [
        'contact_status' => $contactStatusOptions, // <-- Options for the dropdown
    ],
];

/**
 * Sort controls for header links
 * IMPORTANT: keys here must match what list_view.php understands in $_GET['sort'].
 * We sort "Name" by last_name (then first_name handled by list_view's secondary tie-break, if any).
 */
$ALLOWED_COLUMNS = [
    'last_name'       => 'last_name',
    'email'           => 'email',
    'phone'           => 'phone',
    'company'         => 'company',        // now enabled (JOIN sort)
    'contact_status'  => 'contact_status', // <-- enable sort on Status
    'created_at'      => 'created_at',
];

// Defaults: sort by last_name ASC
$S = ot_get_sort($ALLOWED_COLUMNS, 'last_name', 'asc');

try {
    // Get filtered/sorted rows + filter form HTML
    list($contacts, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta) = get_list_view_data($pdo, $config);

    // Bulk-lookup client names for links (keeps your existing UI)
    $clientIds = [];
    foreach ($contacts as $r) {
        if (!empty($r['client_id'])) {
            $clientIds[] = (int)$r['client_id'];
        }
    }
    $clientIds = array_values(array_unique(array_filter($clientIds)));
    if ($clientIds) {
        $in = implode(',', array_fill(0, count($clientIds), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id IN ($in)");
        $stmt->execute($clientIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $clientNames[(int)$row['id']] = $row['name'];
        }
    }
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>Error loading contacts: " . htmlspecialchars($e->getMessage()) . "</div>";
    $filter_html = '';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Contacts</h2>
    <div class="btn-group">
        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            + Add
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="add_contact.php">Add New Contact</a></li>
            <li><a class="dropdown-item" href="import_clients_contacts.php">Import Clients & Contacts</a></li>
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
                <a href="contacts.php?reset=1" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
            <div class="card-body">
                <?= $filter_html ?: '<div class="text-muted">No filters available.</div>' ?>
            </div>
        </div>
    </aside>

    <!-- VERTICAL DRAG HANDLE -->
    <div id="lv-dragbar" class="lv-dragbar" title="Drag to resize"></div>

    <!-- RIGHT: Contacts table -->
    <main id="lv-content" class="lv-content">
        <div style="overflow-x: auto;">
            <table class="table table-striped table-bordered resizable" id="resizableContacts">
                <thead class="table-dark">
                    <tr>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('last_name')) ?>">
                                Name<?= htmlspecialchars(($S['arrow'])('last_name')) ?>
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
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('company')) ?>">
                                Company<?= htmlspecialchars(($S['arrow'])('company')) ?>
                            </a>
                        </th>
                        <th>
                            <a class="text-white text-decoration-none" href="<?= htmlspecialchars(($S['link'])('contact_status')) ?>">
                                Status<?= htmlspecialchars(($S['arrow'])('contact_status')) ?>
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
                    <?php if (!empty($contacts)): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td>
                                    <a href="view_contact.php?id=<?= (int)$contact['id'] ?>">
                                        <?= htmlspecialchars(trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($contact['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($contact['phone'] ?? '') ?></td>
                                <td>
                                    <?php
                                        $cid = isset($contact['client_id']) ? (int)$contact['client_id'] : 0;
                                        $cname = $cid && isset($clientNames[$cid]) ? $clientNames[$cid] : null;
                                    ?>
                                    <?php if ($cid && $cname): ?>
                                        <a href="view_client.php?id=<?= $cid ?>">
                                            <?= htmlspecialchars($cname) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $status = trim((string)($contact['contact_status'] ?? ''));
                                        if ($status === '') {
                                            echo '<span class="text-muted">—</span>';
                                        } else {
                                            // Neutral compact badge (no category color yet)
                                            echo '<span class="badge bg-secondary">'.htmlspecialchars($status).'</span>';
                                        }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($contact['created_at'] ?? '') ?></td>
                                <td>
                                    <a href="edit_contact.php?id=<?= (int)$contact['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="delete_contact.php?id=<?= (int)$contact['id'] ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this contact?');">
                                       Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No contacts found.</td>
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
        const table = document.getElementById('resizableContacts');
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

        const STORAGE_KEY = 'contacts_sidebar_width_px';
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
