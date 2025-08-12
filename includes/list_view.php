<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Build filtered, sorted, paginated list view data.
 *
 * @param PDO   $pdo
 * @param array $config  [
 *   'table' => 'clients',
 *   'default_columns' => [...],
 *   'column_labels' => ['db_col' => 'Label', ...],
 *   'filter_types' => ['db_col' => 'text|dropdown|equals|date_range', ...]
 * ]
 * @return array [$rows, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta]
 */
function get_list_view_data(PDO $pdo, array $config)
{
    // ---- Helpers ----
    $is_ident = function ($s) {
        // allow letters, numbers, underscore; must start with letter or underscore
        return is_string($s) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $s);
    };
    $bt = function ($ident) {
        return '`' . $ident . '`';
    };
    $build_query = function (array $params, array $overrides = []) {
        // Preserve existing GET params except ones explicitly overridden/removed
        $base = $_GET;
        // never carry reset forward
        unset($base['reset']);
        // replace with overrides
        foreach ($overrides as $k => $v) {
            if ($v === null) {
                unset($base[$k]);
            } else {
                $base[$k] = $v;
            }
        }
        // Build encoded query
        $pairs = [];
        foreach ($base as $k => $v) {
            if (is_array($v)) continue; // skip arrays for simplicity
            $pairs[] = urlencode($k) . '=' . urlencode($v);
        }
        return $pairs ? ('?' . implode('&', $pairs)) : '';
    };

    // ---- Config ----
    $table = $config['table'] ?? '';
    $default_columns = $config['default_columns'] ?? [];
    $column_labels = $config['column_labels'] ?? [];
    $filter_types = $config['filter_types'] ?? [];

    if (!$table) {
        throw new RuntimeException('list_view: table not specified');
    }
    if (!$is_ident($table)) {
        throw new RuntimeException('list_view: invalid table name');
    }

    // Reset: clear filters and pagination state
    if (isset($_GET['reset'])) {
        unset($_SESSION['filters'][$table]);
        unset($_SESSION['pager'][$table]);
    }

    // If no default columns, try to use column_labels keys; fallback to ['id']
    if (empty($default_columns)) {
        $default_columns = !empty($column_labels) ? array_keys($column_labels) : ['id'];
    }

    // Build whitelist of allowed columns (for sorting & identifier checks)
    $allowed_cols = array_unique(array_merge(
        $default_columns,
        array_keys($filter_types),
        array_keys($column_labels)
    ));
    // Keep only valid identifiers
    $allowed_cols = array_values(array_filter($allowed_cols, $is_ident));

    // Special: allow virtual "company" sort for contacts even if not in config arrays
    if ($table === 'contacts' && !in_array('company', $allowed_cols, true)) {
        $allowed_cols[] = 'company';
    }

    // ---- Persist filters (GET -> session) ----
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // basic filters
        foreach ($filter_types as $col => $type) {
            if (!$is_ident($col)) continue;
            if (isset($_GET[$col])) {
                $_SESSION['filters'][$table][$col] = trim((string)$_GET[$col]);
            }
            // date range extra fields
            if ($type === 'date_range') {
                $sKey = $col . '_start';
                $eKey = $col . '_end';
                if (isset($_GET[$sKey])) {
                    $_SESSION['filters'][$table][$sKey] = trim((string)$_GET[$sKey]);
                }
                if (isset($_GET[$eKey])) {
                    $_SESSION['filters'][$table][$eKey] = trim((string)$_GET[$eKey]);
                }
            }
        }
    }

    $filters = $_SESSION['filters'][$table] ?? [];

    // ---- Sorting (whitelisted) ----
    $sort_col = $_GET['sort'] ?? $default_columns[0];
    if (!in_array($sort_col, $allowed_cols, true)) {
        $sort_col = $default_columns[0];
    }
    $sort_dir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';

    // ---- Pagination: per_page + page (persisted) ----
    $allowed_page_sizes = [20, 50, 100, 200];
    $default_per_page = 50;

    // Read per_page
    if (isset($_GET['per_page'])) {
        $pp = (int)$_GET['per_page'];
        $_SESSION['pager'][$table]['per_page'] = in_array($pp, $allowed_page_sizes, true) ? $pp : $default_per_page;
    }
    $per_page = $_SESSION['pager'][$table]['per_page'] ?? $default_per_page;
    if (!in_array($per_page, $allowed_page_sizes, true)) {
        $per_page = $default_per_page;
    }

    // ----------------------------
    // Branch by table where needed
    // ----------------------------

    if ($table === 'contacts') {
        // Aliases
        $ct = 'ct';
        $cl = 'cl';
        $qcol = function(string $col) use ($ct, $cl, $bt) {
            if ($col === 'company') return "$cl." . $bt('name');
            return "$ct." . $bt($col);
        };

        // WHERE (qualified to contacts alias to avoid ambiguity)
        $where = [];
        $params = [];
        foreach ($filter_types as $col => $type) {
            if (!$is_ident($col)) continue;

            switch ($type) {
                case 'text':
                case 'equals':
                case 'dropdown':
                    $val = $filters[$col] ?? '';
                    if ($val !== '' && $val !== null) {
                        if ($type === 'text') {
                            $where[] = $qcol($col) . " LIKE ?";
                            $params[] = '%' . $val . '%';
                        } else { // equals|dropdown
                            $where[] = $qcol($col) . " = ?";
                            $params[] = $val;
                        }
                    }
                    break;

                case 'date_range':
                    $start = $filters[$col . '_start'] ?? '';
                    $end   = $filters[$col . '_end'] ?? '';
                    if ($start !== '') {
                        $where[] = $qcol($col) . " >= ?";
                        $params[] = $start;
                    }
                    if ($end !== '') {
                        $where[] = $qcol($col) . " <= ?";
                        $params[] = $end;
                    }
                    break;
                default:
                    break;
            }
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count with JOIN
        $count_sql = "
            SELECT COUNT(*) AS cnt
            FROM contacts $ct
            LEFT JOIN clients $cl ON $cl.id = $ct.client_id
            $where_sql
        ";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total = (int)$count_stmt->fetchColumn();

        // Read page
        if (isset($_GET['page'])) {
            $p = (int)$_GET['page'];
            $_SESSION['pager'][$table]['page'] = ($p >= 1) ? $p : 1;
        }
        $page = $_SESSION['pager'][$table]['page'] ?? 1;

        // Compute pages
        $total_pages = ($per_page > 0) ? (int)ceil($total / $per_page) : 1;
        if ($total_pages < 1) $total_pages = 1;
        if ($page > $total_pages) $page = $total_pages;
        if ($page < 1) $page = 1;

        $offset = ($page - 1) * $per_page;
        if ($offset < 0) $offset = 0;

        // Map sort to qualified expressions (include virtual 'company')
        $sort_map = [
            'first_name' => $qcol('first_name'),
            'last_name'  => $qcol('last_name'),
            'email'      => $qcol('email'),
            'phone'      => $qcol('phone'),
            'title'      => $qcol('title'),
            'owner'      => $qcol('contact_owner'),
            'created_at' => $qcol('created_at'),
            'company'    => "$cl." . $bt('name'),
        ];
        $order_expr = $sort_map[$sort_col] ?? $qcol('last_name');

        // Main query with JOIN, qualified ORDER BY, and stable tie-break
        $sql = "
            SELECT
                $ct.*,
                $cl.name AS company
            FROM contacts $ct
            LEFT JOIN clients $cl ON $cl.id = $ct.client_id
            $where_sql
            ORDER BY $order_expr $sort_dir, $ct.id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        // Bind filter params
        $i = 1;
        foreach ($params as $pval) {
            $stmt->bindValue($i, $pval, is_int($pval) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $i++;
        }
        // Bind limit/offset
        $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ---- Filter panel HTML (unchanged, except it already uses $filter_types) ----
        ob_start();
        echo "<form method='GET'>";
        foreach ($filter_types as $col => $type) {
            if (!$is_ident($col)) continue;

            $label = $column_labels[$col] ?? ucfirst(str_replace('_', ' ', $col));
            $val   = $filters[$col] ?? '';

            echo "<div class='mb-3'>";
            echo "<label class='form-label'>" . htmlspecialchars($label) . "</label>";

            if ($type === 'text' || $type === 'equals') {
                echo "<input type='text' class='form-control' name='" . htmlspecialchars($col, ENT_QUOTES) . "' value='" . htmlspecialchars($val, ENT_QUOTES) . "'>";
            }
            elseif ($type === 'dropdown') {
                echo "<select class='form-control' name='" . htmlspecialchars($col, ENT_QUOTES) . "'>";
                echo "<option value=''>-- Any --</option>";
                // DISTINCT options from contacts table only (filters refer to contacts.*)
                $optSql = "SELECT DISTINCT " . $bt($col) . " AS v FROM " . $bt($table) . " WHERE " . $bt($col) . " IS NOT NULL AND " . $bt($col) . " <> '' ORDER BY " . $bt($col) . " ASC";
                $optStmt = $pdo->query($optSql);
                $options = $optStmt ? $optStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                foreach ($options as $opt) {
                    $selected = ($opt === $val) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($opt, ENT_QUOTES) . "' $selected>" . htmlspecialchars($opt) . "</option>";
                }
                echo "</select>";
            }
            elseif ($type === 'date_range') {
                $start = $filters[$col . '_start'] ?? '';
                $end   = $filters[$col . '_end'] ?? '';
                echo "<input type='date' class='form-control mb-2' name='" . htmlspecialchars($col . '_start', ENT_QUOTES) . "' value='" . htmlspecialchars($start, ENT_QUOTES) . "'>";
                echo "<input type='date' class='form-control' name='" . htmlspecialchars($col . '_end', ENT_QUOTES) . "' value='" . htmlspecialchars($end, ENT_QUOTES) . "'>";
            }

            echo "</div>";
        }
        echo "<button type='submit' class='btn btn-primary w-100'>Apply Filters</button>";
        echo "</form>";
        $filter_html = ob_get_clean();

        // ---- Pager HTML (same UI as before) ----
        $from = $total ? ($offset + 1) : 0;
        $to   = $total ? min($offset + $per_page, $total) : 0;

        ob_start();
        echo "<div class='d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3'>";
        echo "<div class='text-muted'>Showing $from–$to of $total</div>";

        $first_disabled = ($page <= 1) ? ' disabled' : '';
        $prev_disabled  = ($page <= 1) ? ' disabled' : '';
        $next_disabled  = ($page >= $total_pages) ? ' disabled' : '';
        $last_disabled  = ($page >= $total_pages) ? ' disabled' : '';

        $qs_first = $build_query($_GET, ['page' => 1]);
        $qs_prev  = $build_query($_GET, ['page' => max(1, $page - 1)]);
        $qs_next  = $build_query($_GET, ['page' => min($total_pages, $page + 1)]);
        $qs_last  = $build_query($_GET, ['page' => $total_pages]);

        echo "<nav aria-label='Pagination'><ul class='pagination mb-0'>";
        echo "<li class='page-item$first_disabled'><a class='page-link' href='" . htmlspecialchars($qs_first) . "' tabindex='-1'>First</a></li>";
        echo "<li class='page-item$prev_disabled'><a class='page-link' href='" . htmlspecialchars($qs_prev) . "' tabindex='-1' aria-label='Previous'>&laquo; Prev</a></li>";
        echo "<li class='page-item disabled'><span class='page-link'>Page $page of $total_pages</span></li>";
        echo "<li class='page-item$next_disabled'><a class='page-link' href='" . htmlspecialchars($qs_next) . "' aria-label='Next'>Next &raquo;</a></li>";
        echo "<li class='page-item$last_disabled'><a class='page-link' href='" . htmlspecialchars($qs_last) . "'>Last</a></li>";
        echo "</ul></nav>";

        echo "<form method='GET' class='d-flex align-items-center gap-2'>";
        echo "<label class='form-label mb-0'>Rows per page</label>";
        echo "<select name='per_page' class='form-select form-select-sm' onchange='this.form.submit()'>";
        foreach ([20, 50, 100, 200] as $opt) {
            $sel = ($opt === (int)$per_page) ? ' selected' : '';
            echo "<option value='$opt'$sel>$opt</option>";
        }
        echo "</select>";
        foreach ($_GET as $k => $v) {
            if (in_array($k, ['per_page', 'page', 'reset'], true)) continue;
            if (is_array($v)) continue;
            echo "<input type='hidden' name='" . htmlspecialchars($k, ENT_QUOTES) . "' value='" . htmlspecialchars($v, ENT_QUOTES) . "'>";
        }
        echo "</form>";
        echo "</div>";
        $pager_html = ob_get_clean();

        $page_meta = [
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'offset' => $offset,
            'from' => $from,
            'to' => $to,
            'sort_col' => $sort_col,
            'sort_dir' => $sort_dir,
        ];

        return [$rows, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta];
    }

    // ----------------------------
    // Generic path (all other tables)
    // ----------------------------

    // ---- WHERE clause ----
    $where = [];
    $params = [];

    foreach ($filter_types as $col => $type) {
        if (!$is_ident($col)) continue;

        switch ($type) {
            case 'text':
            case 'equals':
            case 'dropdown':
                $val = $filters[$col] ?? '';
                if ($val !== '' && $val !== null) {
                    if ($type === 'text') {
                        $where[] = $bt($col) . " LIKE ?";
                        $params[] = '%' . $val . '%';
                    } else { // equals|dropdown
                        $where[] = $bt($col) . " = ?";
                        $params[] = $val;
                    }
                }
                break;

            case 'date_range':
                $start = $filters[$col . '_start'] ?? '';
                $end   = $filters[$col . '_end'] ?? '';
                if ($start !== '') {
                    $where[] = $bt($col) . " >= ?";
                    $params[] = $start;
                }
                if ($end !== '') {
                    $where[] = $bt($col) . " <= ?";
                    $params[] = $end;
                }
                break;

            default:
                // unknown type -> skip
                break;
        }
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // ---- Counts (generic) ----
    $count_sql = "SELECT COUNT(*) AS cnt FROM " . $bt($table) . " $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();

    // Read page
    if (isset($_GET['page'])) {
        $p = (int)$_GET['page'];
        $_SESSION['pager'][$table]['page'] = ($p >= 1) ? $p : 1;
    }
    $page = $_SESSION['pager'][$table]['page'] ?? 1;

    // Compute pages
    $total_pages = ($per_page > 0) ? (int)ceil($total / $per_page) : 1;
    if ($total_pages < 1) $total_pages = 1;
    if ($page > $total_pages) $page = $total_pages;
    if ($page < 1) $page = 1;

    $offset = ($page - 1) * $per_page;
    if ($offset < 0) $offset = 0;

    // ---- Query with LIMIT/OFFSET (generic) ----
    $sql = "SELECT * FROM " . $bt($table) . " $where_sql ORDER BY " . $bt($sort_col) . " $sort_dir LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    // Bind the same filter params
    $i = 1;
    foreach ($params as $pval) {
        $stmt->bindValue($i, $pval, is_int($pval) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $i++;
    }
    // Bind limit/offset as ints
    $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- Filter panel HTML ----
    ob_start();
    echo "<form method='GET'>";
    foreach ($filter_types as $col => $type) {
        if (!$is_ident($col)) continue;

        $label = $column_labels[$col] ?? ucfirst(str_replace('_', ' ', $col));
        $val   = $filters[$col] ?? '';

        echo "<div class='mb-3'>";
        echo "<label class='form-label'>" . htmlspecialchars($label) . "</label>";

        if ($type === 'text' || $type === 'equals') {
            echo "<input type='text' class='form-control' name='" . htmlspecialchars($col, ENT_QUOTES) . "' value='" . htmlspecialchars($val, ENT_QUOTES) . "'>";
        }
        elseif ($type === 'dropdown') {
            echo "<select class='form-control' name='" . htmlspecialchars($col, ENT_QUOTES) . "'>";
            echo "<option value=''>-- Any --</option>";
            // DISTINCT options for this column
            $optSql = "SELECT DISTINCT " . $bt($col) . " AS v FROM " . $bt($table) . " WHERE " . $bt($col) . " IS NOT NULL AND " . $bt($col) . " <> '' ORDER BY " . $bt($col) . " ASC";
            $optStmt = $pdo->query($optSql);
            $options = $optStmt ? $optStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($options as $opt) {
                $selected = ($opt === $val) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($opt, ENT_QUOTES) . "' $selected>" . htmlspecialchars($opt) . "</option>";
            }
            echo "</select>";
        }
        elseif ($type === 'date_range') {
            $start = $filters[$col . '_start'] ?? '';
            $end   = $filters[$col . '_end'] ?? '';
            echo "<input type='date' class='form-control mb-2' name='" . htmlspecialchars($col . '_start', ENT_QUOTES) . "' value='" . htmlspecialchars($start, ENT_QUOTES) . "'>";
            echo "<input type='date' class='form-control' name='" . htmlspecialchars($col . '_end', ENT_QUOTES) . "' value='" . htmlspecialchars($end, ENT_QUOTES) . "'>";
        }

        echo "</div>";
    }
    echo "<button type='submit' class='btn btn-primary w-100'>Apply Filters</button>";
    echo "</form>";
    $filter_html = ob_get_clean();

    // ---- Pager HTML ----
    $from = $total ? ($offset + 1) : 0;
    $to   = $total ? min($offset + $per_page, $total) : 0;

    // Build per_page <form> preserving current params
    ob_start();
    echo "<div class='d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3'>";
    // Left: range + total
    echo "<div class='text-muted'>Showing $from–$to of $total</div>";

    // Middle: pager buttons
    $first_disabled = ($page <= 1) ? ' disabled' : '';
    $prev_disabled  = ($page <= 1) ? ' disabled' : '';
    $next_disabled  = ($page >= $total_pages) ? ' disabled' : '';
    $last_disabled  = ($page >= $total_pages) ? ' disabled' : '';

    $qs_first = $build_query($_GET, ['page' => 1]);
    $qs_prev  = $build_query($_GET, ['page' => max(1, $page - 1)]);
    $qs_next  = $build_query($_GET, ['page' => min($total_pages, $page + 1)]);
    $qs_last  = $build_query($_GET, ['page' => $total_pages]);

    echo "<nav aria-label='Pagination'><ul class='pagination mb-0'>";
    echo "<li class='page-item$first_disabled'><a class='page-link' href='" . htmlspecialchars($qs_first) . "' tabindex='-1'>First</a></li>";
    echo "<li class='page-item$prev_disabled'><a class='page-link' href='" . htmlspecialchars($qs_prev) . "' tabindex='-1' aria-label='Previous'>&laquo; Prev</a></li>";
    echo "<li class='page-item disabled'><span class='page-link'>Page $page of $total_pages</span></li>";
    echo "<li class='page-item$next_disabled'><a class='page-link' href='" . htmlspecialchars($qs_next) . "' aria-label='Next'>Next &raquo;</a></li>";
    echo "<li class='page-item$last_disabled'><a class='page-link' href='" . htmlspecialchars($qs_last) . "'>Last</a></li>";
    echo "</ul></nav>";

    // Right: per-page selector (preserve params in hidden inputs)
    echo "<form method='GET' class='d-flex align-items-center gap-2'>";
    echo "<label class='form-label mb-0'>Rows per page</label>";
    echo "<select name='per_page' class='form-select form-select-sm' onchange='this.form.submit()'>";
    foreach ([20, 50, 100, 200] as $opt) {
        $sel = ($opt === (int)$per_page) ? ' selected' : '';
        echo "<option value='$opt'$sel>$opt</option>";
    }
    echo "</select>";
    // preserve all current GET except per_page and page and reset
    foreach ($_GET as $k => $v) {
        if (in_array($k, ['per_page', 'page', 'reset'], true)) continue;
        if (is_array($v)) continue;
        echo "<input type='hidden' name='" . htmlspecialchars($k, ENT_QUOTES) . "' value='" . htmlspecialchars($v, ENT_QUOTES) . "'>";
    }
    echo "</form>";

    echo "</div>";
    $pager_html = ob_get_clean();

    // ---- Page meta for optional use
    $page_meta = [
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'from' => $from,
        'to' => $to,
        'sort_col' => $sort_col,
        'sort_dir' => $sort_dir,
    ];

    return [$rows, $filter_html, $sort_col, $sort_dir, $pager_html, $page_meta];
}
