<?php
require_once __DIR__ . '/init.php';
require_login();
require_management();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'public';
$is_super_admin = is_super_admin($user_type);
$is_cluster_admin = is_cluster_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$flash_info = get_flash('info');
$flash_error = get_flash('error');

$report_months = [
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
];

$report_clusters = [];
$report_labs = [];
$report_years = [];
$locked_cluster_id = null;
$role_badge = 'Report Analytics';
$role_heading = 'Booking analytics by year, month, week, or specific date';
$role_description = 'Loaded with AJAX without refreshing the page';

if ($is_super_admin) {
    $role_badge = 'Super Admin Report';
} elseif ($is_cluster_admin) {
    $role_badge = 'Cluster Admin Report';
    $role_description = 'Report is limited to your assigned cluster.';
} elseif ($is_lab_supervisor) {
    $role_badge = 'Lab Supervisor Report';
    $role_description = 'Report is limited to labs under your supervision.';
}

if ($is_cluster_admin) {
    $locked_cluster_id = (int) (get_admin_cluster_id() ?? 0);
    if ($locked_cluster_id > 0) {
        $stmt = $mysqli->prepare('SELECT cluster_id, cluster_name FROM clusters WHERE cluster_id = ? LIMIT 1');
        $stmt->bind_param('i', $locked_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $report_clusters[] = [
                'cluster_id' => (int) $row['cluster_id'],
                'cluster_name' => (string) $row['cluster_name']
            ];
        }
        $stmt->close();

        $stmt = $mysqli->prepare('SELECT lab_id, lab_name FROM labs WHERE cluster_id = ? ORDER BY lab_name ASC');
        $stmt->bind_param('i', $locked_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $report_labs[] = [
                'lab_id' => (int) $row['lab_id'],
                'lab_name' => (string) $row['lab_name']
            ];
        }
        $stmt->close();
    }
} elseif ($is_lab_supervisor) {
    $assigned_lab_ids = get_lab_supervisor_lab_ids($mysqli, $user_id);
    if ($assigned_lab_ids) {
        $placeholders = implode(',', array_fill(0, count($assigned_lab_ids), '?'));
        $types = str_repeat('i', count($assigned_lab_ids));
        $stmt = $mysqli->prepare("
            SELECT l.lab_id, l.lab_name, c.cluster_id, c.cluster_name
            FROM labs l
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE l.lab_id IN ($placeholders)
            ORDER BY c.cluster_name ASC, l.lab_name ASC
        ");
        $stmt->bind_param($types, ...$assigned_lab_ids);
        $stmt->execute();
        $result = $stmt->get_result();
        $cluster_map = [];
        while ($row = $result->fetch_assoc()) {
            $cluster_map[(int) $row['cluster_id']] = [
                'cluster_id' => (int) $row['cluster_id'],
                'cluster_name' => (string) $row['cluster_name']
            ];
            $report_labs[] = [
                'lab_id' => (int) $row['lab_id'],
                'lab_name' => (string) $row['lab_name']
            ];
        }
        $stmt->close();
        $report_clusters = array_values($cluster_map);
    }
} else {
    $cluster_result = $mysqli->query('SELECT cluster_id, cluster_name FROM clusters ORDER BY cluster_name ASC');
    if ($cluster_result) {
        while ($row = $cluster_result->fetch_assoc()) {
            $report_clusters[] = [
                'cluster_id' => (int) $row['cluster_id'],
                'cluster_name' => (string) $row['cluster_name']
            ];
        }
    }
}

$year_sql = '
    SELECT DISTINCT YEAR(lb.booking_date) AS booking_year
    FROM lab_bookings lb
    JOIN labs l ON l.lab_id = lb.lab_id
    WHERE lb.booking_date IS NOT NULL
';
$year_types = '';
$year_params = [];

if ($is_cluster_admin && $locked_cluster_id) {
    $year_sql .= ' AND l.cluster_id = ?';
    $year_types .= 'i';
    $year_params[] = $locked_cluster_id;
} elseif ($is_lab_supervisor) {
    $assigned_lab_ids = get_lab_supervisor_lab_ids($mysqli, $user_id);
    if ($assigned_lab_ids) {
        $year_sql .= ' AND l.lab_id IN (' . implode(',', array_fill(0, count($assigned_lab_ids), '?')) . ')';
        $year_types .= str_repeat('i', count($assigned_lab_ids));
        foreach ($assigned_lab_ids as $assigned_lab_id) {
            $year_params[] = (int) $assigned_lab_id;
        }
    } else {
        $year_sql .= ' AND 1 = 0';
    }
}
$year_sql .= ' ORDER BY booking_year DESC';

if ($year_types !== '') {
    $stmt = $mysqli->prepare($year_sql);
    $stmt->bind_param($year_types, ...$year_params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $year = (int) ($row['booking_year'] ?? 0);
        if ($year > 0) {
            $report_years[] = $year;
        }
    }
    $stmt->close();
} else {
    $year_result = $mysqli->query($year_sql);
    if ($year_result) {
        while ($row = $year_result->fetch_assoc()) {
            $year = (int) ($row['booking_year'] ?? 0);
            if ($year > 0) {
                $report_years[] = $year;
            }
        }
    }
}

if (!$report_years) {
    $report_years[] = (int) date('Y');
}

$layout_path = __DIR__ . '/templates/layouts/admin.php';
if ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
}
$layout = require $layout_path;
$active = ($is_cluster_admin || $is_lab_supervisor) ? 'dashboard' : 'report-analytics';
$user_payload = [
    'name' => $user_name,
    'email' => $user_email,
    'userType' => $user_type
];
$cluster_select_disabled = $is_cluster_admin || (count($report_clusters) <= 1 && !$is_super_admin);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaBS PPMKCP Report Analytics</title>
    <link rel="stylesheet" href="assets/app.css?v=<?php echo (int) filemtime(__DIR__ . '/assets/app.css'); ?>">
