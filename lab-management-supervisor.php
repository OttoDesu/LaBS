<?php
require_once __DIR__ . '/init.php';
require_login();

$user_type = $_SESSION['user_type'] ?? 'public';
require_admin();
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$can_edit_labs = false;
if (is_lab_supervisor($user_type)) {
    set_flash('error', 'You do not have permission to access that page.');
    header('Location: lab-management.php');
    exit;
}

function paginate_items(array $items, $current_page, $per_page) {
    $total_items = count($items);
    $total_pages = max(1, (int) ceil($total_items / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    return [
        'items' => array_slice($items, $offset, $per_page),
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_items' => $total_items,
        'per_page' => $per_page
    ];
}

function format_history_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d/m/Y', $timestamp);
}

$mysqli->query('
    CREATE TABLE IF NOT EXISTS supervisors (
        supervisor_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        cluster_id BIGINT(20) UNSIGNED NOT NULL,
        supervisor_name VARCHAR(255) NOT NULL,
        supervisor_email VARCHAR(255) DEFAULT NULL,
        supervisor_room_no VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_cluster_supervisor (cluster_id, supervisor_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

$cluster_id = isset($_GET['cluster']) ? (int) $_GET['cluster'] : 0;
$supervisor_param = trim($_GET['supervisor'] ?? '');
$supervisor_id = ctype_digit($supervisor_param) ? (int) $supervisor_param : 0;
$is_unassigned = ($supervisor_param === 'unassigned' || $supervisor_id === 0);
$active_tab = $_GET['tab'] ?? 'profile';
if (!in_array($active_tab, ['profile', 'history'], true)) {
    $active_tab = 'profile';
}
$today_date = date('Y-m-d');
$today_js = htmlspecialchars($today_date, ENT_QUOTES);

if ($cluster_id <= 0 || $supervisor_param === '') {
    set_flash('error', 'Please select a supervisor.');
    header('Location: lab-management.php');
    exit;
}
if (!$is_super_admin && $admin_cluster_id !== $cluster_id) {
    set_flash('error', 'You do not have permission to access that cluster.');
    header('Location: lab-management.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($is_super_admin) {
        set_flash('error', 'Super admins can only view Lab Management.');
        header('Location: lab-management-supervisor.php?cluster=' . (int) $cluster_id . '&supervisor=' . urlencode($supervisor_param));
        exit;
    }
    if ($action === 'update_lab_details') {
        $lab_id = (int) ($_POST['lab_id'] ?? 0);
        $name = trim($_POST['lab_name'] ?? '');
        $capacity = (int) ($_POST['lab_capacity'] ?? 0);
        $description = trim($_POST['lab_description'] ?? '');
        $errors = [];
        $maintenance = normalize_lab_maintenance_input($_POST, $errors);
        if ($lab_id <= 0 || $name === '') {
            $errors[] = 'Lab name is required.';
        }
        if ($errors) {
            set_flash('error', implode(' ', $errors));
            header('Location: lab-management-supervisor.php?cluster=' . (int) $cluster_id . '&supervisor=' . urlencode($supervisor_param));
            exit;
        }
        $stmt = $mysqli->prepare('
            UPDATE labs
            SET lab_name = ?, lab_capacity = ?, lab_description = ?, maintenance_status = ?, maintenance_start_date = ?, maintenance_end_date = ?
            WHERE lab_id = ? AND cluster_id = ?
        ');
        $stmt->bind_param('sissssii', $name, $capacity, $description, $maintenance['status'], $maintenance['start_date'], $maintenance['end_date'], $lab_id, $cluster_id);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Lab updated.');
        header('Location: lab-management-supervisor.php?cluster=' . (int) $cluster_id . '&supervisor=' . urlencode($supervisor_param));
        exit;
    }
}

$cluster_name = '';
$cluster_description = '';
$cluster_stmt = $mysqli->prepare('SELECT cluster_name, cluster_description FROM clusters WHERE cluster_id = ?');
$cluster_stmt->bind_param('i', $cluster_id);
$cluster_stmt->execute();
$cluster_result = $cluster_stmt->get_result();
if ($row = $cluster_result->fetch_assoc()) {
    $cluster_name = $row['cluster_name'];
    $cluster_description = $row['cluster_description'];
}
$cluster_stmt->close();

$labs = [];
$supervisor_email = '';
$supervisor_room = '';
$supervisor_display = $is_unassigned ? 'Unassigned' : '';
if ($is_unassigned) {
    $labs_stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, l.lab_description, l.lab_capacity,
               l.maintenance_status, l.maintenance_start_date, l.maintenance_end_date,
               c.cluster_name, s.supervisor_name, s.supervisor_email, s.supervisor_room_no
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
        WHERE l.cluster_id = ? AND l.supervisor_id IS NULL
        ORDER BY l.lab_name ASC
    ');
    $labs_stmt->bind_param('i', $cluster_id);
} else {
    $email_stmt = $mysqli->prepare('
        SELECT supervisor_name, supervisor_email, supervisor_room_no
        FROM supervisors
        WHERE cluster_id = ? AND supervisor_id = ?
        LIMIT 1
    ');
    $email_stmt->bind_param('ii', $cluster_id, $supervisor_id);
    $email_stmt->execute();
    $email_result = $email_stmt->get_result();
    if ($row = $email_result->fetch_assoc()) {
        $supervisor_display = $row['supervisor_name'];
        $supervisor_email = $row['supervisor_email'];
        $supervisor_room = $row['supervisor_room_no'];
    }
    $email_stmt->close();
    if ($supervisor_display === '') {
        set_flash('error', 'Supervisor not found.');
        header('Location: lab-management-cluster.php?cluster=' . (int) $cluster_id);
        exit;
    }
    $labs_stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, l.lab_description, l.lab_capacity,
               l.maintenance_status, l.maintenance_start_date, l.maintenance_end_date,
               c.cluster_name, s.supervisor_name, s.supervisor_email, s.supervisor_room_no
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
        WHERE l.cluster_id = ? AND l.supervisor_id = ?
        ORDER BY l.lab_name ASC
    ');
    $labs_stmt->bind_param('ii', $cluster_id, $supervisor_id);
}
$labs_stmt->execute();
$labs_result = $labs_stmt->get_result();
while ($row = $labs_result->fetch_assoc()) {
    $labs[] = $row;
}
$labs_stmt->close();

$labs_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$labs_pagination = paginate_items($labs, $labs_page, 2);
$labs = $labs_pagination['items'];

$lab_history_map = [];
$visible_lab_ids = array_values(array_filter(array_map(function ($lab) {
    return (int) ($lab['lab_id'] ?? 0);
}, $labs), function ($lab_id) {
    return $lab_id > 0;
}));
if (!empty($visible_lab_ids)) {
    $placeholders = implode(',', array_fill(0, count($visible_lab_ids), '?'));
    $types = str_repeat('i', count($visible_lab_ids));
    $history_stmt = $mysqli->prepare("
        SELECT h.history_id, h.lab_id, h.previous_supervisor_id, h.supervisor_id, h.started_at, h.ended_at,
               curr.supervisor_name AS current_supervisor_name,
               prev.supervisor_name AS previous_supervisor_name
        FROM lab_supervisor_history h
        LEFT JOIN supervisors curr ON curr.supervisor_id = h.supervisor_id
        LEFT JOIN supervisors prev ON prev.supervisor_id = h.previous_supervisor_id
        WHERE h.lab_id IN ($placeholders)
        ORDER BY h.lab_id ASC, h.started_at DESC, h.history_id DESC
    ");
    if ($history_stmt) {
        $history_stmt->bind_param($types, ...$visible_lab_ids);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        while ($row = $history_result->fetch_assoc()) {
            $lab_id = (int) $row['lab_id'];
            if (!isset($lab_history_map[$lab_id])) {
                $lab_history_map[$lab_id] = [];
            }
            $lab_history_map[$lab_id][] = $row;
        }
        $history_stmt->close();
    }
}

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$layout_path = __DIR__ . '/templates/layouts/admin.php';
$layout = require $layout_path;
$active = 'lab-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Labs</title>
    <link rel="stylesheet" href="assets/app.css?v=<?php echo (int) (@filemtime(__DIR__ . '/assets/app.css') ?: time()); ?>">
</head>
<body data-login-url="index.php">
    <div class="app">
        <?php include __DIR__ . '/templates/layouts/sidebar.php'; ?>

        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-button" id="toggle-sidebar" aria-label="Toggle sidebar">
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                    <div class="search">
                        <span class="search-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fill-rule="evenodd"
                                    clip-rule="evenodd"
                                    d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <input type="text" id="global-search" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-button" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                    <div class="user-chip" id="user-menu-toggle" role="button" tabindex="0">
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($user_payload['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_payload['email']); ?></div>
                        </div>
                        <span class="chevron"><svg class="chevron-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </div>
                    <div class="user-menu" id="user-menu">
                        <div class="user-menu-header">
                            <div class="user-name"><?php echo htmlspecialchars($user_payload['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_payload['email']); ?></div>
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
                <div class="content-header">
                    <div>
                        <h1>Supervisor Labs</h1>
                        <p>View the labs assigned to this supervisor and review their assignment history.</p>
                    </div>
                    <div class="breadcrumb">Home / Lab Management / Supervisor</div>
                </div>

                <div class="section-stack">
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2><?php echo htmlspecialchars($cluster_name); ?></h2>
                                <p><?php echo htmlspecialchars($cluster_description); ?></p>
                            </div>
                        <div class="banner-links">
                            <a class="btn ghost" href="lab-management-cluster.php?cluster=<?php echo (int) $cluster_id; ?>">Back to supervisors</a>
                        </div>
                        </div>
                        <div class="asset-management-tabs" role="tablist" aria-label="Supervisor lab sections">
                            <a
                                class="asset-management-tab <?php echo $active_tab === 'profile' ? 'is-active' : ''; ?>"
                                href="lab-management-supervisor.php?cluster=<?php echo (int) $cluster_id; ?>&supervisor=<?php echo urlencode($supervisor_param); ?>&tab=profile"
                                <?php echo $active_tab === 'profile' ? 'aria-current="page"' : ''; ?>
                            >
                                Supervisor Profile
                            </a>
                            <a
                                class="asset-management-tab <?php echo $active_tab === 'history' ? 'is-active' : ''; ?>"
                                href="lab-management-supervisor.php?cluster=<?php echo (int) $cluster_id; ?>&supervisor=<?php echo urlencode($supervisor_param); ?>&tab=history"
                                <?php echo $active_tab === 'history' ? 'aria-current="page"' : ''; ?>
                            >
                                Lab History
                            </a>
                        </div>
                    </div>

                    <?php if ($active_tab === 'profile'): ?>
                        <div class="card supervisor-hero-card">
                            <div class="supervisor-hero-top">
                                <div>
                                    <p class="eyebrow">Supervisor Profile</p>
                                    <h2><?php echo htmlspecialchars($supervisor_display); ?></h2>
                                    <p><?php echo htmlspecialchars($supervisor_email ?: 'No email provided'); ?></p>
                                </div>
                            </div>
                            <div class="supervisor-summary-grid">
                                <div class="supervisor-summary-item">
                                    <span>Cluster</span>
                                    <strong><?php echo htmlspecialchars($cluster_name); ?></strong>
                                </div>
                                <div class="supervisor-summary-item">
                                    <span>Room</span>
                                    <strong><?php echo htmlspecialchars($supervisor_room ?: '-'); ?></strong>
                                </div>
                                <div class="supervisor-summary-item">
                                    <span>Total Labs</span>
                                    <strong><?php echo htmlspecialchars((string) $labs_pagination['total_items']); ?></strong>
                                </div>
                            </div>
                            <div class="supervisor-profile-labs">
                                <div class="section-header supervisor-labs-header">
                                    <div>
                                        <h2>Assigned Labs</h2>
                                    </div>
                                </div>
                                <?php if (empty($labs)): ?>
                                    <div class="empty-state">No labs found for this supervisor.</div>
                                <?php else: ?>
                                    <div class="lab-grid lab-grid-embedded">
                                        <?php foreach ($labs as $index => $lab): ?>
                                            <div class="lab-card">
                                                <div class="lab-card-top">
                                                    <div>
                                                        <p class="muted-text">Lab <?php echo (int) ((($labs_pagination['current_page'] - 1) * $labs_pagination['per_page']) + $index + 1); ?></p>
                                                        <h3><?php echo htmlspecialchars($lab['lab_name']); ?></h3>
                                                        <p><?php echo htmlspecialchars($lab['cluster_name'] ?? $cluster_name); ?></p>
                                                    </div>
                                                    <div class="lab-card-status">
                                                        <?php if (($lab['maintenance_status'] ?? 'available') === 'maintenance'): ?>
                                                            <span class="badge">Under maintenance</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-success">Available</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="lab-card-body">
                                                    <div class="lab-meta">
                                                        <div><span>Capacity:</span> <?php echo htmlspecialchars((string) $lab['lab_capacity']); ?></div>
                                                        <div><span>Status:</span> <?php echo htmlspecialchars(($lab['maintenance_status'] ?? 'available') === 'maintenance' ? 'Under maintenance' : 'Available'); ?></div>
                                                    </div>
                                                    <?php if (($lab['maintenance_status'] ?? 'available') === 'maintenance'): ?>
                                                        <div class="lab-maintenance-note">
                                                            <?php echo htmlspecialchars(get_lab_maintenance_period_label($lab['maintenance_start_date'] ?? null, $lab['maintenance_end_date'] ?? null)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="lab-card-description"><?php echo htmlspecialchars($lab['lab_description'] ?: 'Maklumat terperinci belum disediakan.'); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($active_tab === 'history'): ?>
                        <div class="card history-section-card">
                            <div class="section-header">
                                <div>
                                    <h2>Lab History</h2>
                                </div>
                            </div>
                            <div class="lab-history-grid">
                                <?php foreach ($labs as $index => $lab): ?>
                                    <?php $history_rows = $lab_history_map[(int) $lab['lab_id']] ?? []; ?>
                                    <div class="lab-history-card">
                                        <div class="lab-history-header">
                                            <div>
                                                <p class="muted-text">Lab <?php echo (int) ((($labs_pagination['current_page'] - 1) * $labs_pagination['per_page']) + $index + 1); ?></p>
                                                <h3><?php echo htmlspecialchars($lab['lab_name']); ?></h3>
                                            </div>
                                            <span class="badge badge-secondary"><?php echo count($history_rows) > 0 ? count($history_rows) . ' record' . (count($history_rows) === 1 ? '' : 's') : 'No history'; ?></span>
                                        </div>
                                        <div class="lab-history-list">
                                            <?php if (empty($history_rows)): ?>
                                                <div class="lab-history-empty">No history yet for this lab.</div>
                                            <?php else: ?>
                                                <?php foreach ($history_rows as $history): ?>
                                                    <?php $is_current = empty($history['ended_at']); ?>
                                                    <div class="lab-history-item<?php echo $is_current ? ' is-current' : ''; ?>">
                                                        <div class="lab-history-item-header">
                                                            <strong><?php echo htmlspecialchars($history['current_supervisor_name'] ?: 'Unassigned'); ?></strong>
                                                            <span class="badge <?php echo $is_current ? 'badge-success' : 'badge-secondary'; ?>">
                                                                <?php echo $is_current ? 'Current' : 'Previous'; ?>
                                                            </span>
                                                        </div>
                                                        <div class="lab-history-meta">
                                                            <div><span>Previous supervisor:</span> <?php echo htmlspecialchars($history['previous_supervisor_name'] ?: '-'); ?></div>
                                                            <div><span>From:</span> <?php echo htmlspecialchars(format_history_date($history['started_at'] ?? null)); ?></div>
                                                            <div><span>To:</span> <?php echo $is_current ? 'Current' : htmlspecialchars(format_history_date($history['ended_at'] ?? null)); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($labs_pagination['total_pages'] > 1): ?>
                        <div class="pagination">
                            <?php
                            $prev_page = max(1, $labs_pagination['current_page'] - 1);
                            $next_page = min($labs_pagination['total_pages'], $labs_pagination['current_page'] + 1);
                            ?>
                            <a class="btn ghost small<?php echo $labs_pagination['current_page'] <= 1 ? ' is-disabled' : ''; ?>" href="lab-management-supervisor.php?cluster=<?php echo (int) $cluster_id; ?>&supervisor=<?php echo urlencode($supervisor_param); ?>&tab=<?php echo urlencode($active_tab); ?>&page=<?php echo (int) $prev_page; ?>">Previous</a>
                            <div class="pagination-status">Page <?php echo (int) $labs_pagination['current_page']; ?> of <?php echo (int) $labs_pagination['total_pages']; ?></div>
                            <a class="btn ghost small<?php echo $labs_pagination['current_page'] >= $labs_pagination['total_pages'] ? ' is-disabled' : ''; ?>" href="lab-management-supervisor.php?cluster=<?php echo (int) $cluster_id; ?>&supervisor=<?php echo urlencode($supervisor_param); ?>&tab=<?php echo urlencode($active_tab); ?>&page=<?php echo (int) $next_page; ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>

                <footer class="footer">Ac Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <?php if ($can_edit_labs): ?>
        <div class="modal" id="edit-lab-modal">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_lab_details">
                    <input type="hidden" name="lab_id" id="edit-lab-id">
                    <div class="modal-header">
                        <h2>Edit Lab</h2>
                        <button class="icon-button" type="button" data-close="edit-lab-modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <label for="edit-lab-name">Lab Name</label>
                            <input id="edit-lab-name" name="lab_name" type="text" required>
                        </div>
                        <div>
                            <label for="edit-lab-capacity">Capacity</label>
                            <input id="edit-lab-capacity" name="lab_capacity" type="number" min="0">
                        </div>
                        <div>
                            <label for="edit-lab-description">Description</label>
                            <textarea id="edit-lab-description" name="lab_description" rows="3"></textarea>
                        </div>
                        <div>
                            <label for="edit-maintenance-status">Status</label>
                            <select id="edit-maintenance-status" name="maintenance_status">
                                <option value="available">Available</option>
                                <option value="maintenance">Under Maintenance</option>
                            </select>
                        </div>
                        <div>
                            <label for="edit-maintenance-start">Maintenance Start Date</label>
                            <input id="edit-maintenance-start" name="maintenance_start_date" type="date" min="<?php echo htmlspecialchars($today_date); ?>">
                        </div>
                        <div>
                            <label for="edit-maintenance-end">Maintenance End Date</label>
                            <input id="edit-maintenance-end" name="maintenance_end_date" type="date" min="<?php echo htmlspecialchars($today_date); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn ghost" type="button" data-close="edit-lab-modal">Cancel</button>
                        <button class="btn primary" type="submit">Save</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
    </script>
    <script src="assets/app.js?v=<?php echo (int) (@filemtime(__DIR__ . '/assets/app.js') ?: time()); ?>"></script>
    <?php if ($can_edit_labs): ?>
    <script>
        (function () {
            var modal = document.getElementById('edit-lab-modal');
            var today = '<?php echo $today_js; ?>';
            var maintenanceStart = document.getElementById('edit-maintenance-start');
            var maintenanceEnd = document.getElementById('edit-maintenance-end');

            function openModal() {
                if (modal) {
                    modal.classList.add('active');
                }
            }

            function closeModal() {
                if (modal) {
                    modal.classList.remove('active');
                }
            }

            function normalizeMaintenanceDates() {
                if (!maintenanceStart || !maintenanceEnd) {
                    return;
                }

                maintenanceStart.min = today;
                maintenanceEnd.min = maintenanceStart.value && maintenanceStart.value > today ? maintenanceStart.value : today;

                if (maintenanceStart.value && maintenanceStart.value < today) {
                    maintenanceStart.value = '';
                }

                if (maintenanceEnd.value && maintenanceEnd.value < today) {
                    maintenanceEnd.value = '';
                }

                if (maintenanceStart.value) {
                    maintenanceEnd.min = maintenanceStart.value;
                }

                if (maintenanceEnd.value && maintenanceStart.value && maintenanceEnd.value < maintenanceStart.value) {
                    maintenanceEnd.value = maintenanceStart.value;
                }
            }

            document.querySelectorAll('.edit-lab').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('edit-lab-id').value = button.getAttribute('data-lab-id') || '';
                    document.getElementById('edit-lab-name').value = button.getAttribute('data-lab-name') || '';
                    document.getElementById('edit-lab-capacity').value = button.getAttribute('data-lab-capacity') || 0;
                    document.getElementById('edit-lab-description').value = button.getAttribute('data-lab-description') || '';
                    document.getElementById('edit-maintenance-status').value = button.getAttribute('data-maintenance-status') || 'available';
                    document.getElementById('edit-maintenance-start').value = button.getAttribute('data-maintenance-start') || '';
                    document.getElementById('edit-maintenance-end').value = button.getAttribute('data-maintenance-end') || '';
                    normalizeMaintenanceDates();
                    openModal();
                });
            });

            if (maintenanceStart) {
                maintenanceStart.addEventListener('change', normalizeMaintenanceDates);
            }

            if (maintenanceEnd) {
                maintenanceEnd.addEventListener('change', normalizeMaintenanceDates);
            }

            document.querySelectorAll('[data-close="edit-lab-modal"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal();
                });
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
