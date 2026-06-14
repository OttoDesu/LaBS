<?php
require_once __DIR__ . '/init.php';
require_login();

$user_type = $_SESSION['user_type'] ?? 'public';
require_management();
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$can_manage_clusters = !$is_super_admin;
$today_date = date('Y-m-d');

if (!$is_super_admin && !$is_lab_supervisor && $admin_cluster_id) {
    header('Location: lab-management-cluster.php?cluster=' . (int) $admin_cluster_id);
    exit;
}

$lab_scope_ids = [];
if ($is_lab_supervisor) {
    $lab_scope_ids = get_lab_supervisor_lab_ids($mysqli, $user_id);
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

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($is_super_admin) {
        $errors[] = 'Super admins can only view Lab Management.';
    }
    if ($is_lab_supervisor) {
        if ($action === 'add_lab') {
            $cluster_id = (int) ($_POST['cluster_id'] ?? 0);
            $name = trim($_POST['lab_name'] ?? '');
            $capacity = (int) ($_POST['lab_capacity'] ?? 0);
            $description = trim($_POST['lab_description'] ?? '');
            if ($cluster_id <= 0 || $name === '') {
                $errors[] = 'Lab name and cluster are required.';
            }
            if ($lab_scope_ids) {
                $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
                $types = str_repeat('i', count($lab_scope_ids));
                $stmt = $mysqli->prepare("
                    SELECT DISTINCT cluster_id
                    FROM labs
                    WHERE lab_id IN ($placeholders)
                ");
                $stmt->bind_param($types, ...$lab_scope_ids);
                $stmt->execute();
                $result = $stmt->get_result();
                $allowed_clusters = [];
                while ($row = $result->fetch_assoc()) {
                    $allowed_clusters[] = (int) $row['cluster_id'];
                }
                $stmt->close();
                if (!in_array($cluster_id, $allowed_clusters, true)) {
                    $errors[] = 'You do not have access to that cluster.';
                }
            } else {
                $errors[] = 'No lab scope assigned.';
            }
            if (!$errors) {
                $stmt = $mysqli->prepare('
                    INSERT INTO labs (cluster_id, supervisor_id, lab_name, lab_description, lab_capacity, created_at, updated_at)
                    VALUES (?, NULL, ?, ?, ?, NOW(), NOW())
                ');
                $stmt->bind_param('issi', $cluster_id, $name, $description, $capacity);
                if ($stmt->execute()) {
                    $new_lab_id = $stmt->insert_id;
                    $stmt->close();
                    $scope_stmt = $mysqli->prepare('INSERT IGNORE INTO lab_supervisor_labs (user_id, lab_id, created_at) VALUES (?, ?, NOW())');
                    $scope_stmt->bind_param('ii', $user_id, $new_lab_id);
                    $scope_stmt->execute();
                    $scope_stmt->close();
                    set_flash('info', 'Lab added successfully.');
                    header('Location: lab-management.php');
                    exit;
                }
                $stmt->close();
                $errors[] = 'Unable to add lab.';
            }
        }
        if ($action === 'update_lab') {
            $lab_id = (int) ($_POST['lab_id'] ?? 0);
            $name = trim($_POST['lab_name'] ?? '');
            $capacity = (int) ($_POST['lab_capacity'] ?? 0);
            $description = trim($_POST['lab_description'] ?? '');
            $maintenance = normalize_lab_maintenance_input($_POST, $errors);
            if ($lab_id <= 0 || $name === '') {
                $errors[] = 'Lab name is required.';
            }
            if ($lab_id > 0 && !in_array($lab_id, $lab_scope_ids, true)) {
                $errors[] = 'You do not have access to that lab.';
            }
            if (!$errors) {
                $stmt = $mysqli->prepare('
                    UPDATE labs
                    SET lab_name = ?, lab_capacity = ?, lab_description = ?, maintenance_status = ?, maintenance_start_date = ?, maintenance_end_date = ?, updated_at = NOW()
                    WHERE lab_id = ?
                ');
                $stmt->bind_param('sissssi', $name, $capacity, $description, $maintenance['status'], $maintenance['start_date'], $maintenance['end_date'], $lab_id);
                if ($stmt->execute()) {
                    $stmt->close();
                    set_flash('info', 'Lab updated successfully.');
                    header('Location: lab-management.php');
                    exit;
                }
                $stmt->close();
                $errors[] = 'Unable to update lab.';
            }
        }
    }
    if ($action === 'add_cluster') {
        if (!$is_super_admin) {
            $errors[] = 'Only super admins can add clusters.';
        }
        $name = trim($_POST['cluster_name'] ?? '');
        $description = trim($_POST['cluster_description'] ?? '');
        if ($name === '') {
            $errors[] = 'Cluster name is required.';
        }
        if (!$errors) {
            $stmt = $mysqli->prepare('INSERT INTO clusters (cluster_name, cluster_description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
            $stmt->bind_param('ss', $name, $description);
            $stmt->execute();
            $stmt->close();
            set_flash('info', 'Cluster added successfully.');
            header('Location: lab-management.php');
            exit;
        }
    }

    if ($action === 'update_cluster') {
        if (!$is_super_admin) {
            $errors[] = 'Only super admins can edit clusters.';
        }
        $cluster_id = (int) ($_POST['cluster_id'] ?? 0);
        $name = trim($_POST['cluster_name'] ?? '');
        $description = trim($_POST['cluster_description'] ?? '');
        if ($cluster_id <= 0 || $name === '') {
            $errors[] = 'Cluster name is required.';
        }
        if (!$errors) {
            $stmt = $mysqli->prepare('UPDATE clusters SET cluster_name = ?, cluster_description = ?, updated_at = NOW() WHERE cluster_id = ?');
            $stmt->bind_param('ssi', $name, $description, $cluster_id);
            $stmt->execute();
            $stmt->close();
            set_flash('info', 'Cluster updated successfully.');
            header('Location: lab-management.php');
            exit;
        }
    }

    if ($action === 'add_supervisor') {
        $cluster_id = (int) ($_POST['cluster_id'] ?? 0);
        $name = trim($_POST['supervisor_name'] ?? '');
        $email = trim($_POST['supervisor_email'] ?? '');
        if ($cluster_id <= 0 || $name === '' || $name === 'Unassigned') {
            $errors[] = 'Supervisor name is required.';
        }
        if (!$errors) {
            $email_value = $email !== '' ? $email : null;
            $stmt = $mysqli->prepare('
                INSERT INTO supervisors (cluster_id, supervisor_name, supervisor_email, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE supervisor_email = VALUES(supervisor_email), updated_at = NOW()
            ');
            $stmt->bind_param('iss', $cluster_id, $name, $email_value);
            $stmt->execute();
            $stmt->close();
            set_flash('info', 'Supervisor added successfully.');
            header('Location: lab-management.php');
            exit;
        }
    }
}

$clusters_raw = [];
$cluster_payload = [];
if (!$is_lab_supervisor) {
    if ($is_super_admin) {
        $cluster_stmt = $mysqli->prepare('SELECT cluster_id, cluster_name, cluster_description FROM clusters ORDER BY cluster_name ASC');
        $cluster_stmt->execute();
    } else {
        $cluster_stmt = $mysqli->prepare('SELECT cluster_id, cluster_name, cluster_description FROM clusters WHERE cluster_id = ? ORDER BY cluster_name ASC');
        $cluster_stmt->bind_param('i', $admin_cluster_id);
        $cluster_stmt->execute();
    }
    $cluster_result = $cluster_stmt->get_result();
    while ($row = $cluster_result->fetch_assoc()) {
        $clusters_raw[] = $row;
    }
    $cluster_stmt->close();

    $lab_counts = [];
    if ($is_super_admin) {
        $lab_stmt = $mysqli->prepare('
            SELECT cluster_id,
                   COUNT(*) AS lab_count,
                   SUM(CASE WHEN supervisor_id IS NULL THEN 1 ELSE 0 END) AS unassigned_count
            FROM labs
            GROUP BY cluster_id
        ');
        $lab_stmt->execute();
    } else {
        $lab_stmt = $mysqli->prepare('
            SELECT cluster_id,
                   COUNT(*) AS lab_count,
                   SUM(CASE WHEN supervisor_id IS NULL THEN 1 ELSE 0 END) AS unassigned_count
            FROM labs
            WHERE cluster_id = ?
            GROUP BY cluster_id
        ');
        $lab_stmt->bind_param('i', $admin_cluster_id);
        $lab_stmt->execute();
    }
    $lab_result = $lab_stmt->get_result();
    while ($row = $lab_result->fetch_assoc()) {
        $lab_counts[(int) $row['cluster_id']] = [
            'lab_count' => (int) $row['lab_count'],
            'unassigned_count' => (int) $row['unassigned_count']
        ];
    }
    $lab_stmt->close();

    $supervisor_counts = [];
    if ($is_super_admin) {
        $supervisor_stmt = $mysqli->prepare('SELECT cluster_id, COUNT(*) AS supervisor_count FROM supervisors GROUP BY cluster_id');
        $supervisor_stmt->execute();
    } else {
        $supervisor_stmt = $mysqli->prepare('SELECT cluster_id, COUNT(*) AS supervisor_count FROM supervisors WHERE cluster_id = ? GROUP BY cluster_id');
        $supervisor_stmt->bind_param('i', $admin_cluster_id);
        $supervisor_stmt->execute();
    }
    $supervisor_result = $supervisor_stmt->get_result();
    while ($row = $supervisor_result->fetch_assoc()) {
        $supervisor_counts[(int) $row['cluster_id']] = (int) $row['supervisor_count'];
    }
    $supervisor_stmt->close();

    foreach ($clusters_raw as $cluster) {
        $cluster_id = (int) $cluster['cluster_id'];
        $lab_count = $lab_counts[$cluster_id]['lab_count'] ?? 0;
        $unassigned_count = $lab_counts[$cluster_id]['unassigned_count'] ?? 0;
        $supervisor_count = $supervisor_counts[$cluster_id] ?? 0;
        if ($unassigned_count > 0) {
            $supervisor_count += 1;
        }
        $cluster_payload[] = [
            'id' => $cluster_id,
            'name' => $cluster['cluster_name'],
            'description' => $cluster['cluster_description'],
            'supervisorCount' => $supervisor_count,
            'labCount' => $lab_count
        ];
    }
}

$lab_scope_labs = [];
$allowed_clusters = [];
if ($is_lab_supervisor && $lab_scope_ids) {
    $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
    $types = str_repeat('i', count($lab_scope_ids));
    $stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, l.lab_description, l.lab_capacity,
               l.maintenance_status, l.maintenance_start_date, l.maintenance_end_date,
               c.cluster_id, c.cluster_name
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        WHERE l.lab_id IN (' . $placeholders . ')
        ORDER BY c.cluster_name ASC, l.lab_name ASC
    ');
    $stmt->bind_param($types, ...$lab_scope_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lab_scope_labs[] = $row;
        $allowed_clusters[(int) $row['cluster_id']] = $row['cluster_name'];
    }
    $stmt->close();
}

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$export_params = [
    'type' => 'labs'
];
$layout_path = __DIR__ . '/templates/layouts/admin.php';
if ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
}
$layout = require $layout_path;
$active = 'lab-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Management</title>
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
                        <h1><?php echo $is_lab_supervisor ? 'My Labs' : 'Lab Management'; ?></h1>
                        <p><?php echo $is_lab_supervisor ? 'Manage labs assigned to you.' : 'Explore labs grouped by cluster and supervisor.'; ?></p>
                    </div>
                    <div class="breadcrumb">Home / Lab Management</div>
                </div>

                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_lab_supervisor): ?>
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2>Lab Scope</h2>
                                <p><?php echo $lab_scope_labs ? 'Select a lab card below to focus on the lab you manage.' : 'Manage labs assigned to your supervisor scope.'; ?></p>
                            </div>
                            <div class="banner-links">
                                <a class="btn ghost" href="management-export.php?<?php echo htmlspecialchars(http_build_query($export_params)); ?>">Export Excel</a>
                            </div>
                        </div>
                        <?php if (!$lab_scope_labs): ?>
                            <p class="muted-text">No labs assigned to your scope yet.</p>
                        <?php else: ?>
                            <div class="lab-grid">
                                <?php foreach ($lab_scope_labs as $index => $lab): ?>
                                    <div class="lab-card">
                                        <p class="muted-text">Lab <?php echo (int) ($index + 1); ?></p>
                                        <span class="badge"><?php echo htmlspecialchars($lab['cluster_name']); ?></span>
                                        <h3><?php echo htmlspecialchars($lab['lab_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($lab['lab_description'] ?: 'No description provided.'); ?></p>
                                        <div class="directory-meta">
                                            <span>Capacity: <?php echo htmlspecialchars((string) $lab['lab_capacity']); ?></span>
                                            <span><?php echo ($lab['maintenance_status'] ?? 'available') === 'maintenance' ? 'Maintenance' : 'Available'; ?></span>
                                        </div>
                                        <div class="card-actions">
                                            <a class="btn ghost small" href="assets-management-lab.php?lab=<?php echo (int) $lab['lab_id']; ?>">View Assets</a>
                                            <?php if ($is_lab_supervisor): ?>
                                                <button
                                                    class="btn primary small edit-lab"
                                                    type="button"
                                                    data-lab-id="<?php echo (int) $lab['lab_id']; ?>"
                                                    data-lab-name="<?php echo htmlspecialchars($lab['lab_name']); ?>"
                                                    data-lab-capacity="<?php echo htmlspecialchars((string) $lab['lab_capacity']); ?>"
                                                    data-lab-description="<?php echo htmlspecialchars($lab['lab_description']); ?>"
                                                    data-maintenance-status="<?php echo htmlspecialchars($lab['maintenance_status'] ?? 'available'); ?>"
                                                    data-maintenance-start="<?php echo htmlspecialchars((string) ($lab['maintenance_start_date'] ?? '')); ?>"
                                                    data-maintenance-end="<?php echo htmlspecialchars((string) ($lab['maintenance_end_date'] ?? '')); ?>"
                                                >
                                                    Edit Lab
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                <div class="section-stack">
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2>Clusters</h2>
                                <p>Select a cluster to view supervisors.</p>
                            </div>
                            <div class="banner-links">
                                <a class="btn ghost" href="management-export.php?<?php echo htmlspecialchars(http_build_query($export_params)); ?>">Export Excel</a>
                                <?php if (!$is_super_admin && $admin_cluster_id): ?>
                                    <a class="btn ghost" href="lab-management-cluster.php?cluster=<?php echo (int) $admin_cluster_id; ?>">Open Supervisors</a>
                                <?php endif; ?>
                                <?php if ($can_manage_clusters): ?>
                                    <button class="btn primary" type="button" data-modal="add-cluster-modal">Add Cluster</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="cluster-grid cluster-grid-two-rows">
                        <?php foreach ($cluster_payload as $cluster): ?>
                            <div class="cluster-card clickable" data-href="lab-management-cluster.php?cluster=<?php echo (int) $cluster['id']; ?>">
                                <div class="cluster-card-header">
                                    <div>
                                        <h3><?php echo htmlspecialchars($cluster['name']); ?></h3>
                                        <p><?php echo htmlspecialchars($cluster['description']); ?></p>
                                    </div>
                                    <?php if ($can_manage_clusters): ?>
                                        <div class="card-actions">
                                            <button class="btn ghost small" type="button" data-action="edit-cluster" data-cluster-id="<?php echo (int) $cluster['id']; ?>" data-cluster-name="<?php echo htmlspecialchars($cluster['name']); ?>" data-cluster-description="<?php echo htmlspecialchars($cluster['description']); ?>">Edit</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="cluster-meta">
                                    <?php echo htmlspecialchars((string) $cluster['supervisorCount']); ?> supervisors · <?php echo htmlspecialchars((string) $cluster['labCount']); ?> labs
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <footer class="footer">&copy; Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <?php if ($is_lab_supervisor): ?>
        <div class="modal" id="add-lab-modal">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="add_lab">
                    <div class="modal-header">
                        <h2>Add Lab</h2>
                        <button class="icon-button" type="button" data-close="add-lab-modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <label for="add-lab-cluster">Cluster</label>
                            <select id="add-lab-cluster" name="cluster_id" required>
                                <option value="">Select cluster</option>
                                <?php foreach ($allowed_clusters as $cluster_id => $cluster_name): ?>
                                    <option value="<?php echo (int) $cluster_id; ?>">
                                        <?php echo htmlspecialchars($cluster_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="add-lab-name">Lab Name</label>
                            <input id="add-lab-name" name="lab_name" type="text" required>
                        </div>
                        <div>
                            <label for="add-lab-capacity">Capacity</label>
                            <input id="add-lab-capacity" name="lab_capacity" type="number" min="0">
                        </div>
                        <div>
                            <label for="add-lab-description">Description</label>
                            <textarea id="add-lab-description" name="lab_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn ghost" type="button" data-close="add-lab-modal">Cancel</button>
                        <button class="btn primary" type="submit">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="edit-lab-modal">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_lab">
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
                            <label for="edit-maintenance-status">Maintenance Status</label>
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

    <div class="modal" id="add-cluster-modal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_cluster">
                <div class="modal-header">
                    <h2>Add Cluster</h2>
                    <button class="icon-button" type="button" data-close="add-cluster-modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="add-cluster-name">Cluster Name</label>
                        <input id="add-cluster-name" name="cluster_name" type="text" required>
                    </div>
                    <div>
                        <label for="add-cluster-description">Description</label>
                        <textarea id="add-cluster-description" name="cluster_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="add-cluster-modal">Cancel</button>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="edit-cluster-modal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_cluster">
                <input type="hidden" name="cluster_id" id="edit-cluster-id">
                <div class="modal-header">
                    <h2>Edit Cluster</h2>
                    <button class="icon-button" type="button" data-close="edit-cluster-modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="edit-cluster-name">Cluster Name</label>
                        <input id="edit-cluster-name" name="cluster_name" type="text" required>
                    </div>
                    <div>
                        <label for="edit-cluster-description">Description</label>
                        <textarea id="edit-cluster-description" name="cluster_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="edit-cluster-modal">Cancel</button>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
    </script>
    <script src="assets/app.js?v=<?php echo (int) (@filemtime(__DIR__ . '/assets/app.js') ?: time()); ?>"></script>
    <?php if ($is_lab_supervisor): ?>
        <script>
            (function () {
                var editModal = document.getElementById('edit-lab-modal');
                var addLabModal = document.getElementById('add-lab-modal');
                var maintenanceStart = document.getElementById('edit-maintenance-start');
                var maintenanceEnd = document.getElementById('edit-maintenance-end');
                var today = '<?php echo htmlspecialchars($today_date, ENT_QUOTES); ?>';

                function syncMaintenanceDates() {
                    if (!maintenanceStart || !maintenanceEnd) {
                        return;
                    }

                    maintenanceStart.min = today;
                    maintenanceEnd.min = maintenanceStart.value || today;

                    if (maintenanceStart.value && maintenanceStart.value < today) {
                        maintenanceStart.value = today;
                    }
                    if (maintenanceEnd.value && maintenanceEnd.value < maintenanceEnd.min) {
                        maintenanceEnd.value = maintenanceEnd.min;
                    }
                }

                if (maintenanceStart && maintenanceEnd) {
                    maintenanceStart.addEventListener('change', syncMaintenanceDates);
                    maintenanceEnd.addEventListener('change', syncMaintenanceDates);
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
                        syncMaintenanceDates();
                        if (editModal) {
                            editModal.classList.add('active');
                        }
                    });
                });

                document.querySelectorAll('[data-modal="add-lab-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (addLabModal) {
                            addLabModal.classList.add('active');
                        }
                    });
                });

                document.querySelectorAll('[data-close="edit-lab-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (editModal) {
                            editModal.classList.remove('active');
                        }
                    });
                });

                document.querySelectorAll('[data-close="add-lab-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (addLabModal) {
                            addLabModal.classList.remove('active');
                        }
                    });
                });
            })();
        </script>
    <?php else: ?>
        <script>
            (function () {
                var editModal = document.getElementById('edit-cluster-modal');
                var addClusterModal = document.getElementById('add-cluster-modal');

                document.querySelectorAll('.cluster-card.clickable').forEach(function (card) {
                    card.addEventListener('click', function (event) {
                        if (event.target.closest('button') || event.target.closest('a')) {
                            return;
                        }
                        var href = card.getAttribute('data-href');
                        if (href) {
                            window.location.href = href;
                        }
                    });
                });

                document.querySelectorAll('[data-action="edit-cluster"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        document.getElementById('edit-cluster-id').value = button.getAttribute('data-cluster-id') || '';
                        document.getElementById('edit-cluster-name').value = button.getAttribute('data-cluster-name') || '';
                        document.getElementById('edit-cluster-description').value = button.getAttribute('data-cluster-description') || '';
                        if (editModal) {
                            editModal.classList.add('active');
                        }
                    });
                });

                document.querySelectorAll('[data-modal="add-cluster-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (addClusterModal) {
                            addClusterModal.classList.add('active');
                        }
                    });
                });

                document.querySelectorAll('[data-close="edit-cluster-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (editModal) {
                            editModal.classList.remove('active');
                        }
                    });
                });

                document.querySelectorAll('[data-close="add-cluster-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (addClusterModal) {
                            addClusterModal.classList.remove('active');
                        }
                    });
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
