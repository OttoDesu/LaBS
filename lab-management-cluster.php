<?php
require_once __DIR__ . '/init.php';
require_login();

$user_type = $_SESSION['user_type'] ?? 'public';
require_admin();
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$can_manage_cluster = !$is_super_admin;
if (is_lab_supervisor($user_type)) {
    set_flash('error', 'You do not have permission to access that page.');
    header('Location: lab-management.php');
    exit;
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
$today_date = date('Y-m-d');
$today_js = htmlspecialchars($today_date, ENT_QUOTES);
if ($cluster_id <= 0) {
    set_flash('error', 'Please select a cluster.');
    header('Location: lab-management.php');
    exit;
}
if (!$is_super_admin && $admin_cluster_id !== $cluster_id) {
    set_flash('error', 'You do not have permission to access that cluster.');
    header('Location: lab-management.php');
    exit;
}

function redirect_to_cluster($cluster_id) {
    header('Location: lab-management-cluster.php?cluster=' . (int) $cluster_id);
    exit;
}

function normalize_lab_ids($raw_ids) {
    if (!is_array($raw_ids)) {
        return [];
    }

    $ids = array_map('intval', $raw_ids);
    $ids = array_values(array_filter($ids, function ($id) {
        return $id > 0;
    }));

    return array_values(array_unique($ids));
}

function fetch_cluster_lab_rows(mysqli $mysqli, $cluster_id, array $lab_ids) {
    if (empty($lab_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($lab_ids), '?'));
    $types = 'i' . str_repeat('i', count($lab_ids));
    $stmt = $mysqli->prepare("
        SELECT l.lab_id, l.lab_name, l.supervisor_id, s.supervisor_name
        FROM labs l
        LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
        WHERE l.cluster_id = ? AND l.lab_id IN ($placeholders)
    ");
    $params = array_merge([$cluster_id], $lab_ids);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $labs = [];
    while ($row = $result->fetch_assoc()) {
        $row['lab_id'] = (int) $row['lab_id'];
        $row['supervisor_id'] = $row['supervisor_id'] !== null ? (int) $row['supervisor_id'] : null;
        $labs[$row['lab_id']] = $row;
    }
    $stmt->close();

    return $labs;
}

function fetch_supervisor_lab_ids(mysqli $mysqli, $cluster_id, $supervisor_id) {
    $stmt = $mysqli->prepare('SELECT lab_id FROM labs WHERE cluster_id = ? AND supervisor_id = ?');
    $stmt->bind_param('ii', $cluster_id, $supervisor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $lab_ids = [];
    while ($row = $result->fetch_assoc()) {
        $lab_ids[] = (int) $row['lab_id'];
    }
    $stmt->close();

    return $lab_ids;
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

function get_supervisor_lab_limit() {
    return 3;
}

function fetch_cluster_supervisor_loads(mysqli $mysqli, $cluster_id) {
    $stmt = $mysqli->prepare('
        SELECT supervisor_id, COUNT(*) AS lab_count
        FROM labs
        WHERE cluster_id = ? AND supervisor_id IS NOT NULL
        GROUP BY supervisor_id
    ');
    $stmt->bind_param('i', $cluster_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $loads = [];
    while ($row = $result->fetch_assoc()) {
        $loads[(int) $row['supervisor_id']] = (int) $row['lab_count'];
    }
    $stmt->close();

    return $loads;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($is_super_admin) {
        set_flash('error', 'Super admins can only view Lab Management.');
        redirect_to_cluster($cluster_id);
    }
    if ($action === 'add_supervisor') {
        $name = trim($_POST['supervisor_name'] ?? '');
        $email = trim($_POST['supervisor_email'] ?? '');
        $room = trim($_POST['supervisor_room_no'] ?? '');
        $vacant_lab_ids = normalize_lab_ids($_POST['vacant_lab_ids'] ?? []);
        $recommended_lab_ids = normalize_lab_ids($_POST['recommended_lab_ids'] ?? []);
        $selected_lab_ids = array_values(array_unique(array_merge($vacant_lab_ids, $recommended_lab_ids)));
        if ($name === '' || $name === 'Unassigned') {
            set_flash('error', 'Supervisor name is required.');
            redirect_to_cluster($cluster_id);
        }
        if (count($selected_lab_ids) > get_supervisor_lab_limit()) {
            set_flash('error', 'A new supervisor can only be assigned a maximum of ' . get_supervisor_lab_limit() . ' labs.');
            redirect_to_cluster($cluster_id);
        }
        $email_value = $email !== '' ? $email : null;
        $room_value = $room !== '' ? $room : null;

        try {
            $mysqli->begin_transaction();

            $stmt = $mysqli->prepare('
                INSERT INTO supervisors (cluster_id, supervisor_name, supervisor_email, supervisor_room_no, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ');
            $stmt->bind_param('isss', $cluster_id, $name, $email_value, $room_value);
            $stmt->execute();
            $supervisor_id = (int) $mysqli->insert_id;
            $stmt->close();

            if (!empty($selected_lab_ids)) {
                $selected_labs = fetch_cluster_lab_rows($mysqli, $cluster_id, $selected_lab_ids);
                if (count($selected_labs) !== count($selected_lab_ids)) {
                    throw new RuntimeException('One or more selected labs are invalid.');
                }

                $supervisor_loads = fetch_cluster_supervisor_loads($mysqli, $cluster_id);
                foreach ($vacant_lab_ids as $lab_id) {
                    $lab = $selected_labs[$lab_id];
                    if (!empty($lab['supervisor_id'])) {
                        throw new RuntimeException('Only vacant labs can be selected from the vacant lab list.');
                    }
                }

                foreach ($recommended_lab_ids as $lab_id) {
                    $lab = $selected_labs[$lab_id];
                    $owner_id = (int) ($lab['supervisor_id'] ?? 0);
                    if ($owner_id <= 0) {
                        throw new RuntimeException('Recommended reassignment labs must already have an assigned supervisor.');
                    }
                    if (($supervisor_loads[$owner_id] ?? 0) < 3) {
                        throw new RuntimeException('Selected DSS recommendation no longer comes from a supervisor with 3 or more labs. Please refresh and try again.');
                    }
                }

                if (!empty($vacant_lab_ids)) {
                    $placeholders = implode(',', array_fill(0, count($vacant_lab_ids), '?'));
                    $types = 'ii' . str_repeat('i', count($vacant_lab_ids));
                    $stmt = $mysqli->prepare("
                        UPDATE labs
                        SET supervisor_id = ?
                        WHERE cluster_id = ? AND supervisor_id IS NULL AND lab_id IN ($placeholders)
                    ");
                    $params = array_merge([$supervisor_id, $cluster_id], $vacant_lab_ids);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    if ($stmt->affected_rows !== count($vacant_lab_ids)) {
                        $stmt->close();
                        throw new RuntimeException('Selected vacant labs are no longer available. Please refresh and try again.');
                    }
                    $stmt->close();
                    foreach ($vacant_lab_ids as $lab_id) {
                        sync_lab_supervisor_history($mysqli, $lab_id, $supervisor_id);
                    }
                }

                if (!empty($recommended_lab_ids)) {
                    $placeholders = implode(',', array_fill(0, count($recommended_lab_ids), '?'));
                    $types = 'ii' . str_repeat('i', count($recommended_lab_ids));
                    $stmt = $mysqli->prepare("
                        UPDATE labs
                        SET supervisor_id = ?
                        WHERE cluster_id = ? AND lab_id IN ($placeholders)
                    ");
                    $params = array_merge([$supervisor_id, $cluster_id], $recommended_lab_ids);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    if ($stmt->affected_rows !== count($recommended_lab_ids)) {
                        $stmt->close();
                        throw new RuntimeException('Selected DSS recommendation labs could not be reassigned. Please refresh and try again.');
                    }
                    $stmt->close();
                    foreach ($recommended_lab_ids as $lab_id) {
                        sync_lab_supervisor_history($mysqli, $lab_id, $supervisor_id);
                    }
                }
            }

            $mysqli->commit();
            $message = 'Supervisor added.';
            if (!empty($selected_lab_ids)) {
                $message = 'Supervisor added and assigned to ' . count($selected_lab_ids) . ' lab(s).';
            }
            set_flash('success', $message);
        } catch (Throwable $e) {
            $mysqli->rollback();
            $message = $e->getCode() === 1062
                ? 'A supervisor with that name already exists in this cluster.'
                : $e->getMessage();
            set_flash('error', $message ?: 'Failed to add supervisor.');
        }
        redirect_to_cluster($cluster_id);
    }

    if ($action === 'update_supervisor_labs') {
        $supervisor_id = (int) ($_POST['supervisor_id'] ?? 0);
        $name = trim($_POST['supervisor_name'] ?? '');
        $email = trim($_POST['supervisor_email'] ?? '');
        $room = trim($_POST['supervisor_room_no'] ?? '');
        if ($supervisor_id <= 0 || $name === '' || $name === 'Unassigned') {
            set_flash('error', 'Supervisor name is required.');
            redirect_to_cluster($cluster_id);
        }
        $email_value = $email !== '' ? $email : null;
        $room_value = $room !== '' ? $room : null;
        $selected_ids = normalize_lab_ids($_POST['lab_ids'] ?? []);
        $replace_ids = normalize_lab_ids($_POST['replace_lab_ids'] ?? []);
        if (count($selected_ids) > get_supervisor_lab_limit()) {
            set_flash('error', 'A supervisor can only hold a maximum of ' . get_supervisor_lab_limit() . ' labs.');
            redirect_to_cluster($cluster_id);
        }

        try {
            $mysqli->begin_transaction();

            $stmt = $mysqli->prepare('
                UPDATE supervisors
                SET supervisor_name = ?, supervisor_email = ?, supervisor_room_no = ?, updated_at = NOW()
                WHERE supervisor_id = ? AND cluster_id = ?
            ');
            $stmt->bind_param('sssii', $name, $email_value, $room_value, $supervisor_id, $cluster_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $check_stmt = $mysqli->prepare('SELECT supervisor_id FROM supervisors WHERE supervisor_id = ? AND cluster_id = ?');
                $check_stmt->bind_param('ii', $supervisor_id, $cluster_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = (bool) $check_result->fetch_assoc();
                $check_stmt->close();
                if (!$exists) {
                    $stmt->close();
                    throw new RuntimeException('Supervisor not found in this cluster.');
                }
            }
            $stmt->close();

            $existing_ids = fetch_supervisor_lab_ids($mysqli, $cluster_id, $supervisor_id);
            $selected_labs = fetch_cluster_lab_rows($mysqli, $cluster_id, $selected_ids);

            if (count($selected_labs) !== count($selected_ids)) {
                throw new RuntimeException('One or more selected labs are invalid.');
            }

            $replacement_count = 0;
            foreach ($selected_ids as $lab_id) {
                $lab = $selected_labs[$lab_id];
                $assigned_supervisor_id = $lab['supervisor_id'];
                if ($assigned_supervisor_id !== null && $assigned_supervisor_id !== $supervisor_id) {
                    if (!in_array($lab_id, $replace_ids, true)) {
                        throw new RuntimeException(
                            'Lab "' . $lab['lab_name'] . '" is still assigned to ' . ($lab['supervisor_name'] ?: 'another supervisor') . '. Tick replace before saving.'
                        );
                    }
                    $replacement_count++;
                }
            }

            $to_unassign = array_values(array_diff($existing_ids, $selected_ids));

            if (!empty($to_unassign)) {
                $placeholders = implode(',', array_fill(0, count($to_unassign), '?'));
                $types = 'ii' . str_repeat('i', count($to_unassign));
                $stmt = $mysqli->prepare("
                    UPDATE labs
                    SET supervisor_id = NULL
                    WHERE cluster_id = ? AND supervisor_id = ? AND lab_id IN ($placeholders)
                ");
                $params = array_merge([$cluster_id, $supervisor_id], $to_unassign);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
                foreach ($to_unassign as $lab_id) {
                    sync_lab_supervisor_history($mysqli, $lab_id, null);
                }
            }

            if (!empty($selected_ids)) {
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                $types = 'ii' . str_repeat('i', count($selected_ids));
                $stmt = $mysqli->prepare("
                    UPDATE labs
                    SET supervisor_id = ?
                    WHERE cluster_id = ? AND lab_id IN ($placeholders)
                ");
                $params = array_merge([$supervisor_id, $cluster_id], $selected_ids);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
                foreach ($selected_ids as $lab_id) {
                    sync_lab_supervisor_history($mysqli, $lab_id, $supervisor_id);
                }
            }

            $mysqli->commit();

            $message = 'Supervisor updated.';
            if ($replacement_count > 0) {
                $message = 'Supervisor updated. Replaced ' . $replacement_count . ' occupied lab assignment(s).';
            }
            set_flash('success', $message);
        } catch (Throwable $e) {
            $mysqli->rollback();
            $message = $e->getCode() === 1062
                ? 'A supervisor with that name already exists in this cluster.'
                : $e->getMessage();
            set_flash('error', $message ?: 'Failed to update supervisor.');
        }
        redirect_to_cluster($cluster_id);
    }

    if ($action === 'delete_supervisor') {
        $supervisor_id = (int) ($_POST['supervisor_id'] ?? 0);
        if ($supervisor_id <= 0) {
            set_flash('error', 'Supervisor name is required.');
            redirect_to_cluster($cluster_id);
        }

        $lab_ids_to_clear = fetch_supervisor_lab_ids($mysqli, $cluster_id, $supervisor_id);

        $stmt = $mysqli->prepare('
            UPDATE labs
            SET supervisor_id = NULL
            WHERE cluster_id = ? AND supervisor_id = ?
        ');
        $stmt->bind_param('ii', $cluster_id, $supervisor_id);
        $stmt->execute();
        $stmt->close();

        foreach ($lab_ids_to_clear as $lab_id) {
            sync_lab_supervisor_history($mysqli, $lab_id, null);
        }

        $stmt = $mysqli->prepare('DELETE FROM supervisors WHERE supervisor_id = ? AND cluster_id = ?');
        $stmt->bind_param('ii', $supervisor_id, $cluster_id);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Supervisor removed.');
        redirect_to_cluster($cluster_id);
    }

    if ($action === 'add_lab') {
        $name = trim($_POST['lab_name'] ?? '');
        $capacity = (int) ($_POST['lab_capacity'] ?? 0);
        $description = trim($_POST['lab_description'] ?? '');
        $assigned_supervisor_id = (int) ($_POST['assigned_supervisor_id'] ?? 0);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Lab name is required.';
        }
        if ($assigned_supervisor_id > 0) {
            $supervisor_loads = fetch_cluster_supervisor_loads($mysqli, $cluster_id);
            $stmt = $mysqli->prepare('
                SELECT supervisor_id
                FROM supervisors
                WHERE cluster_id = ? AND supervisor_id = ?
                LIMIT 1
            ');
            $stmt->bind_param('ii', $cluster_id, $assigned_supervisor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = (bool) $result->fetch_assoc();
            $stmt->close();
            if (!$exists) {
                $errors[] = 'Selected supervisor is invalid.';
            } elseif (($supervisor_loads[$assigned_supervisor_id] ?? 0) >= 2) {
                $errors[] = 'Selected DSS supervisor is no longer below 2 labs. Please refresh and try again.';
            }
        }
        if ($errors) {
            set_flash('error', implode(' ', $errors));
            redirect_to_cluster($cluster_id);
        }
        $supervisor_id_value = $assigned_supervisor_id > 0 ? $assigned_supervisor_id : null;
        $stmt = $mysqli->prepare('
            INSERT INTO labs (
                cluster_id,
                supervisor_id,
                lab_name,
                lab_description,
                lab_capacity,
                maintenance_status,
                maintenance_start_date,
                maintenance_end_date,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->bind_param(
            'iississs',
            $cluster_id,
            $supervisor_id_value,
            $name,
            $description,
            $capacity,
            'available',
            null,
            null
        );
        $stmt->execute();
        $stmt->close();

        $lab_id = (int) $mysqli->insert_id;
        if ($lab_id > 0 && $supervisor_id_value !== null) {
            sync_lab_supervisor_history($mysqli, $lab_id, $supervisor_id_value);
        }

        set_flash('success', 'Lab added.');
        redirect_to_cluster($cluster_id);
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
            redirect_to_cluster($cluster_id);
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
        redirect_to_cluster($cluster_id);
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

$labs_all = [];
$labs_stmt = $mysqli->prepare('
    SELECT l.lab_id, l.lab_name, l.lab_description, l.lab_capacity,
           l.maintenance_status, l.maintenance_start_date, l.maintenance_end_date,
           l.supervisor_id, s.supervisor_name, s.supervisor_email, s.supervisor_room_no, c.cluster_name
    FROM labs l
    JOIN clusters c ON c.cluster_id = l.cluster_id
    LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
    WHERE l.cluster_id = ?
    ORDER BY l.lab_name ASC
');
$labs_stmt->bind_param('i', $cluster_id);
$labs_stmt->execute();
$labs_result = $labs_stmt->get_result();
while ($row = $labs_result->fetch_assoc()) {
    $labs_all[] = $row;
}
$labs_stmt->close();
$vacant_labs = array_values(array_filter($labs_all, function ($lab) {
    return empty($lab['supervisor_id']);
}));

$supervisors_map = [];
$supervisor_stmt = $mysqli->prepare('
    SELECT supervisor_id, supervisor_name, supervisor_email, supervisor_room_no
    FROM supervisors
    WHERE cluster_id = ?
    ORDER BY supervisor_name ASC
');
$supervisor_stmt->bind_param('i', $cluster_id);
$supervisor_stmt->execute();
$supervisor_result = $supervisor_stmt->get_result();
while ($row = $supervisor_result->fetch_assoc()) {
    $key = 'id_' . $row['supervisor_id'];
    $supervisors_map[$key] = [
        'id' => (int) $row['supervisor_id'],
        'name' => $row['supervisor_name'],
        'email' => $row['supervisor_email'],
        'room' => $row['supervisor_room_no'],
        'cluster' => $cluster_name,
        'rooms' => [],
        'labs' => []
    ];
}
$supervisor_stmt->close();

foreach ($labs_all as $lab) {
    $supervisor_id = $lab['supervisor_id'] ? (int) $lab['supervisor_id'] : 0;
    $key = $supervisor_id > 0 ? ('id_' . $supervisor_id) : 'unassigned';
    if (!isset($supervisors_map[$key])) {
        $supervisors_map[$key] = [
            'id' => $supervisor_id,
            'name' => $lab['supervisor_name'] ?: 'Unassigned',
            'email' => $lab['supervisor_email'],
            'room' => $lab['supervisor_room_no'],
            'cluster' => $cluster_name,
            'rooms' => [],
            'labs' => []
        ];
    }
    $supervisors_map[$key]['labs'][] = [
        'id' => (int) $lab['lab_id'],
        'name' => $lab['lab_name']
    ];
}

$supervisors = array_values($supervisors_map);
usort($supervisors, function ($a, $b) {
    if ($a['name'] === 'Unassigned') {
        return 1;
    }
    if ($b['name'] === 'Unassigned') {
        return -1;
    }
    return strcasecmp($a['name'], $b['name']);
});

$total_labs_in_cluster = count($labs_all);
$all_supervisors = $supervisors;
$supervisor_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$supervisor_pagination = paginate_items($supervisors, $supervisor_page, 6);
$supervisors = $supervisor_pagination['items'];

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$layout_path = __DIR__ . '/templates/layouts/admin.php';
$layout = require $layout_path;
$active = 'lab-management';
$app_css_version = @filemtime(__DIR__ . '/assets/app.css') ?: time();
$app_js_version = @filemtime(__DIR__ . '/assets/app.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cluster Supervisors</title>
    <link rel="stylesheet" href="assets/app.css?v=<?php echo (int) $app_css_version; ?>">
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
                        <h1>Supervisors</h1>
                        <p><?php echo htmlspecialchars($cluster_name); ?></p>
                    </div>
                    <div class="header-actions">
                        <div class="breadcrumb">Home / Lab Management / Supervisors</div>
                    </div>
                </div>

                <div class="section-stack">
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2 class="cluster-title-inline">
                                    <span><?php echo htmlspecialchars($cluster_name); ?></span>
                                    <span class="badge">Total labs: <?php echo (int) $total_labs_in_cluster; ?></span>
                                </h2>
                                <p><?php echo htmlspecialchars($cluster_description); ?></p>
                            </div>
                        <div class="banner-links">
                            <a class="btn ghost" href="lab-management.php">Back to clusters</a>
                            <?php if ($can_manage_cluster): ?>
                                <button class="btn primary" type="button" data-modal="add-lab-modal">Add Lab</button>
                                <button class="btn primary" type="button" data-modal="add-supervisor-modal">Add Supervisor</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                    <div class="cluster-grid supervisor-grid">
                        <?php if (empty($supervisors)): ?>
                            <div class="card">
                                <p class="muted-text">No supervisors found for this cluster.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($supervisors as $index => $supervisor): ?>
                                <?php $is_unassigned = $supervisor['name'] === 'Unassigned'; ?>
                                <?php $supervisor_param = $supervisor['name'] === 'Unassigned' ? 'unassigned' : (string) $supervisor['id']; ?>
                                <?php $lab_count = count($supervisor['labs']); ?>
                                <?php
                                $alert_class = 'badge-secondary';
                                if ($lab_count === 0) {
                                    $alert_class = 'badge-danger';
                                } elseif ($lab_count === 1) {
                                    $alert_class = 'badge-warning';
                                } elseif ($lab_count === 2) {
                                    $alert_class = 'badge-success';
                                }
                                ?>
                                <div class="cluster-card clickable" data-href="lab-management-supervisor.php?cluster=<?php echo (int) $cluster_id; ?>&supervisor=<?php echo urlencode($supervisor_param); ?>">
                                    <div class="cluster-card-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($supervisor['name']); ?></h3>
                                            <p><?php echo htmlspecialchars($supervisor['email'] ?: 'No email provided'); ?></p>
                                            <p class="muted-text"><?php echo htmlspecialchars($supervisor['cluster'] ?? $cluster_name); ?></p>
                                        </div>
                                        <div class="card-actions">
                                            <span class="badge <?php echo $alert_class; ?>">
                                                <?php echo htmlspecialchars((string) $lab_count); ?> Lab<?php echo $lab_count === 1 ? '' : 's'; ?>
                                            </span>
                                            <?php if ($can_manage_cluster && !$is_unassigned): ?>
                                                <button class="btn primary small" type="button" data-action="edit-supervisor" data-supervisor-id="<?php echo (int) $supervisor['id']; ?>">Edit</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($supervisor['room'])): ?>
                                        <div class="cluster-meta">Room: <?php echo htmlspecialchars($supervisor['room']); ?></div>
                                    <?php endif; ?>
                                    <div class="lab-chip-list">
                                        <?php if (empty($supervisor['labs'])): ?>
                                            <p class="muted-text">No labs assigned yet.</p>
                                        <?php else: ?>
                                            <?php foreach ($supervisor['labs'] as $lab): ?>
                                                <div class="lab-chip">
                                                    <span><?php echo htmlspecialchars($lab['name']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($supervisor_pagination['total_pages'] > 1): ?>
                        <div class="pagination">
                            <?php
                            $prev_page = max(1, $supervisor_pagination['current_page'] - 1);
                            $next_page = min($supervisor_pagination['total_pages'], $supervisor_pagination['current_page'] + 1);
                            ?>
                            <a class="btn ghost small<?php echo $supervisor_pagination['current_page'] <= 1 ? ' is-disabled' : ''; ?>" href="lab-management-cluster.php?cluster=<?php echo (int) $cluster_id; ?>&page=<?php echo (int) $prev_page; ?>">Previous</a>
                            <div class="pagination-status">Page <?php echo (int) $supervisor_pagination['current_page']; ?> of <?php echo (int) $supervisor_pagination['total_pages']; ?></div>
                            <a class="btn ghost small<?php echo $supervisor_pagination['current_page'] >= $supervisor_pagination['total_pages'] ? ' is-disabled' : ''; ?>" href="lab-management-cluster.php?cluster=<?php echo (int) $cluster_id; ?>&page=<?php echo (int) $next_page; ?>">Next</a>
                        </div>
                    <?php endif; ?>
                </div>

                <footer class="footer">Ac Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

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
                    <div class="modal-section">
                        <div class="section-title">DSS Recommendation SV</div>
                        <p class="muted-text">Recommended supervisors handling fewer than 2 labs for this new lab assignment. Leave it unselected to keep the lab unassigned.</p>
                        <div class="lab-select-list dss-recommendation-list" id="add-lab-dss-list"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="add-lab-modal">Cancel</button>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="add-supervisor-modal">
        <div class="modal-content">
            <form method="POST" id="add-supervisor-form">
                <input type="hidden" name="action" value="add_supervisor">
                <div class="modal-header">
                    <h2>Add Supervisor</h2>
                    <button class="icon-button" type="button" data-close="add-supervisor-modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="add-supervisor-name">Supervisor Name</label>
                        <input id="add-supervisor-name" name="supervisor_name" type="text" required>
                    </div>
                    <div>
                        <label for="add-supervisor-email">Supervisor Email</label>
                        <input id="add-supervisor-email" name="supervisor_email" type="email">
                    </div>
                    <div>
                        <label for="add-supervisor-room">Supervisor Room</label>
                        <input id="add-supervisor-room" name="supervisor_room_no" type="text">
                    </div>
                    <div class="modal-section">
                        <div class="section-title">DSS Recommendation</div>
                        <p class="muted-text">Recommended transfer labs from supervisors currently handling 3 or more labs.</p>
                        <div class="lab-select-list dss-recommendation-list" id="add-supervisor-dss-list"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="add-supervisor-modal">Cancel</button>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="supervisor-modal">
        <div class="modal-content modal-content-wide">
            <form method="POST" id="edit-supervisor-form">
                    <input type="hidden" name="supervisor_id" id="edit-supervisor-id">
                <div class="modal-header">
                    <h2>Edit Supervisor</h2>
                    <button class="icon-button" type="button" data-close="supervisor-modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="edit-supervisor-name">Supervisor Name</label>
                        <input id="edit-supervisor-name" name="supervisor_name" type="text" required>
                    </div>
                    <div>
                        <label for="edit-supervisor-email">Supervisor Email</label>
                        <input id="edit-supervisor-email" name="supervisor_email" type="email">
                    </div>
                    <div>
                        <label for="edit-supervisor-room">Supervisor Room</label>
                        <input id="edit-supervisor-room" name="supervisor_room_no" type="text">
                    </div>
                    <div class="modal-section"></br>
                        <div class="section-title">Use the recommended supervisors first when temporarily switching or reassigning labs.</div>
                        <div class="lab-select-list dss-recommendation-list" id="edit-supervisor-dss-list"></div>
                    </div>
                    <div class="modal-section"></br>
                        <div class="section-title">Vacant labs can be assigned directly. Occupied labs require explicit replacement.</div>
                        <div class="alert alert-error" id="manage-labs-error" hidden></div>
                        <div class="lab-select-list" id="manage-labs-list"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="supervisor-modal">Cancel</button>
                    <button class="btn danger" type="submit" name="action" value="delete_supervisor">Delete</button>
                    <button class="btn primary" type="submit" name="action" value="update_supervisor_labs">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="supervisor-labs-modal">
        <div class="modal-content modal-content-wide">
            <div class="modal-header">
                <h2 id="supervisor-labs-modal-title">Supervisor Labs</h2>
                <button class="icon-button" type="button" data-close="supervisor-labs-modal" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="muted-text" id="supervisor-labs-modal-subtitle"></p>
                <div class="lab-select-list supervisor-labs-modal-list" id="supervisor-labs-modal-list"></div>
            </div>
        </div>
    </div>

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

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
    </script>
    <script src="assets/app.js?v=<?php echo (int) $app_js_version; ?>"></script>
    <script>
        (function () {
            var maxLabsPerSupervisor = <?php echo (int) get_supervisor_lab_limit(); ?>;
            var supervisors = <?php echo json_encode($all_supervisors); ?>;
            var labs = <?php echo json_encode($labs_all); ?>;
            var labsById = {};
            labs.forEach(function (lab) {
                labsById[lab.lab_id] = lab;
            });

            function openModal(id) {
                var modal = document.getElementById(id);
                if (modal) {
                    modal.classList.add('active');
                }
            }

            function closeModal(id) {
                var modal = document.getElementById(id);
                if (modal) {
                    modal.classList.remove('active');
                }
            }

            function setModalError(node, message) {
                if (!node) {
                    return;
                }
                if (!message) {
                    node.hidden = true;
                    node.textContent = '';
                    return;
                }
                node.hidden = false;
                node.textContent = message;
            }

            function countCheckedLabs(container, checkboxName) {
                if (!container) {
                    return 0;
                }
                return container.querySelectorAll('input[name="' + checkboxName + '[]"]:checked').length;
            }

            function validateLabLimit(container, checkboxName, errorNode) {
                var checkedCount = countCheckedLabs(container, checkboxName);
                if (checkedCount > maxLabsPerSupervisor) {
                    setModalError(errorNode, 'A supervisor can only hold a maximum of ' + maxLabsPerSupervisor + ' labs. Please deselect at least ' + (checkedCount - maxLabsPerSupervisor) + ' lab(s).');
                    return false;
                }
                setModalError(errorNode, '');
                return true;
            }

            var addSupervisorForm = document.getElementById('add-supervisor-form');
            var addSupervisorError = document.getElementById('add-supervisor-labs-error');
            var addSupervisorDssList = document.getElementById('add-supervisor-dss-list');
            var addLabDssList = document.getElementById('add-lab-dss-list');
            var addMaintenanceStart = document.getElementById('add-maintenance-start');
            var addMaintenanceEnd = document.getElementById('add-maintenance-end');
            var editSupervisorForm = document.getElementById('edit-supervisor-form');
            var editSupervisorDssList = document.getElementById('edit-supervisor-dss-list');
            var manageLabsList = document.getElementById('manage-labs-list');
            var manageLabsError = document.getElementById('manage-labs-error');
            var supervisorLabsModalTitle = document.getElementById('supervisor-labs-modal-title');
            var supervisorLabsModalSubtitle = document.getElementById('supervisor-labs-modal-subtitle');
            var supervisorLabsModalList = document.getElementById('supervisor-labs-modal-list');
            var editMaintenanceStart = document.getElementById('edit-maintenance-start');
            var editMaintenanceEnd = document.getElementById('edit-maintenance-end');

            function normalizeMaintenanceDates(startInput, endInput) {
                if (!startInput || !endInput) {
                    return;
                }

                startInput.min = '<?php echo $today_js; ?>';
                endInput.min = startInput.value && startInput.value > '<?php echo $today_js; ?>' ? startInput.value : '<?php echo $today_js; ?>';

                if (startInput.value && startInput.value < '<?php echo $today_js; ?>') {
                    startInput.value = '';
                }

                if (endInput.value && endInput.value < '<?php echo $today_js; ?>') {
                    endInput.value = '';
                }

                if (startInput.value) {
                    endInput.min = startInput.value;
                }

                if (endInput.value && startInput.value && endInput.value < startInput.value) {
                    endInput.value = startInput.value;
                }
            }
            var supervisorLoads = {};
            supervisors.forEach(function (candidate) {
                supervisorLoads[Number(candidate.id || 0)] = (candidate.labs || []).length;
            });

            function getDssCandidates(excludedSupervisorId) {
                return supervisors.filter(function (candidate) {
                    if (!candidate || !candidate.id || candidate.name === 'Unassigned') {
                        return false;
                    }
                    if (excludedSupervisorId && Number(candidate.id) === Number(excludedSupervisorId)) {
                        return false;
                    }
                    return (candidate.labs || []).length < 2;
                }).sort(function (a, b) {
                    var aLabCount = (a.labs || []).length;
                    var bLabCount = (b.labs || []).length;
                    if (aLabCount !== bLabCount) {
                        return aLabCount - bLabCount;
                    }
                    return String(a.name || '').localeCompare(String(b.name || ''));
                });
            }

            function getAddLabDssCandidates() {
                return supervisors.filter(function (candidate) {
                    if (!candidate || !candidate.id || candidate.name === 'Unassigned') {
                        return false;
                    }
                    return (candidate.labs || []).length < 2;
                }).sort(function (a, b) {
                    var aLabCount = (a.labs || []).length;
                    var bLabCount = (b.labs || []).length;
                    if (aLabCount !== bLabCount) {
                        return aLabCount - bLabCount;
                    }
                    return String(a.name || '').localeCompare(String(b.name || ''));
                });
            }

            function getAddSupervisorDssLabs() {
                return labs.filter(function (lab) {
                    var ownerId = Number(lab.supervisor_id || 0);
                    if (ownerId <= 0) {
                        return false;
                    }
                    return (supervisorLoads[ownerId] || 0) >= 3;
                }).sort(function (a, b) {
                    var aLoad = supervisorLoads[Number(a.supervisor_id || 0)] || 0;
                    var bLoad = supervisorLoads[Number(b.supervisor_id || 0)] || 0;
                    if (aLoad !== bLoad) {
                        return bLoad - aLoad;
                    }
                    return String(a.lab_name || '').localeCompare(String(b.lab_name || ''));
                });
            }

            function buildDssRecommendationItem(candidate) {
                var candidateLabCount = (candidate.labs || []).length;
                var badgeClass = candidateLabCount === 0 ? 'badge-danger' : 'badge-warning';
                var labsHeld = (candidate.labs || []).map(function (lab) {
                    return lab.name;
                }).join(', ');
                var labsHeldLabel = labsHeld || 'No labs assigned';
                return [
                    '<button class="lab-select-item dss-recommendation-item" type="button" data-dss-supervisor-id="' + candidate.id + '">',
                    '  <span class="dss-indicator"><input type="checkbox" disabled checked></span>',
                    '  <span class="lab-select-details">',
                    '    <strong>' + candidate.name + '</strong>',
                    '    <span>' + (candidate.email || 'No email provided') + '</span>',
                    '  </span>',
                    '  <span class="lab-select-actions">',
                    '    <span class="dss-info" title="' + labsHeldLabel.replace(/"/g, '&quot;') + '">i</span>',
                    '    <span class="badge ' + badgeClass + '">' + candidateLabCount + ' Lab' + (candidateLabCount === 1 ? '' : 's') + '</span>',
                    '    <span class="btn ghost small">Use This SV</span>',
                    '  </span>',
                    '</button>'
                ].join('');
            }

            function wireDssList(container) {
                if (!container) {
                    return;
                }
                container.querySelectorAll('[data-dss-supervisor-id]').forEach(function (item) {
                    item.addEventListener('click', function () {
                        var targetId = Number(item.getAttribute('data-dss-supervisor-id'));
                        var targetSupervisor = supervisors.find(function (candidate) {
                            return Number(candidate.id || 0) === targetId;
                        });
                        openSupervisorEditor(targetSupervisor);
                    });
                });
            }

            function renderDssList(container, excludedSupervisorId) {
                if (!container) {
                    return;
                }
                var candidates = getDssCandidates(excludedSupervisorId);
                if (candidates.length === 0) {
                    container.innerHTML = '<div class="lab-select-item dss-recommendation-empty"><span class="lab-select-details"><strong>No DSS recommendation</strong><span>No supervisor is currently below 2 labs.</span></span></div>';
                    return;
                }
                container.innerHTML = candidates.map(buildDssRecommendationItem).join('');
                wireDssList(container);
            }

            function renderSupervisorLabsModal(supervisor) {
                if (!supervisorLabsModalTitle || !supervisorLabsModalSubtitle || !supervisorLabsModalList) {
                    return;
                }

                var labList = supervisor && supervisor.labs ? supervisor.labs : [];
                var labCount = labList.length;
                supervisorLabsModalTitle.textContent = supervisor ? supervisor.name + ' Labs' : 'Supervisor Labs';
                supervisorLabsModalSubtitle.textContent = supervisor
                    ? (supervisor.email || 'No email provided') + ' · ' + labCount + ' lab' + (labCount === 1 ? '' : 's')
                    : '';

                if (labCount === 0) {
                    supervisorLabsModalList.innerHTML = '<div class="lab-select-item dss-recommendation-empty"><span class="lab-select-details"><strong>No labs assigned</strong><span>This supervisor is currently not managing any labs.</span></span></div>';
                } else {
                    supervisorLabsModalList.innerHTML = labList.map(function (lab) {
                        return [
                            '<div class="lab-select-item supervisor-lab-info-item">',
                            '  <span class="lab-select-details">',
                            '    <strong>' + lab.name + '</strong>',
                            '    <span>Lab ID: ' + lab.id + '</span>',
                            '  </span>',
                            '  <span class="badge badge-secondary">Assigned</span>',
                            '</div>'
                        ].join('');
                    }).join('');
                }
                openModal('supervisor-labs-modal');
            }

            function countAddSupervisorSelectedLabs() {
                if (!addSupervisorForm) {
                    return 0;
                }
                return addSupervisorForm.querySelectorAll('input[name="vacant_lab_ids[]"]:checked, input[name="recommended_lab_ids[]"]:checked').length;
            }

            function validateAddSupervisorLimit() {
                var checkedCount = countAddSupervisorSelectedLabs();
                if (checkedCount > maxLabsPerSupervisor) {
                    setModalError(addSupervisorError, 'A new supervisor can only be assigned a maximum of ' + maxLabsPerSupervisor + ' labs. Please deselect at least ' + (checkedCount - maxLabsPerSupervisor) + ' lab(s).');
                    return false;
                }
                setModalError(addSupervisorError, '');
                return true;
            }

            function renderAddSupervisorDssList() {
                if (!addSupervisorDssList) {
                    return;
                }
                var recommendedLabs = getAddSupervisorDssLabs();
                if (recommendedLabs.length === 0) {
                    addSupervisorDssList.innerHTML = '<div class="lab-select-item dss-recommendation-empty"><span class="lab-select-details"><strong>No DSS recommendation</strong><span>No supervisor is currently handling 3 or more labs.</span></span></div>';
                    return;
                }
                addSupervisorDssList.innerHTML = recommendedLabs.map(function (lab) {
                    var ownerLoad = supervisorLoads[Number(lab.supervisor_id || 0)] || 0;
                    return [
                        '<label class="lab-select-item dss-transfer-item">',
                        '  <input type="checkbox" name="recommended_lab_ids[]" value="' + lab.lab_id + '">',
                        '  <span class="lab-select-details">',
                        '    <strong>' + lab.lab_name + '</strong>',
                        '    <span>Currently assigned to ' + (lab.supervisor_name || 'another supervisor') + ' · ' + ownerLoad + ' labs</span>',
                        '  </span>',
                        '  <span class="lab-select-actions">',
                        '    <span class="badge badge-warning">Transfer</span>',
                        '  </span>',
                        '</label>'
                    ].join('');
                }).join('');
            }

            function renderAddLabDssList() {
                if (!addLabDssList) {
                    return;
                }
                var candidates = getAddLabDssCandidates();
                if (candidates.length === 0) {
                    addLabDssList.innerHTML = '<div class="lab-select-item dss-recommendation-empty"><span class="lab-select-details"><strong>No DSS recommendation</strong><span>No supervisor is currently below 2 labs.</span></span></div>';
                    return;
                }
                addLabDssList.innerHTML = candidates.map(function (candidate) {
                    var candidateLabCount = (candidate.labs || []).length;
                    var badgeClass = candidateLabCount === 0 ? 'badge-danger' : 'badge-warning';
                    var labsHeld = (candidate.labs || []).map(function (lab) { return lab.name; }).join(', ');
                    var labsHeldLabel = labsHeld || 'No labs assigned';
                    return [
                        '<label class="lab-select-item dss-recommendation-item dss-radio-item">',
                        '  <input type="radio" name="assigned_supervisor_id" value="' + candidate.id + '">',
                        '  <span class="lab-select-details">',
                        '    <strong>' + candidate.name + '</strong>',
                        '    <span>' + (candidate.email || 'No email provided') + '</span>',
                        '  </span>',
                        '  <span class="lab-select-actions">',
                        '    <span class="dss-info" title="' + labsHeldLabel.replace(/"/g, '&quot;') + '">i</span>',
                        '    <span class="badge ' + badgeClass + '">' + candidateLabCount + ' Lab' + (candidateLabCount === 1 ? '' : 's') + '</span>',
                        '  </span>',
                        '</label>'
                    ].join('');
                }).join('');
            }

            function openSupervisorEditor(supervisor) {
                if (!supervisor) {
                    return;
                }
                closeModal('add-supervisor-modal');
                document.getElementById('edit-supervisor-id').value = supervisor.id || 0;
                document.getElementById('edit-supervisor-name').value = supervisor.name || '';
                document.getElementById('edit-supervisor-email').value = supervisor.email || '';
                document.getElementById('edit-supervisor-room').value = supervisor.room || '';
                renderDssList(editSupervisorDssList, supervisor.id || 0);
                var list = document.getElementById('manage-labs-list');
                list.innerHTML = '';
                setModalError(manageLabsError, '');
                var assignedIds = new Set((supervisor.labs || []).map(function (lab) { return lab.id; }));
                labs.forEach(function (lab) {
                    var item = document.createElement('div');
                    item.className = 'lab-select-item';

                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'lab_ids[]';
                    checkbox.value = lab.lab_id;
                    checkbox.checked = assignedIds.has(lab.lab_id);

                    var details = document.createElement('div');
                    details.className = 'lab-select-details';

                    var name = document.createElement('strong');
                    name.textContent = lab.lab_name;

                    var meta = document.createElement('span');
                    var occupiedByOther = !!lab.supervisor_id && Number(lab.supervisor_id) !== Number(supervisor.id || 0);
                    var ownedByCurrent = assignedIds.has(lab.lab_id);
                    if (ownedByCurrent) {
                        item.classList.add('is-assigned');
                        meta.textContent = 'Assigned to this supervisor';
                    } else if (occupiedByOther) {
                        item.classList.add('is-occupied');
                        var currentWrapper = document.createElement('span');
                        currentWrapper.className = 'lab-select-occupied-row';

                        var currentText = document.createElement('span');
                        currentText.innerHTML = 'Currently assigned to <span class="lab-select-assignee">' + (lab.supervisor_name || 'another supervisor') + '</span>';

                        var infoButton = document.createElement('button');
                        infoButton.type = 'button';
                        infoButton.className = 'dss-info lab-sv-info';
                        infoButton.setAttribute('aria-label', 'View supervisor labs');
                        infoButton.textContent = 'i';
                        infoButton.addEventListener('click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();
                            var supervisorId = Number(lab.supervisor_id || 0);
                            var supervisorInfo = supervisors.find(function (candidate) {
                                return Number(candidate.id || 0) === supervisorId;
                            });
                            renderSupervisorLabsModal(supervisorInfo);
                        });

                        currentWrapper.appendChild(currentText);
                        currentWrapper.appendChild(infoButton);
                        meta.appendChild(currentWrapper);
                    } else {
                        item.classList.add('is-available');
                        meta.textContent = 'Vacant';
                    }

                    details.appendChild(name);
                    details.appendChild(meta);

                    var actions = document.createElement('div');
                    actions.className = 'lab-select-actions';

                    if (occupiedByOther) {
                        checkbox.disabled = true;
                        checkbox.checked = false;

                        var replaceLabel = document.createElement('label');
                        replaceLabel.className = 'checkbox checkbox-inline';

                        var replaceCheckbox = document.createElement('input');
                        replaceCheckbox.type = 'checkbox';
                        replaceCheckbox.name = 'replace_lab_ids[]';
                        replaceCheckbox.value = lab.lab_id;

                        var replaceText = document.createElement('span');
                        replaceText.textContent = 'Replace current supervisor';

                        replaceCheckbox.addEventListener('change', function () {
                            checkbox.disabled = !replaceCheckbox.checked;
                            checkbox.checked = replaceCheckbox.checked;
                            item.classList.toggle('is-replacing', replaceCheckbox.checked);
                            validateLabLimit(manageLabsList, 'lab_ids', manageLabsError);
                        });

                        replaceLabel.appendChild(replaceCheckbox);
                        replaceLabel.appendChild(replaceText);
                        actions.appendChild(replaceLabel);
                    }

                    var statusBadge = document.createElement('span');
                    statusBadge.className = 'badge ' + (ownedByCurrent || !occupiedByOther ? 'badge-success' : 'badge-warning');
                    statusBadge.textContent = ownedByCurrent ? 'Assigned' : (occupiedByOther ? 'Occupied' : 'Vacant');
                    actions.appendChild(statusBadge);

                    var editButton = document.createElement('button');
                    editButton.type = 'button';
                    editButton.className = 'btn ghost small';
                    editButton.textContent = 'Edit';
                    editButton.setAttribute('data-lab-id', lab.lab_id);
                    editButton.addEventListener('click', function (event) {
                        event.preventDefault();
                        var labRow = labsById[lab.lab_id];
                        if (!labRow) {
                            return;
                        }
                        document.getElementById('edit-lab-id').value = labRow.lab_id;
                        document.getElementById('edit-lab-name').value = labRow.lab_name || '';
                        document.getElementById('edit-lab-capacity').value = labRow.lab_capacity || 0;
                        document.getElementById('edit-lab-description').value = labRow.lab_description || '';
                        document.getElementById('edit-maintenance-status').value = labRow.maintenance_status || 'available';
                        document.getElementById('edit-maintenance-start').value = labRow.maintenance_start_date || '';
                        document.getElementById('edit-maintenance-end').value = labRow.maintenance_end_date || '';
                        normalizeMaintenanceDates(editMaintenanceStart, editMaintenanceEnd);
                        openModal('edit-lab-modal');
                    });

                    actions.appendChild(editButton);
                    item.appendChild(checkbox);
                    item.appendChild(details);
                    item.appendChild(actions);
                    list.appendChild(item);

                    checkbox.addEventListener('change', function () {
                        validateLabLimit(manageLabsList, 'lab_ids', manageLabsError);
                    });
                });
                openModal('supervisor-modal');
            }

            if (addSupervisorForm) {
                renderAddSupervisorDssList();
                addSupervisorForm.addEventListener('change', function () {
                    validateAddSupervisorLimit();
                });
                addSupervisorForm.addEventListener('submit', function (event) {
                    if (!validateAddSupervisorLimit()) {
                        event.preventDefault();
                    }
                });
            }

            renderAddLabDssList();

            normalizeMaintenanceDates(addMaintenanceStart, addMaintenanceEnd);
            normalizeMaintenanceDates(editMaintenanceStart, editMaintenanceEnd);

            if (addMaintenanceStart) {
                addMaintenanceStart.addEventListener('change', function () {
                    normalizeMaintenanceDates(addMaintenanceStart, addMaintenanceEnd);
                });
            }

            if (addMaintenanceEnd) {
                addMaintenanceEnd.addEventListener('change', function () {
                    normalizeMaintenanceDates(addMaintenanceStart, addMaintenanceEnd);
                });
            }

            if (editMaintenanceStart) {
                editMaintenanceStart.addEventListener('change', function () {
                    normalizeMaintenanceDates(editMaintenanceStart, editMaintenanceEnd);
                });
            }

            if (editMaintenanceEnd) {
                editMaintenanceEnd.addEventListener('change', function () {
                    normalizeMaintenanceDates(editMaintenanceStart, editMaintenanceEnd);
                });
            }

            document.querySelectorAll('[data-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    openModal(button.getAttribute('data-modal'));
                });
            });

            document.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    closeModal(button.getAttribute('data-close'));
                });
            });

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

            document.querySelectorAll('[data-action="edit-supervisor"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var supervisorId = Number(button.getAttribute('data-supervisor-id'));
                    var supervisor = supervisors.find(function (candidate) {
                        return Number(candidate.id || 0) === supervisorId;
                    });
                    openSupervisorEditor(supervisor);
                });
            });

            if (editSupervisorForm) {
                editSupervisorForm.addEventListener('submit', function (event) {
                    if (!validateLabLimit(manageLabsList, 'lab_ids', manageLabsError)) {
                        event.preventDefault();
                    }
                });
            }

            document.querySelectorAll('form button[name="action"][value="delete_supervisor"]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    var ok = window.confirm('Delete this supervisor and unassign all their labs?');
                    if (!ok) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>
</body>
</html>