</head>
<body data-login-url="index.php">
    <div class="app">
        <?php include __DIR__ . '/templates/layouts/sidebar.php'; ?>

        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-button" id="toggle-sidebar" aria-label="Toggle sidebar">
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z" fill="currentColor"/>
                        </svg>
                    </button>
                    <div class="search">
                        <span class="search-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z" fill="currentColor"/>
                            </svg>
                        </span>
                        <input type="text" id="global-search" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-button" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z" fill="currentColor"/>
                        </svg>
                    </button>
                    <div class="user-chip" id="user-menu-toggle" role="button" tabindex="0">
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                        <span class="chevron"><svg class="chevron-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </div>
                    <div class="user-menu" id="user-menu">
                        <div class="user-menu-header">
                            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                        <a class="menu-item" href="profile.php">Edit Profile</a>
                        <form method="POST" action="logout.php">
                            <button class="menu-item danger" type="submit">Sign Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <section class="content">
                <?php if ($flash_error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($flash_error); ?></div>
                <?php endif; ?>
                <?php if ($flash_info): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($flash_info); ?></div>
                <?php endif; ?>

                <div class="content-header">
                    <div>
                        <h1>Report Analytics</h1>
                        <p>Analyze bookings based on your reporting scope.</p>
                    </div>
                    <div class="breadcrumb">Home / Report Analytics</div>
                </div>

                <div class="card report-filter-card" id="report-analytics">
                    <div class="chart-header">
                        <div>
                            <p class="badge"><?php echo htmlspecialchars($role_badge); ?></p>
                            <h3><?php echo htmlspecialchars($role_heading); ?></h3>
                        </div>
                        <span class="muted-text"><?php echo htmlspecialchars($role_description); ?></span>
                    </div>
                    <form class="filters report-filters" id="super-admin-report-form">
                        <select name="filter_type" id="report-filter-type">
                            <option value="year">Year</option>
                            <option value="month">Month</option>
                            <option value="week">Week</option>
                            <option value="date">Specific Date</option>
                        </select>
                        <select name="year" id="report-year">
                            <?php foreach ($report_years as $year): ?>
                                <option value="<?php echo (int) $year; ?>"><?php echo (int) $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="month" id="report-month">
                            <?php foreach ($report_months as $month_number => $month_name): ?>
                                <option value="<?php echo (int) $month_number; ?>"<?php echo (int) $month_number === (int) date('n') ? ' selected' : ''; ?>><?php echo htmlspecialchars($month_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="week" id="report-week"></select>
                        <div class="report-date-range-fields" id="report-date-range-fields" hidden>
                            <input type="date" name="start_date" id="report-start-date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                            <input type="date" name="end_date" id="report-end-date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                        </div>
                        <select name="cluster_id" id="report-cluster" <?php echo $cluster_select_disabled ? 'disabled' : ''; ?>>
                            <option value="">All clusters</option>
                            <?php foreach ($report_clusters as $cluster): ?>
                                <option value="<?php echo (int) $cluster['cluster_id']; ?>"<?php echo $locked_cluster_id !== null && (int) $cluster['cluster_id'] === (int) $locked_cluster_id ? ' selected' : ''; ?>><?php echo htmlspecialchars($cluster['cluster_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="lab_id" id="report-lab" <?php echo $report_labs ? '' : 'disabled'; ?>>
                            <option value="">All labs</option>
                            <?php foreach ($report_labs as $lab): ?>
                                <option value="<?php echo (int) $lab['lab_id']; ?>"><?php echo htmlspecialchars($lab['lab_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn primary" type="submit" id="report-submit">Generate Report</button>
                    </form>
                    <div class="report-filter-state">
                        <span class="muted-text" id="report-filter-description">Choose a filter and generate the report.</span>
                        <span class="muted-text" id="report-selected-days" hidden>Selected days: 1 day</span>
                        <span class="muted-text" id="report-loading-state" hidden>Loading report...</span>
                    </div>
                </div>

                <div id="report-error" class="alert alert-error" hidden></div>

                <div class="stats-grid" id="report-summary-cards">
                    <div class="card stat-card"><p class="stat-label">Total Bookings</p><h2 class="stat-value">-</h2><span class="stat-meta">Waiting for report</span></div>
                    <div class="card stat-card"><p class="stat-label">Unique Users</p><h2 class="stat-value">-</h2><span class="stat-meta">Waiting for report</span></div>
                    <div class="card stat-card"><p class="stat-label">Total Hours</p><h2 class="stat-value">-</h2><span class="stat-meta">Waiting for report</span></div>
                    <div class="card stat-card"><p class="stat-label">Average Hours</p><h2 class="stat-value">-</h2><span class="stat-meta">Waiting for report</span></div>
                </div>

                <div class="report-visual-grid">
                    <div class="card chart-card">
                        <div class="chart-header">
                            <div>
                                <p class="badge">Bar Chart</p>
                                <h3>Booking distribution</h3>
                            </div>
                            <div class="card-actions">
                                <span class="muted-text" id="report-bar-meta">No data yet</span>
                                <button class="btn ghost small report-export" type="button" data-chart-target="report-bar-chart" data-export-format="png">Export PNG</button>
                                <button class="btn ghost small report-export" type="button" data-chart-target="report-bar-chart" data-export-format="pdf">Export PDF</button>
                            </div>
                        </div>
                        <div id="report-bar-chart" class="report-chart-body"><div class="empty-state">Report data will appear here after you submit a filter.</div></div>
                    </div>
                    <div class="card chart-card">
                        <div class="chart-header">
                            <div>
                                <p class="badge">Pie Chart</p>
                                <h3>Status distribution</h3>
                            </div>
                            <div class="card-actions">
                                <button class="btn ghost small report-export" type="button" data-chart-target="report-pie-chart" data-export-format="png">Export PNG</button>
                                <button class="btn ghost small report-export" type="button" data-chart-target="report-pie-chart" data-export-format="pdf">Export PDF</button>
                            </div>
                        </div>
                        <div id="report-pie-chart" class="report-chart-body"><div class="empty-state">Report data will appear here after you submit a filter.</div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="chart-header">
                        <div>
                            <p class="badge">Report Table</p>
                            <h3>Booking records</h3>
                        </div>
                        <div class="report-table-tools">
                            <label class="report-page-size-control">
                                <span>Show</span>
                                <select id="report-page-size" aria-label="Report rows per page">
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="all">All</option>
                                </select>
                            </label>
                            <span class="muted-text" id="report-table-meta">No data yet</span>
                        </div>
                    </div>
                    <div class="table-wrapper" id="report-table-wrapper">
                        <div class="empty-state">Report data will appear here after you submit a filter.</div>
                    </div>
                    <div class="pagination report-table-pagination" id="report-table-pagination" hidden></div>
                </div>

                <footer class="footer">© Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <div class="report-tooltip" id="report-tooltip" hidden></div>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
        window.SUPER_ADMIN_REPORT = <?php echo json_encode([
            'endpoint' => 'super-admin-report-api.php',
            'years' => $report_years,
            'defaultYear' => (int) ($report_years[0] ?? date('Y')),
            'defaultMonth' => (int) date('n'),
            'roleScope' => $is_super_admin ? 'super_admin' : ($is_cluster_admin ? 'cluster_admin' : 'lab_supervisor'),
            'lockedClusterId' => $locked_cluster_id,
            'initialLabs' => $report_labs
        ]); ?>;
    </script>
    <script src="assets/app.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/app.js'); ?>"></script>
    <script src="assets/super-admin-report.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/super-admin-report.js'); ?>"></script>
</body>
</html>
