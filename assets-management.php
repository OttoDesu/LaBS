<?php
require_once __DIR__ . '/init.php';
require_login();

$user_type = $_SESSION['user_type'] ?? 'public';
require_management();
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$can_edit_assets = $is_super_admin || $is_lab_supervisor;
$lab_scope_ids = [];
if ($is_lab_supervisor) {
    $lab_scope_ids = get_lab_supervisor_lab_ids($mysqli, (int) ($_SESSION['user_id'] ?? 0));
}

function admin_can_access_lab($mysqli, $lab_id, $admin_cluster_id, $is_super_admin, $is_lab_supervisor, $lab_scope_ids) {
    if ($is_super_admin) {
        return true;
    }
    if ($is_lab_supervisor) {
        return in_array((int) $lab_id, $lab_scope_ids, true);
    }
    if (!$admin_cluster_id || $lab_id <= 0) {
        return false;
    }
    $stmt = $mysqli->prepare('SELECT lab_id FROM labs WHERE lab_id = ? AND cluster_id = ?');
    $stmt->bind_param('ii', $lab_id, $admin_cluster_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (bool) $row;
}

function paginate_items(array $items, $current_page, $per_page) {
    $total_items = count($items);
    $total_pages = max(1, (int) ceil($total_items / $per_page));
    $current_page = max(1, min((int) $current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    return [
        'items' => array_slice($items, $offset, $per_page, true),
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_items' => $total_items,
        'per_page' => $per_page
    ];
}

$mysqli->query('
    CREATE TABLE IF NOT EXISTS assets (
        asset_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lab_id BIGINT(20) UNSIGNED NOT NULL,
        asset_name VARCHAR(255) NOT NULL,
        asset_status VARCHAR(50) NOT NULL,
        asset_unavailable_reason TEXT DEFAULT NULL,
        asset_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_assets_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

function ensure_asset_unavailable_reason_column(mysqli $mysqli): void {
    $column_exists = false;
    $column_stmt = $mysqli->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'assets'
          AND column_name = 'asset_unavailable_reason'
        LIMIT 1
    ");
    if ($column_stmt) {
        $column_stmt->execute();
        $column_exists = (bool) $column_stmt->get_result()->fetch_assoc();
        $column_stmt->close();
    }

    if (!$column_exists) {
        $mysqli->query("ALTER TABLE assets ADD COLUMN asset_unavailable_reason TEXT DEFAULT NULL AFTER asset_status");
    }
}

ensure_asset_unavailable_reason_column($mysqli);

$errors = [];
$selected_lab = isset($_GET['lab']) ? (int) $_GET['lab'] : 0;
$selected_cluster = isset($_GET['cluster']) ? (int) $_GET['cluster'] : 0;
$selected_supervisor = isset($_GET['supervisor']) ? (int) ($_GET['supervisor'] ?? 0) : 0;
$search = trim($_GET['search'] ?? '');
$card_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_edit_assets) {
        $errors[] = 'Cluster admins can only view asset information.';
    }

    $action = $_POST['action'] ?? '';
    $asset_id = (int) ($_POST['asset_id'] ?? 0);
    $lab_id = (int) ($_POST['lab_id'] ?? 0);
    $asset_name = trim($_POST['asset_name'] ?? '');
    $asset_status = trim($_POST['asset_status'] ?? '');
    $asset_unavailable_reason = trim($_POST['asset_unavailable_reason'] ?? '');
    $asset_count = (int) ($_POST['asset_count'] ?? 0);

    if ($lab_id <= 0) {
        $errors[] = 'Lab is required.';
    }
    if ($asset_name === '') {
        $errors[] = 'Asset name is required.';
    }
    if ($asset_status === '') {
        $errors[] = 'Asset status is required.';
    }
    if ($asset_status === 'Unavailable' && $asset_unavailable_reason === '') {
        $errors[] = 'Unavailable reason is required.';
    }
    if ($asset_status !== 'Unavailable') {
        $asset_unavailable_reason = null;
    }
    if ($asset_count < 0) {
        $errors[] = 'Asset count must be zero or more.';
    }

    if (!$errors && $action === 'add_asset') {
        if (!admin_can_access_lab($mysqli, $lab_id, $admin_cluster_id, $is_super_admin, $is_lab_supervisor, $lab_scope_ids)) {
            $errors[] = 'You do not have access to that lab.';
        }
    }

    if (!$errors && $action === 'add_asset') {
        $stmt = $mysqli->prepare('
            INSERT INTO assets (lab_id, asset_name, asset_status, asset_unavailable_reason, asset_count, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->bind_param('isssi', $lab_id, $asset_name, $asset_status, $asset_unavailable_reason, $asset_count);
        $stmt->execute();
        $stmt->close();
        set_flash('info', 'Asset added successfully.');
        header('Location: assets-management.php?lab=' . (int) $lab_id);
        exit;
    }

    if (!$errors && $action === 'update_asset' && $asset_id > 0) {
        if (!admin_can_access_lab($mysqli, $lab_id, $admin_cluster_id, $is_super_admin, $is_lab_supervisor, $lab_scope_ids)) {
            $errors[] = 'You do not have access to that lab.';
        }
    }

    if (!$errors && $action === 'update_asset' && $asset_id > 0) {
        $stmt = $mysqli->prepare('
            UPDATE assets
            SET lab_id = ?, asset_name = ?, asset_status = ?, asset_unavailable_reason = ?, asset_count = ?, updated_at = NOW()
            WHERE asset_id = ?
        ');
        $stmt->bind_param('isssii', $lab_id, $asset_name, $asset_status, $asset_unavailable_reason, $asset_count, $asset_id);
        $stmt->execute();
        $stmt->close();
        set_flash('info', 'Asset updated successfully.');
        header('Location: assets-management.php?lab=' . (int) $lab_id);
        exit;
    }

    if ($action === 'delete_asset' && $asset_id > 0) {
        if (!$is_super_admin) {
            if ($is_lab_supervisor) {
                if (!$lab_scope_ids) {
                    $errors[] = 'You do not have access to that asset.';
                } else {
                    $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
                    $types = str_repeat('i', count($lab_scope_ids));
                    $stmt = $mysqli->prepare("
                        SELECT a.asset_id
                        FROM assets a
                        WHERE a.asset_id = ? AND a.lab_id IN ($placeholders)
                    ");
                    $stmt->bind_param('i' . $types, $asset_id, ...$lab_scope_ids);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $allowed = (bool) $result->fetch_assoc();
                    $stmt->close();
                    if (!$allowed) {
                        $errors[] = 'You do not have access to that asset.';
                    }
                }
            } else {
                $stmt = $mysqli->prepare('
                    SELECT a.asset_id
                    FROM assets a
                    JOIN labs l ON l.lab_id = a.lab_id
                    WHERE a.asset_id = ? AND l.cluster_id = ?
                ');
                $stmt->bind_param('ii', $asset_id, $admin_cluster_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $allowed = (bool) $result->fetch_assoc();
                $stmt->close();
                if (!$allowed) {
                    $errors[] = 'You do not have access to that asset.';
                }
            }
        }
    }

    if (!$errors && $action === 'delete_asset' && $asset_id > 0) {
        $stmt = $mysqli->prepare('DELETE FROM assets WHERE asset_id = ?');
        $stmt->bind_param('i', $asset_id);
        $stmt->execute();
        $stmt->close();
        set_flash('info', 'Asset deleted successfully.');
        header('Location: assets-management.php?lab=' . (int) $lab_id);
        exit;
    }
}

$cluster_options = [];
$cluster_meta = [];
$supervisor_options = [];
$supervisor_options_map = [];
$lab_options = [];
$selected_cluster_info = null;

if ($is_super_admin) {
    $cluster_options_stmt = $mysqli->prepare('SELECT cluster_id, cluster_name, cluster_description FROM clusters ORDER BY cluster_name ASC');
    $cluster_options_stmt->execute();
    $cluster_options_result = $cluster_options_stmt->get_result();
    while ($row = $cluster_options_result->fetch_assoc()) {
        $cluster_options[] = $row;
        $cluster_meta[(int) $row['cluster_id']] = [
            'lab_count' => 0,
            'supervisor_count' => 0
        ];
    }
    $cluster_options_stmt->close();

    if ($cluster_meta) {
        $cluster_counts_stmt = $mysqli->prepare('
            SELECT cluster_id, COUNT(*) AS lab_count
            FROM labs
            GROUP BY cluster_id
        ');
        $cluster_counts_stmt->execute();
        $cluster_counts_result = $cluster_counts_stmt->get_result();
        while ($row = $cluster_counts_result->fetch_assoc()) {
            $cluster_key = (int) $row['cluster_id'];
            if (isset($cluster_meta[$cluster_key])) {
                $cluster_meta[$cluster_key]['lab_count'] = (int) $row['lab_count'];
            }
        }
        $cluster_counts_stmt->close();

        $supervisor_counts_stmt = $mysqli->prepare('
            SELECT cluster_id, COUNT(*) AS supervisor_count
            FROM supervisors
            GROUP BY cluster_id
        ');
        $supervisor_counts_stmt->execute();
        $supervisor_counts_result = $supervisor_counts_stmt->get_result();
        while ($row = $supervisor_counts_result->fetch_assoc()) {
            $cluster_key = (int) $row['cluster_id'];
            if (isset($cluster_meta[$cluster_key])) {
                $cluster_meta[$cluster_key]['supervisor_count'] = (int) $row['supervisor_count'];
            }
        }
        $supervisor_counts_stmt->close();
    }

    $supervisor_map_stmt = $mysqli->prepare('
        SELECT cluster_id, supervisor_id, supervisor_name
        FROM supervisors
        ORDER BY supervisor_name ASC
    ');
    $supervisor_map_stmt->execute();
    $supervisor_map_result = $supervisor_map_stmt->get_result();
    while ($row = $supervisor_map_result->fetch_assoc()) {
        $cluster_key = (int) $row['cluster_id'];
        if (!isset($supervisor_options_map[$cluster_key])) {
            $supervisor_options_map[$cluster_key] = [];
        }
        $supervisor_options_map[$cluster_key][] = [
            'supervisor_id' => (int) $row['supervisor_id'],
            'supervisor_name' => $row['supervisor_name']
        ];
    }
    $supervisor_map_stmt->close();

    $valid_cluster_ids = array_map(static function ($cluster) {
        return (int) $cluster['cluster_id'];
    }, $cluster_options);
    if ($selected_cluster > 0 && !in_array($selected_cluster, $valid_cluster_ids, true)) {
        $selected_cluster = 0;
    }

    if ($selected_cluster > 0) {
        foreach ($cluster_options as $cluster_option) {
            if ((int) $cluster_option['cluster_id'] === $selected_cluster) {
                $selected_cluster_info = $cluster_option;
                break;
            }
        }
        $supervisor_options_stmt = $mysqli->prepare('
            SELECT supervisor_id, supervisor_name
            FROM supervisors
            WHERE cluster_id = ?
            ORDER BY supervisor_name ASC
        ');
        $supervisor_options_stmt->bind_param('i', $selected_cluster);
        $supervisor_options_stmt->execute();
        $supervisor_options_result = $supervisor_options_stmt->get_result();
        while ($row = $supervisor_options_result->fetch_assoc()) {
            $supervisor_options[] = $row;
        }
        $supervisor_options_stmt->close();

        $valid_supervisor_ids = array_map(static function ($supervisor) {
            return (int) $supervisor['supervisor_id'];
        }, $supervisor_options);
        if ($selected_supervisor > 0 && !in_array($selected_supervisor, $valid_supervisor_ids, true)) {
            $selected_supervisor = 0;
        }
    } else {
        $selected_supervisor = 0;
    }
}

if ($is_super_admin) {
    $labs_stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, c.cluster_name
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        ORDER BY c.cluster_name ASC, l.lab_name ASC
    ');
    $labs_stmt->execute();
} elseif ($is_lab_supervisor) {
    if ($lab_scope_ids) {
        $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
        $types = str_repeat('i', count($lab_scope_ids));
        $labs_stmt = $mysqli->prepare('
            SELECT l.lab_id, l.lab_name, c.cluster_name
            FROM labs l
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE l.lab_id IN (' . $placeholders . ')
            ORDER BY c.cluster_name ASC, l.lab_name ASC
        ');
        $labs_stmt->bind_param($types, ...$lab_scope_ids);
        $labs_stmt->execute();
    } else {
        $labs_stmt = null;
    }
} else {
    $labs_stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, c.cluster_name
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        WHERE l.cluster_id = ?
        ORDER BY c.cluster_name ASC, l.lab_name ASC
    ');
    $labs_stmt->bind_param('i', $admin_cluster_id);
    $labs_stmt->execute();
}
if ($labs_stmt) {
    $labs_result = $labs_stmt->get_result();
    while ($row = $labs_result->fetch_assoc()) {
        $lab_options[] = $row;
    }
    $labs_stmt->close();
}

$assets = [];
$search_like = '%' . $search . '%';
if ($selected_lab > 0) {
    if ($is_super_admin) {
        $assets_stmt = $mysqli->prepare('
            SELECT a.asset_id, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count, l.lab_id, l.lab_name, c.cluster_name
            FROM assets a
            JOIN labs l ON l.lab_id = a.lab_id
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE a.lab_id = ? AND (l.lab_name LIKE ? OR c.cluster_name LIKE ?)
            ORDER BY a.asset_name ASC
        ');
        $assets_stmt->bind_param('iss', $selected_lab, $search_like, $search_like);
    } elseif ($is_lab_supervisor) {
        if (in_array($selected_lab, $lab_scope_ids, true)) {
            $assets_stmt = $mysqli->prepare('
                SELECT a.asset_id, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count, l.lab_id, l.lab_name, c.cluster_name
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                JOIN clusters c ON c.cluster_id = l.cluster_id
                WHERE a.lab_id = ? AND (l.lab_name LIKE ? OR c.cluster_name LIKE ?)
                ORDER BY a.asset_name ASC
            ');
            $assets_stmt->bind_param('iss', $selected_lab, $search_like, $search_like);
        } else {
            $assets_stmt = null;
        }
    } else {
        $assets_stmt = $mysqli->prepare('
            SELECT a.asset_id, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count, l.lab_id, l.lab_name, c.cluster_name
            FROM assets a
            JOIN labs l ON l.lab_id = a.lab_id
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE a.lab_id = ? AND (l.lab_name LIKE ? OR c.cluster_name LIKE ?) AND l.cluster_id = ?
            ORDER BY a.asset_name ASC
        ');
        $assets_stmt->bind_param('issi', $selected_lab, $search_like, $search_like, $admin_cluster_id);
    }
} else {
    if ($is_super_admin) {
        $assets_stmt = $mysqli->prepare('
            SELECT a.asset_id, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count, l.lab_id, l.lab_name, c.cluster_name
            FROM assets a
            JOIN labs l ON l.lab_id = a.lab_id
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE l.lab_name LIKE ? OR c.cluster_name LIKE ?
            ORDER BY c.cluster_name ASC, l.lab_name ASC, a.asset_name ASC
        ');
        $assets_stmt->bind_param('ss', $search_like, $search_like);
    } elseif ($is_lab_supervisor) {
        if ($lab_scope_ids) {
            $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
            $types = str_repeat('i', count($lab_scope_ids));
            $assets_stmt = $mysqli->prepare('
                SELECT a.asset_id, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count, l.lab_id, l.lab_name, c.cluster_name
                FROM assets a
                JOIN labs l ON l.lab_id = a.lab_id
                JOIN clusters c ON c.cluster_id = l.cluster_id
                WHERE (l.lab_name LIKE ? OR c.cluster_name LIKE ?) AND l.lab_id IN (' . $placeholders . ')
                ORDER BY c.cluster_name ASC, l.lab_name ASC, a.asset_name ASC
            ');
            $assets_stmt->bind_param('ss' . $types, $search_like, $search_like, ...$lab_scope_ids);
        } else {
            $assets_stmt = null;
        }
    } else {
        $assets_stmt = $mysqli->prepare('
            SELECT a.asset_id, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count, l.lab_id, l.lab_name, c.cluster_name
            FROM assets a
            JOIN labs l ON l.lab_id = a.lab_id
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE (l.lab_name LIKE ? OR c.cluster_name LIKE ?) AND l.cluster_id = ?
            ORDER BY c.cluster_name ASC, l.lab_name ASC, a.asset_name ASC
        ');
        $assets_stmt->bind_param('ssi', $search_like, $search_like, $admin_cluster_id);
    }
}
if ($assets_stmt) {
    $assets_stmt->execute();
    $assets_result = $assets_stmt->get_result();
    while ($row = $assets_result->fetch_assoc()) {
        $assets[] = $row;
    }
    $assets_stmt->close();
}

$asset_cards = [];
if ($is_super_admin) {
    if ($selected_cluster > 0) {
        $directory_sql = '
            SELECT
                COALESCE(s.supervisor_id, 0) AS supervisor_id,
                COALESCE(s.supervisor_name, "Unassigned") AS supervisor_name,
                COALESCE(s.supervisor_email, "-") AS supervisor_email,
                COALESCE(s.supervisor_room_no, "-") AS supervisor_room_no,
                c.cluster_name,
                l.lab_id,
                l.lab_name,
                l.lab_description,
                l.maintenance_status,
                a.asset_id,
                a.asset_name,
                a.asset_status,
                a.asset_unavailable_reason,
                a.asset_count
            FROM labs l
            JOIN clusters c ON c.cluster_id = l.cluster_id
            LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
            LEFT JOIN assets a ON a.lab_id = l.lab_id
            WHERE l.cluster_id = ?
        ';
        if ($selected_supervisor > 0) {
            $directory_sql .= ' AND l.supervisor_id = ?';
        }
        $directory_sql .= ' ORDER BY supervisor_name ASC, l.lab_name ASC, a.asset_name ASC';
        $directory_stmt = $mysqli->prepare($directory_sql);
        if ($selected_supervisor > 0) {
            $directory_stmt->bind_param('ii', $selected_cluster, $selected_supervisor);
        } else {
            $directory_stmt->bind_param('i', $selected_cluster);
        }
        $directory_stmt->execute();
        $directory_result = $directory_stmt->get_result();
        while ($row = $directory_result->fetch_assoc()) {
            $supervisor_key = (string) $row['supervisor_id'];
            if (!isset($asset_cards[$supervisor_key])) {
                $asset_cards[$supervisor_key] = [
                    'type' => 'supervisor',
                    'supervisor_id' => (int) $row['supervisor_id'],
                    'supervisor_name' => $row['supervisor_name'],
                    'email' => $row['supervisor_email'],
                    'room' => $row['supervisor_room_no'],
                    'cluster_name' => $row['cluster_name'],
                    'labs' => []
                ];
            }
            $lab_key = (int) $row['lab_id'];
            if (!isset($asset_cards[$supervisor_key]['labs'][$lab_key])) {
                $asset_cards[$supervisor_key]['labs'][$lab_key] = [
                    'lab_id' => $lab_key,
                    'lab_name' => $row['lab_name'],
                    'lab_description' => $row['lab_description'],
                    'lab_status' => ($row['maintenance_status'] ?? 'available') === 'maintenance' ? 'Maintenance' : 'Available',
                    'assets' => []
                ];
            }
            if (!empty($row['asset_id'])) {
                $asset_cards[$supervisor_key]['labs'][$lab_key]['assets'][] = [
                    'asset_id' => (int) $row['asset_id'],
                    'asset_name' => $row['asset_name'],
                    'asset_status' => $row['asset_status'],
                    'asset_unavailable_reason' => $row['asset_unavailable_reason'],
                    'asset_count' => (int) $row['asset_count']
                ];
            }
        }
        $directory_stmt->close();
    }
} else {
    if ($is_lab_supervisor && $lab_scope_ids) {
        $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
        $types = str_repeat('i', count($lab_scope_ids));
        $directory_stmt = $mysqli->prepare("
            SELECT
                l.lab_id,
                l.lab_name,
                l.lab_description,
                l.maintenance_status,
                c.cluster_name,
                a.asset_id,
                a.asset_name,
                a.asset_status,
                a.asset_unavailable_reason,
                a.asset_count
            FROM labs l
            JOIN clusters c ON c.cluster_id = l.cluster_id
            LEFT JOIN assets a ON a.lab_id = l.lab_id
            WHERE l.lab_id IN ($placeholders)
            ORDER BY l.lab_name ASC, a.asset_name ASC
        ");
        $directory_stmt->bind_param($types, ...$lab_scope_ids);
        $directory_stmt->execute();
        $directory_result = $directory_stmt->get_result();
        while ($row = $directory_result->fetch_assoc()) {
            $lab_key = (int) $row['lab_id'];
            if (!isset($asset_cards[$lab_key])) {
                $asset_cards[$lab_key] = [
                    'type' => 'lab',
                    'cluster_name' => $row['cluster_name'],
                    'lab_name' => $row['lab_name'],
                    'lab_description' => $row['lab_description'],
                    'lab_status' => ($row['maintenance_status'] ?? 'available') === 'maintenance' ? 'Maintenance' : 'Available',
                    'assets' => []
                ];
            }
            if (!empty($row['asset_id'])) {
                $asset_cards[$lab_key]['assets'][] = [
                    'asset_id' => (int) $row['asset_id'],
                    'asset_name' => $row['asset_name'],
                    'asset_status' => $row['asset_status'],
                    'asset_unavailable_reason' => $row['asset_unavailable_reason'],
                    'asset_count' => (int) $row['asset_count']
                ];
            }
        }
        $directory_stmt->close();
    } elseif ($admin_cluster_id) {
        $directory_stmt = $mysqli->prepare('
            SELECT
                COALESCE(s.supervisor_id, 0) AS supervisor_id,
                COALESCE(s.supervisor_name, "Unassigned") AS supervisor_name,
                COALESCE(s.supervisor_email, "-") AS supervisor_email,
                COALESCE(s.supervisor_room_no, "-") AS supervisor_room_no,
                l.lab_id,
                l.lab_name,
                l.lab_description,
                l.maintenance_status,
                a.asset_id,
                a.asset_name,
                a.asset_status,
                a.asset_unavailable_reason,
                a.asset_count
            FROM labs l
            LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
            LEFT JOIN assets a ON a.lab_id = l.lab_id
            WHERE l.cluster_id = ?
            ORDER BY supervisor_name ASC, l.lab_name ASC, a.asset_name ASC
        ');
        $directory_stmt->bind_param('i', $admin_cluster_id);
        $directory_stmt->execute();
        $directory_result = $directory_stmt->get_result();
        while ($row = $directory_result->fetch_assoc()) {
            $supervisor_key = (string) $row['supervisor_id'];
            if (!isset($asset_cards[$supervisor_key])) {
                $asset_cards[$supervisor_key] = [
                    'type' => 'supervisor',
                    'supervisor_name' => $row['supervisor_name'],
                    'email' => $row['supervisor_email'],
                    'room' => $row['supervisor_room_no'],
                    'cluster_name' => $lab_options[0]['cluster_name'] ?? '',
                    'labs' => []
                ];
            }
            $lab_key = (int) $row['lab_id'];
            if (!isset($asset_cards[$supervisor_key]['labs'][$lab_key])) {
                $asset_cards[$supervisor_key]['labs'][$lab_key] = [
                    'lab_id' => $lab_key,
                    'lab_name' => $row['lab_name'],
                    'lab_description' => $row['lab_description'],
                    'lab_status' => ($row['maintenance_status'] ?? 'available') === 'maintenance' ? 'Maintenance' : 'Available',
                    'assets' => []
                ];
            }
            if (!empty($row['asset_id'])) {
                $asset_cards[$supervisor_key]['labs'][$lab_key]['assets'][] = [
                    'asset_id' => (int) $row['asset_id'],
                    'asset_name' => $row['asset_name'],
                    'asset_status' => $row['asset_status'],
                    'asset_unavailable_reason' => $row['asset_unavailable_reason'],
                    'asset_count' => (int) $row['asset_count']
                ];
            }
        }
        $directory_stmt->close();
    }
}

$asset_card_pagination = null;
if (!empty($asset_cards)) {
    $asset_card_pagination = paginate_items($asset_cards, $card_page, 6);
    $asset_cards = $asset_card_pagination['items'];
}

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$export_params = [
    'type' => 'assets',
    'search' => $search,
    'lab' => $selected_lab,
    'cluster' => $selected_cluster,
    'supervisor' => $selected_supervisor
];
$layout_path = __DIR__ . '/templates/layouts/admin.php';
if ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
}
$layout = require $layout_path;
$active = 'asset-management';
$app_css_version = @filemtime(__DIR__ . '/assets/app.css') ?: time();
$app_js_version = @filemtime(__DIR__ . '/assets/app.js') ?: time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management</title>
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
                        <h1>Asset Management</h1>
                        <p>Manage lab assets by lab.</p>
                    </div>
                    <div class="breadcrumb">Home / Management / Asset Management</div>
                </div>

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
                            <h2>Assets</h2>
                            <p>Assign assets to labs and track availability.</p>
                        </div>
                        <div class="banner-links">
                            <a class="btn ghost" href="management-export.php?<?php echo htmlspecialchars(http_build_query($export_params)); ?>">Export Excel</a>
                            <?php if ($can_edit_assets): ?>
                                <button class="btn primary" type="button" data-modal="asset-modal">Add Asset</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$is_super_admin): ?>
                    <form class="filters" method="GET" action="assets-management.php">
                        <input type="text" name="search" placeholder="Search lab or cluster" value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!$is_super_admin): ?>
                            <select name="lab">
                                <option value="0">All Labs</option>
                                <?php foreach ($lab_options as $lab): ?>
                                    <option value="<?php echo (int) $lab['lab_id']; ?>"<?php echo $selected_lab === (int) $lab['lab_id'] ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lab['cluster_name'] . ' - ' . $lab['lab_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button class="btn primary" type="submit">Filter</button>
                    </form>
                    <?php endif; ?>
                    <div class="asset-management-tabs" role="tablist" aria-label="Asset management sections">
                        <a class="asset-management-tab is-active" href="assets-management.php" aria-current="page">Asset Records</a>
                        <a class="asset-management-tab" href="asset-transfer-requests.php">Transfer Requests</a>
                    </div>
                </div>

                <?php if ($is_super_admin): ?>
                    <?php if ($selected_cluster <= 0): ?>
                        <div class="cluster-grid cluster-grid-two-rows">
                            <?php foreach ($cluster_options as $cluster_option): ?>
                                <?php $cluster_option_id = (int) $cluster_option['cluster_id']; ?>
                                <div class="cluster-card clickable" data-href="assets-management.php?cluster=<?php echo $cluster_option_id; ?>">
                                    <div class="cluster-card-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($cluster_option['cluster_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($cluster_option['cluster_description'] ?: 'No description provided.'); ?></p>
                                        </div>
                                    </div>
                                    <div class="cluster-meta">
                                        <?php echo (int) ($cluster_meta[$cluster_option_id]['supervisor_count'] ?? 0); ?> supervisors · <?php echo (int) ($cluster_meta[$cluster_option_id]['lab_count'] ?? 0); ?> labs
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!$asset_cards): ?>
                        <div class="card">
                            <div class="banner">
                                <div>
                                    <h2><?php echo htmlspecialchars($selected_cluster_info['cluster_name'] ?? 'Selected Cluster'); ?></h2>
                                    <p><?php echo htmlspecialchars($selected_cluster_info['cluster_description'] ?? ''); ?></p>
                                </div>
                                <div class="banner-links">
                                    <a class="btn ghost" href="assets-management.php">Back to clusters</a>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <p class="muted-text">No asset records found for the selected cluster or supervisor.</p>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="banner">
                                <div>
                                    <h2><?php echo htmlspecialchars($selected_cluster_info['cluster_name'] ?? 'Selected Cluster'); ?></h2>
                                    <p><?php echo htmlspecialchars($selected_cluster_info['cluster_description'] ?? ''); ?></p>
                                </div>
                                <div class="banner-links">
                                    <a class="btn ghost" href="assets-management.php">Back to clusters</a>
                                </div>
                            </div>
                            <form class="filters" method="GET" action="assets-management.php">
                                <input type="hidden" name="cluster" value="<?php echo (int) $selected_cluster; ?>">
                                <select name="supervisor" id="asset-supervisor-filter">
                                    <option value="0">All supervisors</option>
                                    <?php foreach ($supervisor_options as $supervisor_option): ?>
                                        <option value="<?php echo (int) $supervisor_option['supervisor_id']; ?>"<?php echo $selected_supervisor === (int) $supervisor_option['supervisor_id'] ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supervisor_option['supervisor_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn primary" type="submit">View Supervisor</button>
                            </form>
                        </div>
                        <div class="asset-supervisor-grid">
                            <?php foreach ($asset_cards as $asset_card): ?>
                                <div class="cluster-card asset-supervisor-card clickable" data-href="assets-management.php?cluster=<?php echo (int) $selected_cluster; ?>&supervisor=<?php echo (int) ($asset_card['supervisor_id'] ?? 0); ?>">
                                    <div class="cluster-card-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($asset_card['supervisor_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($asset_card['email']); ?></p>
                                            <p><?php echo htmlspecialchars($asset_card['cluster_name']); ?></p>
                                        </div>
                                        <div class="card-actions">
                                            <span class="badge"><?php echo count($asset_card['labs']); ?> labs</span>
                                            <?php if ($selected_supervisor > 0): ?>
                                                <span class="btn ghost small is-disabled">Selected</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="muted-text">Room: <?php echo htmlspecialchars($asset_card['room'] ?: '-'); ?></div>
                                    <div class="lab-chip-list">
                                        <?php foreach ($asset_card['labs'] as $lab): ?>
                                            <a
                                                class="lab-chip lab-detail-link"
                                                href="assets-management-lab.php?lab=<?php echo (int) $lab['lab_id']; ?>"
                                            >
                                                <span><?php echo htmlspecialchars($lab['lab_name']); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($asset_card_pagination && $asset_card_pagination['total_pages'] > 1): ?>
                            <div class="pagination">
                                <?php
                                $prev_page = max(1, $asset_card_pagination['current_page'] - 1);
                                $next_page = min($asset_card_pagination['total_pages'], $asset_card_pagination['current_page'] + 1);
                                $pagination_query = 'search=' . urlencode($search) . '&cluster=' . (int) $selected_cluster . '&supervisor=' . (int) $selected_supervisor;
                                ?>
                                <a class="btn ghost small<?php echo $asset_card_pagination['current_page'] <= 1 ? ' is-disabled' : ''; ?>" href="assets-management.php?<?php echo $pagination_query; ?>&page=<?php echo (int) $prev_page; ?>">Previous</a>
                                <div class="pagination-status">Page <?php echo (int) $asset_card_pagination['current_page']; ?> of <?php echo (int) $asset_card_pagination['total_pages']; ?></div>
                                <a class="btn ghost small<?php echo $asset_card_pagination['current_page'] >= $asset_card_pagination['total_pages'] ? ' is-disabled' : ''; ?>" href="assets-management.php?<?php echo $pagination_query; ?>&page=<?php echo (int) $next_page; ?>">Next</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="asset-supervisor-grid">
                        <?php foreach ($asset_cards as $card_index => $asset_card): ?>
                            <?php if ($asset_card['type'] === 'supervisor'): ?>
                                <div class="cluster-card asset-supervisor-card">
                                    <div class="cluster-card-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($asset_card['supervisor_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($asset_card['email']); ?></p>
                                            <p><?php echo htmlspecialchars($asset_card['cluster_name']); ?></p>
                                        </div>
                                        <div class="card-actions">
                                            <span class="btn ghost small is-disabled">View Only</span>
                                        </div>
                                    </div>
                                    <div class="cluster-meta"><?php echo count($asset_card['labs']); ?> labs</div>
                                    <div class="muted-text">Room: <?php echo htmlspecialchars($asset_card['room'] ?: '-'); ?></div>
                                    <div class="lab-chip-list">
                                        <?php foreach ($asset_card['labs'] as $lab): ?>
                                            <a
                                                class="lab-chip lab-detail-link"
                                                href="assets-management-lab.php?lab=<?php echo (int) $lab['lab_id']; ?>"
                                            >
                                                <span><?php echo htmlspecialchars($lab['lab_name']); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <a class="cluster-card asset-supervisor-card asset-card-link clickable" href="assets-management-lab.php?lab=<?php echo (int) $card_index; ?>">
                                    <div class="cluster-card-header">
                                        <div>
                                            <h3><?php echo htmlspecialchars($asset_card['lab_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($asset_card['cluster_name']); ?></p>
                                        </div>
                                        <div class="card-actions">
                                            <span class="badge"><?php echo htmlspecialchars($asset_card['lab_status']); ?></span>
                                        </div>
                                    </div>
                                    <div class="cluster-meta"><?php echo count($asset_card['assets']); ?> assets</div>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($asset_card_pagination && $asset_card_pagination['total_pages'] > 1): ?>
                        <div class="pagination">
                            <?php
                            $prev_page = max(1, $asset_card_pagination['current_page'] - 1);
                            $next_page = min($asset_card_pagination['total_pages'], $asset_card_pagination['current_page'] + 1);
                            $pagination_query = 'search=' . urlencode($search) . '&lab=' . (int) $selected_lab;
                            ?>
                            <a class="btn ghost small<?php echo $asset_card_pagination['current_page'] <= 1 ? ' is-disabled' : ''; ?>" href="assets-management.php?<?php echo $pagination_query; ?>&page=<?php echo (int) $prev_page; ?>">Previous</a>
                            <div class="pagination-status">Page <?php echo (int) $asset_card_pagination['current_page']; ?> of <?php echo (int) $asset_card_pagination['total_pages']; ?></div>
                            <a class="btn ghost small<?php echo $asset_card_pagination['current_page'] >= $asset_card_pagination['total_pages'] ? ' is-disabled' : ''; ?>" href="assets-management.php?<?php echo $pagination_query; ?>&page=<?php echo (int) $next_page; ?>">Next</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <footer class="footer">&copy; Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <?php if ($can_edit_assets): ?>
    <div class="modal" id="asset-modal">
        <div class="modal-content">
            <form method="POST" id="asset-form">
                <input type="hidden" name="action" value="add_asset" id="asset-action">
                <input type="hidden" name="asset_id" id="asset-id">
                <div class="modal-header">
                    <h2 id="asset-modal-title">Add Asset</h2>
                    <button class="icon-button" type="button" data-close="asset-modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="asset-lab">Lab</label>
                        <select id="asset-lab" name="lab_id" required>
                            <option value="">Select lab</option>
                            <?php foreach ($lab_options as $lab): ?>
                                <option value="<?php echo (int) $lab['lab_id']; ?>">
                                    <?php echo htmlspecialchars($lab['cluster_name'] . ' - ' . $lab['lab_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="asset-name">Asset Name</label>
                        <input id="asset-name" name="asset_name" type="text" required>
                    </div>
                    <div>
                        <label for="asset-status">Status</label>
                        <select id="asset-status" name="asset_status" required>
                            <option value="">Select status</option>
                            <option value="Available">Available</option>
                            <option value="Unavailable">Unavailable</option>
                            <option value="Disposed">Disposed</option>
                        </select>
                    </div>
                    <div id="asset-unavailable-reason-field" hidden>
                        <label for="asset-unavailable-reason">Why unavailable?</label>
                        <textarea id="asset-unavailable-reason" name="asset_unavailable_reason" rows="3" placeholder="State why this asset is unavailable"></textarea>
                    </div>
                    <div>
                        <label for="asset-count">Count</label>
                        <input id="asset-count" name="asset_count" type="number" min="0" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="asset-modal">Cancel</button>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
        window.LABS_ASSET_FILTERS = <?php echo json_encode([
            'isSuperAdmin' => $is_super_admin,
            'selectedCluster' => $selected_cluster,
            'selectedSupervisor' => $selected_supervisor,
            'supervisorsByCluster' => $supervisor_options_map
        ]); ?>;
    </script>
    <script src="assets/app.js?v=<?php echo (int) $app_js_version; ?>"></script>
    <script>
        (function () {
            var filters = window.LABS_ASSET_FILTERS || {};
            document.querySelectorAll('.cluster-card.clickable').forEach(function (card) {
                card.addEventListener('click', function (event) {
                    if (event.target.closest('button') || event.target.closest('a') || event.target.closest('form') || event.target.closest('select')) {
                        return;
                    }
                    var href = card.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });
            });

            if (!filters.isSuperAdmin) {
                return;
            }

            var clusterFilter = document.getElementById('asset-cluster-filter');
            var supervisorFilter = document.getElementById('asset-supervisor-filter');
            if (!clusterFilter || !supervisorFilter) {
                return;
            }

            function populateSupervisors(clusterId, selectedSupervisorId) {
                var supervisorGroups = filters.supervisorsByCluster || {};
                var supervisors = supervisorGroups[String(clusterId)] || supervisorGroups[clusterId] || [];
                supervisorFilter.innerHTML = '';

                var defaultOption = document.createElement('option');
                defaultOption.value = '0';
                defaultOption.textContent = 'All supervisors';
                supervisorFilter.appendChild(defaultOption);

                supervisors.forEach(function (supervisor) {
                    var option = document.createElement('option');
                    option.value = String(supervisor.supervisor_id);
                    option.textContent = supervisor.supervisor_name;
                    if (Number(selectedSupervisorId) === Number(supervisor.supervisor_id)) {
                        option.selected = true;
                    }
                    supervisorFilter.appendChild(option);
                });

                supervisorFilter.disabled = Number(clusterId) <= 0;
                if (Number(clusterId) <= 0) {
                    supervisorFilter.value = '0';
                }
            }

            populateSupervisors(clusterFilter.value, filters.selectedSupervisor || 0);

            clusterFilter.addEventListener('change', function () {
                populateSupervisors(clusterFilter.value, 0);
            });
        })();
    </script>
    <?php if ($can_edit_assets): ?>
    <script>
        (function () {
            var modal = document.getElementById('asset-modal');
            var form = document.getElementById('asset-form');
            var actionInput = document.getElementById('asset-action');
            var title = document.getElementById('asset-modal-title');
            var assetId = document.getElementById('asset-id');
            var labSelect = document.getElementById('asset-lab');
            var nameInput = document.getElementById('asset-name');
            var statusInput = document.getElementById('asset-status');
            var unavailableReasonField = document.getElementById('asset-unavailable-reason-field');
            var unavailableReasonInput = document.getElementById('asset-unavailable-reason');
            var countInput = document.getElementById('asset-count');

            function syncUnavailableReason() {
                if (!statusInput || !unavailableReasonField || !unavailableReasonInput) {
                    return;
                }
                var isUnavailable = statusInput.value === 'Unavailable';
                unavailableReasonField.hidden = !isUnavailable;
                unavailableReasonInput.required = isUnavailable;
                if (!isUnavailable) {
                    unavailableReasonInput.value = '';
                }
            }

            document.querySelectorAll('.edit-asset').forEach(function (button) {
                button.addEventListener('click', function () {
                    actionInput.value = 'update_asset';
                    title.textContent = 'Edit Asset';
                    assetId.value = button.getAttribute('data-id');
                    labSelect.value = button.getAttribute('data-lab-id');
                    nameInput.value = button.getAttribute('data-name');
                    statusInput.value = button.getAttribute('data-status');
                    if (unavailableReasonInput) {
                        unavailableReasonInput.value = button.getAttribute('data-unavailable-reason') || '';
                    }
                    countInput.value = button.getAttribute('data-count');
                    syncUnavailableReason();
                    modal.classList.add('active');
                });
            });

            document.querySelectorAll('[data-modal="asset-modal"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    actionInput.value = 'add_asset';
                    title.textContent = 'Add Asset';
                    assetId.value = '';
                    nameInput.value = '';
                    statusInput.value = '';
                    if (unavailableReasonInput) {
                        unavailableReasonInput.value = '';
                    }
                    countInput.value = 0;
                    syncUnavailableReason();
                    modal.classList.add('active');
                });
            });

            if (statusInput) {
                statusInput.addEventListener('change', syncUnavailableReason);
            }

            document.querySelectorAll('[data-close="asset-modal"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    modal.classList.remove('active');
                });
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
