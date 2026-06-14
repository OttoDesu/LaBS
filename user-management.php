<?php
require_once __DIR__ . '/init.php';
require_login();

$user_type = $_SESSION['user_type'] ?? 'public';
require_management();
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$is_cluster_admin = is_cluster_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$can_manage_users = !$is_lab_supervisor;
$show_action_column = !$is_super_admin && !$is_cluster_admin;
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);

$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? 'all';
$cluster_filter = (int) ($_GET['cluster'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page_options = [10, 25, 50, 100];
$per_page = (int) ($_GET['per_page'] ?? 10);
if (!in_array($per_page, $per_page_options, true)) {
    $per_page = 10;
}
$errors = [];
$flash_info = get_flash('info');
$show_add_modal = false;
$add_name = '';
$add_email = '';
$add_phone = '';
$add_ic = '';
$add_role = 'public';
$add_cluster_id = $admin_cluster_id ?? 0;

$clusters = [];
if ($is_super_admin) {
    $cluster_stmt = $mysqli->prepare('SELECT cluster_id, cluster_name FROM clusters ORDER BY cluster_name ASC');
    $cluster_stmt->execute();
} else {
    $cluster_stmt = $mysqli->prepare('SELECT cluster_id, cluster_name FROM clusters WHERE cluster_id = ? ORDER BY cluster_name ASC');
    $cluster_stmt->bind_param('i', $admin_cluster_id);
    $cluster_stmt->execute();
}
$cluster_result = $cluster_stmt->get_result();
while ($row = $cluster_result->fetch_assoc()) {
    $clusters[] = $row;
}
$cluster_stmt->close();
if ($is_super_admin && $cluster_filter !== 0) {
    $cluster_ids = array_map(static function ($cluster) {
        return (int) $cluster['cluster_id'];
    }, $clusters);
    if (!in_array($cluster_filter, $cluster_ids, true)) {
        $cluster_filter = 0;
    }
}

$visible_cluster_ids = [];
if ($is_super_admin) {
    foreach ($clusters as $cluster) {
        $visible_cluster_ids[] = (int) $cluster['cluster_id'];
    }
} elseif ($admin_cluster_id) {
    $visible_cluster_ids[] = (int) $admin_cluster_id;
} elseif ($is_lab_supervisor) {
    $lab_scope_ids = get_lab_supervisor_lab_ids($mysqli, $session_user_id);
    if ($lab_scope_ids) {
        $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
        $types = str_repeat('i', count($lab_scope_ids));
        $scope_cluster_stmt = $mysqli->prepare("
            SELECT DISTINCT cluster_id
            FROM labs
            WHERE lab_id IN ($placeholders)
            ORDER BY cluster_id ASC
        ");
        $scope_cluster_stmt->bind_param($types, ...$lab_scope_ids);
        $scope_cluster_stmt->execute();
        $scope_cluster_result = $scope_cluster_stmt->get_result();
        while ($row = $scope_cluster_result->fetch_assoc()) {
            $visible_cluster_ids[] = (int) $row['cluster_id'];
        }
        $scope_cluster_stmt->close();
    }
}

$labs = [];
if ($is_super_admin) {
    $lab_stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, c.cluster_name
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        ORDER BY c.cluster_name ASC, l.lab_name ASC
    ');
    $lab_stmt->execute();
    $lab_result = $lab_stmt->get_result();
    while ($row = $lab_result->fetch_assoc()) {
        $labs[] = $row;
    }
    $lab_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_manage_users) {
        $errors[] = 'Lab supervisors can only view user information.';
    }

    $action = $_POST['action'] ?? '';
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $ic_no = trim($_POST['ic_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type_input = $_POST['user_type'] ?? 'public';
    $cluster_id_input = (int) ($_POST['cluster_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($phone !== '' && !preg_match('/^\d{9,12}$/', $phone)) {
        $errors[] = 'Phone number must be 9 to 12 digits.';
    }
    if ($ic_no !== '' && !preg_match('/^\d{12}$/', $ic_no)) {
        $errors[] = 'IC number must be 12 digits.';
    }

    if ($action === 'add_user') {
        $add_name = $name;
        $add_email = $email;
        $add_phone = $phone;
        $add_ic = $ic_no;
        $add_role = $user_type_input;
        $add_cluster_id = $cluster_id_input;
        if (!$is_super_admin) {
            $cluster_id_input = $admin_cluster_id ?? 0;
            $add_cluster_id = $cluster_id_input;
        }
        $allowed_roles = $is_super_admin
            ? ['public', 'uthm_student', 'uthm_staff', 'cluster_admin', 'lab_supervisor', 'super_admin', 'admin']
            : ['public', 'uthm_student', 'uthm_staff'];
        if (!in_array($user_type_input, $allowed_roles, true)) {
            $errors[] = 'User role is required.';
        }
        if (in_array($user_type_input, ['cluster_admin', 'uthm_student', 'uthm_staff'], true) && $cluster_id_input <= 0) {
            $errors[] = 'Cluster is required for that role.';
        }
        $lab_ids = array_filter(array_map('intval', $_POST['lab_ids'] ?? []));
        if ($user_type_input === 'lab_supervisor' && !$lab_ids) {
            $errors[] = 'Please select at least one lab for the supervisor scope.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Password must be at least 8 characters, include one uppercase letter, and one number.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors) {
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $errors[] = 'An account with this email already exists.';
            }
        }
        if ($errors) {
            $show_add_modal = true;
        }
        if (!$errors) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $cluster_id_value = $cluster_id_input > 0 ? $cluster_id_input : null;
            if ($user_type_input === 'super_admin' || $user_type_input === 'lab_supervisor') {
                $cluster_id_value = null;
            }
            $stmt = $mysqli->prepare('
                INSERT INTO users (name, email, phone, ic_no, password, user_type, cluster_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ');
            $stmt->bind_param('ssssssi', $name, $email, $phone, $ic_no, $hashed, $user_type_input, $cluster_id_value);
            if ($stmt->execute()) {
                set_flash('info', 'User added successfully.');
                if ($user_type_input === 'lab_supervisor' && $lab_ids) {
                    foreach ($lab_ids as $lab_id) {
                        $scope_stmt = $mysqli->prepare('INSERT IGNORE INTO lab_supervisor_labs (user_id, lab_id, created_at) VALUES (?, ?, NOW())');
                        $scope_stmt->bind_param('ii', $stmt->insert_id, $lab_id);
                        $scope_stmt->execute();
                        $scope_stmt->close();
                    }
                }
                $stmt->close();
                header('Location: user-management.php');
                exit;
            }
            $stmt->close();
            $errors[] = 'Unable to add user.';
            $show_add_modal = true;
        }
    }

    $target_role = null;
    if ($action === 'update_user' && $user_id > 0) {
        $stmt = $mysqli->prepare('SELECT user_type FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $target_role = $row['user_type'];
        }
        $stmt->close();
    }

    if (!$errors && $action === 'update_user' && $user_id > 0) {
        if (!$is_super_admin) {
            $stmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? AND cluster_id = ?');
            $stmt->bind_param('ii', $user_id, $admin_cluster_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $errors[] = 'You do not have permission to update that user.';
            }
        }
    }

    if (!$errors && $action === 'update_user' && $user_id > 0 && $is_super_admin && $target_role === 'lab_supervisor') {
        $lab_ids = array_filter(array_map('intval', $_POST['lab_ids'] ?? []));
        if (!$lab_ids) {
            $errors[] = 'Please select at least one lab for the supervisor scope.';
        }
    }

    if (!$errors && $action === 'update_user' && $user_id > 0) {
        if ($is_super_admin) {
            $cluster_id_value = $cluster_id_input > 0 ? $cluster_id_input : null;
            if ($target_role === 'lab_supervisor') {
                $cluster_id_value = null;
            }
            $stmt = $mysqli->prepare('UPDATE users SET name = ?, email = ?, phone = ?, ic_no = ?, cluster_id = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('ssssii', $name, $email, $phone, $ic_no, $cluster_id_value, $user_id);
        } else {
            $stmt = $mysqli->prepare('UPDATE users SET name = ?, email = ?, phone = ?, ic_no = ?, updated_at = NOW() WHERE id = ?');
            $stmt->bind_param('ssssi', $name, $email, $phone, $ic_no, $user_id);
        }
        if ($stmt->execute()) {
            set_flash('info', 'User updated successfully.');
            $stmt->close();
            if ($is_super_admin && $target_role === 'lab_supervisor') {
                $lab_ids = array_filter(array_map('intval', $_POST['lab_ids'] ?? []));
                $mysqli->query('DELETE FROM lab_supervisor_labs WHERE user_id = ' . (int) $user_id);
                foreach ($lab_ids as $lab_id) {
                    $scope_stmt = $mysqli->prepare('INSERT IGNORE INTO lab_supervisor_labs (user_id, lab_id, created_at) VALUES (?, ?, NOW())');
                    $scope_stmt->bind_param('ii', $user_id, $lab_id);
                    $scope_stmt->execute();
                    $scope_stmt->close();
                }
            }
            header('Location: user-management.php');
            exit;
        }
        $stmt->close();
        $errors[] = 'Unable to update user.';
    }

    if (!$errors && $action === '') {
        $errors[] = 'Invalid request.';
    }
}

$users = [];
$search_like = '%' . $search . '%';
if ($role_filter !== 'all' && !in_array($role_filter, ['public', 'uthm_student', 'uthm_staff', 'cluster_admin', 'lab_supervisor', 'super_admin', 'admin'], true)) {
    $role_filter = 'all';
}

if ($role_filter === 'all') {
    if ($is_super_admin) {
        if ($cluster_filter > 0) {
            $stmt = $mysqli->prepare('
                SELECT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, u.cluster_id, c.cluster_name
                FROM users u
                LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
                WHERE (u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?) AND u.cluster_id = ?
                ORDER BY u.id ASC
            ');
            $stmt->bind_param('sssi', $search_like, $search_like, $search_like, $cluster_filter);
        } else {
            $stmt = $mysqli->prepare('
                SELECT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, u.cluster_id, c.cluster_name
                FROM users u
                LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
                WHERE u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?
                ORDER BY u.id ASC
            ');
            $stmt->bind_param('sss', $search_like, $search_like, $search_like);
        }
    } else {
        if ($visible_cluster_ids) {
            $placeholders = implode(',', array_fill(0, count($visible_cluster_ids), '?'));
            $types = str_repeat('i', count($visible_cluster_ids));
            $stmt = $mysqli->prepare("
                SELECT DISTINCT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, u.cluster_id, c.cluster_name
                FROM users u
                LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
                LEFT JOIN lab_supervisor_labs lsl ON lsl.user_id = u.id
                LEFT JOIN labs ls ON ls.lab_id = lsl.lab_id
                WHERE (u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?)
                  AND (
                        u.user_type = 'super_admin'
                        OR u.user_type = 'public'
                        OR u.cluster_id IN ($placeholders)
                        OR (u.user_type = 'lab_supervisor' AND ls.cluster_id IN ($placeholders))
                  )
                ORDER BY
                    CASE
                        WHEN u.user_type = 'super_admin' THEN 0
                        WHEN u.user_type = 'cluster_admin' THEN 1
                        WHEN u.user_type = 'lab_supervisor' THEN 2
                        WHEN u.user_type = 'public' THEN 3
                        ELSE 4
                    END,
                    c.cluster_name ASC,
                    u.name ASC
            ");
            $params = array_merge([$search_like, $search_like, $search_like], $visible_cluster_ids, $visible_cluster_ids);
            $stmt->bind_param('sss' . $types . $types, ...$params);
        }
    }
} else {
    if ($is_super_admin) {
        if ($cluster_filter > 0) {
            $stmt = $mysqli->prepare('
                SELECT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, u.cluster_id, c.cluster_name
                FROM users u
                LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
                WHERE (u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?) AND u.user_type = ? AND u.cluster_id = ?
                ORDER BY u.id ASC
            ');
            $stmt->bind_param('ssssi', $search_like, $search_like, $search_like, $role_filter, $cluster_filter);
        } else {
            $stmt = $mysqli->prepare('
                SELECT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, u.cluster_id, c.cluster_name
                FROM users u
                LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
                WHERE (u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?) AND u.user_type = ?
                ORDER BY u.id ASC
            ');
            $stmt->bind_param('ssss', $search_like, $search_like, $search_like, $role_filter);
        }
    } else {
        if ($visible_cluster_ids) {
            $placeholders = implode(',', array_fill(0, count($visible_cluster_ids), '?'));
            $types = str_repeat('i', count($visible_cluster_ids));
            $stmt = $mysqli->prepare("
                SELECT DISTINCT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, u.cluster_id, c.cluster_name
                FROM users u
                LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
                LEFT JOIN lab_supervisor_labs lsl ON lsl.user_id = u.id
                LEFT JOIN labs ls ON ls.lab_id = lsl.lab_id
                WHERE (u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?)
                  AND u.user_type = ?
                  AND (
                        u.user_type = 'super_admin'
                        OR u.user_type = 'public'
                        OR u.cluster_id IN ($placeholders)
                        OR (u.user_type = 'lab_supervisor' AND ls.cluster_id IN ($placeholders))
                  )
                ORDER BY
                    c.cluster_name ASC,
                    u.name ASC
            ");
            $params = array_merge([$search_like, $search_like, $search_like, $role_filter], $visible_cluster_ids, $visible_cluster_ids);
            $stmt->bind_param('ssss' . $types . $types, ...$params);
        }
    }
}
$directory_groups = [
    'super_admin' => [],
    'cluster_admin' => [],
    'lab_supervisor' => [],
    'public' => []
];

if (isset($stmt) && $stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
        $row['can_edit'] = (
            $can_manage_users
            && !$is_super_admin
            && in_array($row['user_type'], ['public', 'uthm_student', 'uthm_staff'], true)
            && (int) ($row['cluster_id'] ?? 0) === (int) $admin_cluster_id
        );
    $users[] = $row;
}
    $stmt->close();
}

$total_users = count($users);
$total_pages = max(1, (int) ceil($total_users / $per_page));
$page = min($page, $total_pages);
$page_offset = ($page - 1) * $per_page;
$users = array_slice($users, $page_offset, $per_page);
$pagination_query = [
    'search' => $search,
    'role' => $role_filter,
    'cluster' => $cluster_filter,
    'per_page' => $per_page
];

$lab_scope_map = [];
if ($is_super_admin && $users) {
    $user_ids = array_map(static function ($user) {
        return (int) $user['id'];
    }, $users);
    $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
    $types = str_repeat('i', count($user_ids));
    $scope_stmt = $mysqli->prepare("
        SELECT user_id, GROUP_CONCAT(lab_id ORDER BY lab_id ASC) AS lab_ids
        FROM lab_supervisor_labs
        WHERE user_id IN ($placeholders)
        GROUP BY user_id
    ");
    $scope_stmt->bind_param($types, ...$user_ids);
    $scope_stmt->execute();
    $scope_result = $scope_stmt->get_result();
    while ($row = $scope_result->fetch_assoc()) {
        $lab_scope_map[(int) $row['user_id']] = $row['lab_ids'] ?? '';
    }
    $scope_stmt->close();
}

if ($is_super_admin) {
    $directory_stmt = $mysqli->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.user_type, u.cluster_id, c.cluster_name
        FROM users u
        LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
        WHERE u.user_type IN ('super_admin', 'cluster_admin', 'lab_supervisor', 'public')
        ORDER BY FIELD(u.user_type, 'super_admin', 'cluster_admin', 'lab_supervisor', 'public'), c.cluster_name ASC, u.name ASC
    ");
    $directory_stmt->execute();
} elseif ($visible_cluster_ids) {
    $placeholders = implode(',', array_fill(0, count($visible_cluster_ids), '?'));
    $types = str_repeat('i', count($visible_cluster_ids));
    $directory_stmt = $mysqli->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.phone, u.user_type, u.cluster_id, c.cluster_name
        FROM users u
        LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
        LEFT JOIN lab_supervisor_labs lsl ON lsl.user_id = u.id
        LEFT JOIN labs l ON l.lab_id = lsl.lab_id
        WHERE u.user_type = 'super_admin'
           OR (u.user_type IN ('cluster_admin', 'public') AND u.cluster_id IN ($placeholders))
           OR (u.user_type = 'lab_supervisor' AND l.cluster_id IN ($placeholders))
        ORDER BY FIELD(u.user_type, 'super_admin', 'cluster_admin', 'lab_supervisor', 'public'), c.cluster_name ASC, u.name ASC
    ");
    $params = array_merge($visible_cluster_ids, $visible_cluster_ids);
    $directory_stmt->bind_param($types . $types, ...$params);
    $directory_stmt->execute();
}

if (isset($directory_stmt) && $directory_stmt) {
    $directory_result = $directory_stmt->get_result();
    while ($row = $directory_result->fetch_assoc()) {
        $key = $row['user_type'];
        if (!isset($directory_groups[$key])) {
            $key = 'public';
        }
        $directory_groups[$key][] = $row;
    }
    $directory_stmt->close();
}

function role_label($user_type) {
    switch ($user_type) {
        case 'super_admin':
            return 'Admin';
        case 'cluster_admin':
            return 'Cluster Admin';
        case 'lab_supervisor':
            return 'Lab Supervisor';
        case 'uthm_student':
            return 'Student';
        case 'uthm_staff':
            return 'Staff';
        case 'admin':
            return 'Admin';
        case 'public':
        default:
            return 'Public User';
    }
}

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$export_params = [
    'type' => 'users',
    'search' => $search,
    'role' => $role_filter,
    'cluster' => $cluster_filter
];
$layout_path = __DIR__ . '/templates/layouts/admin.php';
$layout_path = $is_lab_supervisor ? __DIR__ . '/templates/layouts/lab_supervisor.php' : $layout_path;
$layout = require $layout_path;
$active = 'user-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
                        <h1>User Management</h1>
                        <p>View, filter, add, or edit platform users by user type.</p>
                    </div>
                    <div class="breadcrumb">Home / User Management</div>
                </div>

                <?php if ($flash_info): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($flash_info); ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="banner">
                        <div>
                            <h2>User Management</h2>
                            <p>Search users by name, email, or IC number.</p>
                        </div>
                        <div class="banner-links">
                            <a class="btn ghost" href="management-export.php?<?php echo htmlspecialchars(http_build_query($export_params)); ?>">Export Excel</a>
                            <?php if ($can_manage_users): ?>
                                <button class="btn ghost" type="button">Upload CSV</button>
                                <button class="btn primary" type="button" data-modal="add-user-modal">Add User</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form class="filters" method="GET" action="user-management.php">
                        <input type="text" name="search" placeholder="Search name, email, or IC" value="<?php echo htmlspecialchars($search); ?>">
                        <?php if ($is_super_admin): ?>
                            <select name="cluster">
                                <option value="0"<?php echo $cluster_filter === 0 ? ' selected' : ''; ?>>All clusters</option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?php echo (int) $cluster['cluster_id']; ?>"<?php echo $cluster_filter === (int) $cluster['cluster_id'] ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <select name="role">
                            <option value="all"<?php echo $role_filter === 'all' ? ' selected' : ''; ?>>All</option>
                            <option value="public"<?php echo $role_filter === 'public' ? ' selected' : ''; ?>>Public User</option>
                            <option value="uthm_student"<?php echo $role_filter === 'uthm_student' ? ' selected' : ''; ?>>Student</option>
                            <option value="uthm_staff"<?php echo $role_filter === 'uthm_staff' ? ' selected' : ''; ?>>Staff</option>
                            <?php if ($is_super_admin || $is_lab_supervisor): ?>
                                <option value="cluster_admin"<?php echo $role_filter === 'cluster_admin' ? ' selected' : ''; ?>>Cluster Admin</option>
                                <option value="lab_supervisor"<?php echo $role_filter === 'lab_supervisor' ? ' selected' : ''; ?>>Lab Supervisor</option>
                            <?php endif; ?>
                            <?php if ($is_super_admin): ?>
                                <option value="super_admin"<?php echo $role_filter === 'super_admin' ? ' selected' : ''; ?>>Admin</option>
                                <option value="admin"<?php echo $role_filter === 'admin' ? ' selected' : ''; ?>>Admin (Legacy)</option>
                            <?php endif; ?>
                        </select>
                        <select name="per_page" aria-label="Users per page">
                            <?php foreach ($per_page_options as $per_page_option): ?>
                                <option value="<?php echo (int) $per_page_option; ?>"<?php echo $per_page === $per_page_option ? ' selected' : ''; ?>>
                                    <?php echo (int) $per_page_option; ?> per page
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn primary" type="submit">Filter</button>
                    </form>
                </div>

                <div class="card">
                    <div class="table-meta">
                        Showing <?php echo $total_users > 0 ? (int) ($page_offset + 1) : 0; ?>-<?php echo (int) min($page_offset + $per_page, $total_users); ?> of <?php echo (int) $total_users; ?> users
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Cluster</th>
                                    <th>Email</th>
                                    <th>Phone No</th>
                                    <?php if ($show_action_column): ?>
                                        <th>Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <td><?php echo (int) ($page_offset + $index + 1); ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars(role_label($user['user_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['cluster_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                        <?php if ($show_action_column): ?>
                                            <td>
                                                <?php if (!empty($user['can_edit'])): ?>
                                                    <button
                                                        class="btn ghost edit-user"
                                                        type="button"
                                                        data-id="<?php echo (int) $user['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                                        data-ic="<?php echo htmlspecialchars($user['ic_no'] ?? ''); ?>"
                                                        data-role="<?php echo htmlspecialchars(role_label($user['user_type'])); ?>"
                                                        data-cluster-id="<?php echo (int) ($user['cluster_id'] ?? 0); ?>"
                                                        data-labs="<?php echo htmlspecialchars($lab_scope_map[$user['id']] ?? ''); ?>"
                                                    >
                                                        Edit
                                                    </button>
                                                <?php else: ?>
                                                    <span class="muted-text">View only</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$users): ?>
                                    <tr>
                                        <td colspan="<?php echo $show_action_column ? '7' : '6'; ?>">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php
                            $prev_page = max(1, $page - 1);
                            $next_page = min($total_pages, $page + 1);
                            $pagination_query['page'] = $prev_page;
                            $prev_url = 'user-management.php?' . http_build_query($pagination_query);
                            $pagination_query['page'] = $next_page;
                            $next_url = 'user-management.php?' . http_build_query($pagination_query);
                            ?>
                            <a class="btn ghost small<?php echo $page <= 1 ? ' is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($prev_url); ?>">Previous</a>
                            <div class="pagination-status">Page <?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></div>
                            <a class="btn ghost small<?php echo $page >= $total_pages ? ' is-disabled' : ''; ?>" href="<?php echo htmlspecialchars($next_url); ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_lab_supervisor): ?>
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2>Role Directory</h2>
                                <p>Additional directory view grouped by role without changing the main user management table.</p>
                            </div>
                        </div>
                        <div class="user-directory-sections">
                            <?php foreach (['super_admin' => 'Admin', 'cluster_admin' => 'Cluster Admin', 'lab_supervisor' => 'Lab Supervisor', 'public' => 'Public User'] as $role_key => $role_title): ?>
                                <div class="user-directory-section">
                                    <div class="directory-section-head">
                                        <h3><?php echo htmlspecialchars($role_title); ?></h3>
                                        <span class="badge"><?php echo count($directory_groups[$role_key]); ?> user(s)</span>
                                    </div>
                                    <?php if ($directory_groups[$role_key]): ?>
                                        <div class="user-directory-grid">
                                            <?php foreach ($directory_groups[$role_key] as $directory_user): ?>
                                                <div class="user-directory-card">
                                                    <h4><?php echo htmlspecialchars($directory_user['name']); ?></h4>
                                                    <p><?php echo htmlspecialchars($directory_user['cluster_name'] ?? 'All clusters'); ?></p>
                                                    <div class="directory-meta">
                                                        <span><?php echo htmlspecialchars($directory_user['email']); ?></span>
                                                        <span><?php echo htmlspecialchars($directory_user['phone'] ?: 'No phone'); ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="muted-text">No users in this directory section.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <footer class="footer">&copy; Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <?php if ($can_manage_users): ?>
    <div class="modal" id="user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button class="icon-button" data-close="user-modal">x</button>
            </div>
            <form class="modal-body" method="POST" action="user-management.php" id="user-form">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="form-user-id">
                <label for="form-name">Name</label>
                <input type="text" name="name" id="form-name" required>
                <label for="form-email">Email</label>
                <input type="email" name="email" id="form-email" required>
                <label for="form-phone">Phone No</label>
                <input type="text" name="phone" id="form-phone" maxlength="12" placeholder="Example: 0123456789">
                <label for="form-ic">IC Number</label>
                <input type="text" name="ic_no" id="form-ic" maxlength="12" placeholder="Example: 990101011234">
                <label for="form-role">Role</label>
                <input type="text" id="form-role" readonly>
                <p class="muted-text">Role cannot be edited by admin.</p>
                <?php if ($is_super_admin): ?>
                    <div class="lab-scope-group" id="form-labs-group">
                        <label for="form-labs">Lab Scope (Supervisor)</label>
                        <select name="lab_ids[]" id="form-labs" multiple size="6">
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?php echo (int) $lab['lab_id']; ?>">
                                    <?php echo htmlspecialchars($lab['cluster_name'] . ' - ' . $lab['lab_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="cluster-scope-group" id="form-cluster-group">
                    <label for="form-cluster">Cluster</label>
                    <select name="cluster_id" id="form-cluster"<?php echo $is_super_admin ? '' : ' disabled'; ?>>
                        <option value="0">Unassigned</option>
                        <?php foreach ($clusters as $cluster): ?>
                            <option value="<?php echo (int) $cluster['cluster_id']; ?>">
                                <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn ghost" data-close="user-modal">Cancel</button>
                    <button type="submit" class="btn primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal<?php echo $show_add_modal ? ' active' : ''; ?>" id="add-user-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add User</h2>
                <button class="icon-button" data-close="add-user-modal">x</button>
            </div>
            <form class="modal-body" method="POST" action="user-management.php" id="add-user-form">
                <input type="hidden" name="action" value="add_user">
                <label for="add-name">Name</label>
                <input type="text" name="name" id="add-name" value="<?php echo htmlspecialchars($add_name); ?>" required>
                <label for="add-email">Email</label>
                <input type="email" name="email" id="add-email" value="<?php echo htmlspecialchars($add_email); ?>" required>
                <label for="add-phone">Phone No</label>
                <input type="text" name="phone" id="add-phone" maxlength="12" placeholder="Example: 0123456789" value="<?php echo htmlspecialchars($add_phone); ?>">
                <label for="add-ic">IC Number</label>
                <input type="text" name="ic_no" id="add-ic" maxlength="12" placeholder="Example: 990101011234" value="<?php echo htmlspecialchars($add_ic); ?>">
                <label for="add-role">Role</label>
                <select name="user_type" id="add-role" required>
                    <option value="public"<?php echo $add_role === 'public' ? ' selected' : ''; ?>>Public User</option>
                    <option value="uthm_student"<?php echo $add_role === 'uthm_student' ? ' selected' : ''; ?>>Student</option>
                    <option value="uthm_staff"<?php echo $add_role === 'uthm_staff' ? ' selected' : ''; ?>>Staff</option>
                    <?php if ($is_super_admin): ?>
                        <option value="cluster_admin"<?php echo $add_role === 'cluster_admin' ? ' selected' : ''; ?>>Cluster Admin</option>
                        <option value="lab_supervisor"<?php echo $add_role === 'lab_supervisor' ? ' selected' : ''; ?>>Lab Supervisor</option>
                    <?php endif; ?>
                </select>
                <?php if ($is_super_admin): ?>
                    <div class="lab-scope-group" id="add-labs-group">
                        <label for="add-labs">Lab Scope (Supervisor)</label>
                        <select name="lab_ids[]" id="add-labs" multiple size="6">
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?php echo (int) $lab['lab_id']; ?>">
                                    <?php echo htmlspecialchars($lab['cluster_name'] . ' - ' . $lab['lab_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="cluster-scope-group" id="add-cluster-group">
                    <label for="add-cluster">Cluster</label>
                    <select name="cluster_id" id="add-cluster">
                        <option value="0">Unassigned</option>
                        <?php foreach ($clusters as $cluster): ?>
                            <option value="<?php echo (int) $cluster['cluster_id']; ?>"<?php echo (int) $add_cluster_id === (int) $cluster['cluster_id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label for="add-password">Password</label>
                <input type="password" name="password" id="add-password" required>
                <label for="add-confirm">Confirm Password</label>
                <input type="password" name="confirm_password" id="add-confirm" required>
                <div class="modal-footer">
                    <button type="button" class="btn ghost" data-close="add-user-modal">Cancel</button>
                    <button type="submit" class="btn primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
        window.LABS_SHOW_ADD_USER = <?php echo $show_add_modal ? 'true' : 'false'; ?>;
    </script>
    <script src="assets/app.js?v=<?php echo (int) (@filemtime(__DIR__ . '/assets/app.js') ?: time()); ?>"></script>
    <?php if ($can_manage_users): ?>
        <script src="assets/user-management.js"></script>
    <?php endif; ?>
</body>
</html>
