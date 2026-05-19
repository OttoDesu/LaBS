<?php
require_once __DIR__ . '/init.php';
require_login();
require_super_admin();

$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'public';
$flash_info = get_flash('info');
$flash_error = get_flash('error');

$report_years = [];
$report_clusters = [];
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

$year_result = $mysqli->query('
    SELECT DISTINCT YEAR(lb.booking_date) AS booking_year
    FROM lab_bookings lb
    WHERE lb.booking_date IS NOT NULL
    ORDER BY booking_year DESC
');
if ($year_result) {
    while ($row = $year_result->fetch_assoc()) {
        $year = (int) ($row['booking_year'] ?? 0);
        if ($year > 0) {
            $report_years[] = $year;
        }
    }
}
if (!$report_years) {
    $report_years[] = (int) date('Y');
}

$cluster_result = $mysqli->query('SELECT cluster_id, cluster_name FROM clusters ORDER BY cluster_name ASC');
if ($cluster_result) {
    while ($row = $cluster_result->fetch_assoc()) {
        $report_clusters[] = [
            'cluster_id' => (int) $row['cluster_id'],
            'cluster_name' => $row['cluster_name']
        ];
    }
}

$layout = require __DIR__ . '/templates/layouts/admin.php';
$active = 'report-analytics';
$user_payload = [
    'name' => $user_name,
    'email' => $user_email,
    'userType' => $user_type
];
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
                            <div class="user-name" id="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="user-email" id="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                        <span class="chevron"><svg class="chevron-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </div>
                    <div class="user-menu" id="user-menu">
                        <div class="user-menu-header">
                            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                        </div>
                        <a class="menu-item" href="profile.php"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="menu-icon"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z" fill="currentColor"/></svg>Edit Profile</a>
                        <button class="menu-item" type="button"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="menu-icon"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.5 12C3.5 7.30558 7.30558 3.5 12 3.5C16.6944 3.5 20.5 7.30558 20.5 12C20.5 16.6944 16.6944 20.5 12 20.5C7.30558 20.5 3.5 16.6944 3.5 12ZM12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM11.0991 7.52507C11.0991 8.02213 11.5021 8.42507 11.9991 8.42507H12.0001C12.4972 8.42507 12.9001 8.02213 12.9001 7.52507C12.9001 7.02802 12.4972 6.62507 12.0001 6.62507H11.9991C11.5021 6.62507 11.0991 7.02802 11.0991 7.52507ZM12.0001 17.3714C11.5859 17.3714 11.2501 17.0356 11.2501 16.6214V10.9449C11.2501 10.5307 11.5859 10.1949 12.0001 10.1949C12.4143 10.1949 12.7501 10.5307 12.7501 10.9449V16.6214C12.7501 17.0356 12.4143 17.3714 12.0001 17.3714Z" fill="currentColor"/></svg>Support</button>
                        <form method="POST" action="logout.php">
                            <button class="menu-item danger" type="submit"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="menu-icon"><path fill-rule="evenodd" clip-rule="evenodd" d="M15.1007 19.247C14.6865 19.247 14.3507 18.9112 14.3507 18.497L14.3507 14.245H12.8507V18.497C12.8507 19.7396 13.8581 20.747 15.1007 20.747H18.5007C19.7434 20.747 20.7507 19.7396 20.7507 18.497L20.7507 5.49609C20.7507 4.25345 19.7433 3.24609 18.5007 3.24609H15.1007C13.8581 3.24609 12.8507 4.25345 12.8507 5.49609V9.74501L14.3507 9.74501V5.49609C14.3507 5.08188 14.6865 4.74609 15.1007 4.74609L18.5007 4.74609C18.9149 4.74609 19.2507 5.08188 19.2507 5.49609L19.2507 18.497C19.2507 18.9112 18.9149 19.247 18.5007 19.247H15.1007ZM3.25073 11.9984C3.25073 12.2144 3.34204 12.4091 3.48817 12.546L8.09483 17.1556C8.38763 17.4485 8.86251 17.4487 9.15549 17.1559C9.44848 16.8631 9.44863 16.3882 9.15583 16.0952L5.81116 12.7484L16.0007 12.7484C16.4149 12.7484 16.7507 12.4127 16.7507 11.9984C16.7507 11.5842 16.4149 11.2484 16.0007 11.2484L5.81528 11.2484L9.15585 7.90554C9.44864 7.61255 9.44847 7.13767 9.15547 6.84488C8.86248 6.55209 8.3876 6.55226 8.09481 6.84525L3.52309 11.4202C3.35673 11.5577 3.25073 11.7657 3.25073 11.9984Z" fill="currentColor"/></svg>Sign Out</button>
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
                        <p>Analyze bookings by year, month, week, cluster, and lab.</p>
                    </div>
                    <div class="breadcrumb">Home / Report Analytics</div>
                </div>

                <div class="card report-filter-card" id="report-analytics">
                    <div class="chart-header">
                        <div>
                            <p class="badge">Super Admin Report</p>
                            <h3>Booking analytics by year, month, week, or specific date</h3>
                        </div>
                        <span class="muted-text">Loaded with AJAX without refreshing the page</span>
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
                        <select name="cluster_id" id="report-cluster">
                            <option value="">All clusters</option>
                            <?php foreach ($report_clusters as $cluster): ?>
                                <option value="<?php echo (int) $cluster['cluster_id']; ?>"><?php echo htmlspecialchars($cluster['cluster_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="lab_id" id="report-lab" disabled>
                            <option value="">All labs</option>
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
                    <div class="card stat-card">
                        <p class="stat-label">Total Bookings</p>
                        <h2 class="stat-value">-</h2>
                        <span class="stat-meta">Waiting for report</span>
                    </div>
                    <div class="card stat-card">
                        <p class="stat-label">Unique Users</p>
                        <h2 class="stat-value">-</h2>
                        <span class="stat-meta">Waiting for report</span>
                    </div>
                    <div class="card stat-card">
                        <p class="stat-label">Total Hours</p>
                        <h2 class="stat-value">-</h2>
                        <span class="stat-meta">Waiting for report</span>
                    </div>
                    <div class="card stat-card">
                        <p class="stat-label">Average Hours</p>
                        <h2 class="stat-value">-</h2>
                        <span class="stat-meta">Waiting for report</span>
                    </div>
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
                        <div id="report-bar-chart" class="report-chart-body">
                            <div class="empty-state">Report data will appear here after you submit a filter.</div>
                        </div>
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
                        <div id="report-pie-chart" class="report-chart-body">
                            <div class="empty-state">Report data will appear here after you submit a filter.</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="chart-header">
                        <div>
                            <p class="badge">Report Table</p>
                            <h3>Booking records</h3>
                        </div>
                        <span class="muted-text" id="report-table-meta">No data yet</span>
                    </div>
                    <div class="table-wrapper" id="report-table-wrapper">
                        <div class="empty-state">Report data will appear here after you submit a filter.</div>
                    </div>
                </div>

                <footer class="footer">Â© Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
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
            'defaultMonth' => (int) date('n')
        ]); ?>;
    </script>
    <script src="assets/app.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/app.js'); ?>"></script>
    <script src="assets/super-admin-report.js?v=<?php echo (int) filemtime(__DIR__ . '/assets/super-admin-report.js'); ?>"></script>
</body>
</html>
