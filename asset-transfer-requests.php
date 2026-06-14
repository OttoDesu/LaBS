<?php
require_once __DIR__ . '/init.php';
require_login();
require_management();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? 'public';
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$is_cluster_admin = is_cluster_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$lab_scope_ids = $is_lab_supervisor ? get_lab_supervisor_lab_ids($mysqli, $user_id) : [];
$can_request_transfer = $is_cluster_admin || $is_lab_supervisor;
$search = trim((string) ($_GET['search'] ?? ''));
$selected_lab = isset($_GET['lab']) ? (int) $_GET['lab'] : 0;
$flash_info = get_flash('info');
$flash_error = get_flash('error');

function asset_transfer_has_asset_image_path(mysqli $mysqli): bool {
    static $has_column = null;
    if ($has_column !== null) {
        return $has_column;
    }

    $stmt = $mysqli->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'assets'
          AND column_name = 'asset_image_path'
        LIMIT 1
    ");
    $stmt->execute();
    $has_column = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $has_column;
}

function asset_transfer_log(mysqli $mysqli, int $request_id, string $event, int $actor_id, ?string $notes = null): void {
    $stmt = $mysqli->prepare('
        INSERT INTO asset_transfer_requests_log (request_id, event, actor_id, notes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->bind_param('isis', $request_id, $event, $actor_id, $notes);
    $stmt->execute();
    $stmt->close();
}

function asset_transfer_user_can_approve(array $request, string $user_type, int $user_id, ?int $admin_cluster_id, array $lab_scope_ids): bool {
    if (($request['status'] ?? '') !== 'pending' || (int) ($request['requested_by'] ?? 0) === $user_id) {
        return false;
    }

    $requester_role = $request['requester_role'] ?? '';
    $source_lab_id = (int) ($request['source_lab_id'] ?? 0);
    $destination_lab_id = (int) ($request['destination_lab_id'] ?? 0);
    $source_cluster_id = (int) ($request['source_cluster_id'] ?? 0);
    $destination_cluster_id = (int) ($request['destination_cluster_id'] ?? 0);

    if ($requester_role === 'cluster_admin' && is_lab_supervisor($user_type)) {
        return in_array($source_lab_id, $lab_scope_ids, true) || in_array($destination_lab_id, $lab_scope_ids, true);
    }

    if ($requester_role === 'lab_supervisor' && is_cluster_admin($user_type) && $admin_cluster_id) {
        return $source_cluster_id === $admin_cluster_id || $destination_cluster_id === $admin_cluster_id;
    }

    return false;
}

function asset_transfer_get_request(mysqli $mysqli, int $request_id, bool $for_update = false): ?array {
    $lock = $for_update ? ' FOR UPDATE' : '';
    $stmt = $mysqli->prepare("
        SELECT
            atr.*,
            a.asset_name,
            a.asset_status,
            a.asset_count,
            a.lab_id AS current_lab_id,
            sl.lab_name AS source_lab_name,
            dl.lab_name AS destination_lab_name,
            sl.cluster_id AS source_cluster_id,
            dl.cluster_id AS destination_cluster_id,
            ru.name AS requested_by_name,
            ru.user_type AS requester_role,
            au.name AS approved_by_name
        FROM asset_transfer_requests atr
        JOIN assets a ON a.asset_id = atr.asset_id
        JOIN labs sl ON sl.lab_id = atr.source_lab_id
        JOIN labs dl ON dl.lab_id = atr.destination_lab_id
        JOIN users ru ON ru.id = atr.requested_by
        LEFT JOIN users au ON au.id = atr.approved_by
        WHERE atr.request_id = ?
        LIMIT 1{$lock}
    ");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $request;
}

function asset_transfer_visible_requests(mysqli $mysqli, string $user_type, int $user_id, ?int $admin_cluster_id, array $lab_scope_ids): array {
    if (is_super_admin($user_type)) {
        $stmt = $mysqli->prepare('
            SELECT
                atr.*,
                a.asset_name,
                sl.lab_name AS source_lab_name,
                dl.lab_name AS destination_lab_name,
                ru.name AS requested_by_name,
                ru.user_type AS requester_role,
                au.name AS approved_by_name,
                sl.cluster_id AS source_cluster_id,
                dl.cluster_id AS destination_cluster_id
            FROM asset_transfer_requests atr
            JOIN assets a ON a.asset_id = atr.asset_id
            JOIN labs sl ON sl.lab_id = atr.source_lab_id
            JOIN labs dl ON dl.lab_id = atr.destination_lab_id
            JOIN users ru ON ru.id = atr.requested_by
            LEFT JOIN users au ON au.id = atr.approved_by
            ORDER BY atr.requested_at DESC, atr.request_id DESC
        ');
        $stmt->execute();
    } elseif (is_cluster_admin($user_type) && $admin_cluster_id) {
        $stmt = $mysqli->prepare('
            SELECT
                atr.*,
                a.asset_name,
                sl.lab_name AS source_lab_name,
                dl.lab_name AS destination_lab_name,
                ru.name AS requested_by_name,
                ru.user_type AS requester_role,
                au.name AS approved_by_name,
                sl.cluster_id AS source_cluster_id,
                dl.cluster_id AS destination_cluster_id
            FROM asset_transfer_requests atr
            JOIN assets a ON a.asset_id = atr.asset_id
            JOIN labs sl ON sl.lab_id = atr.source_lab_id
            JOIN labs dl ON dl.lab_id = atr.destination_lab_id
            JOIN users ru ON ru.id = atr.requested_by
            LEFT JOIN users au ON au.id = atr.approved_by
            WHERE sl.cluster_id = ? OR dl.cluster_id = ? OR atr.requested_by = ?
            ORDER BY atr.requested_at DESC, atr.request_id DESC
        ');
        $stmt->bind_param('iii', $admin_cluster_id, $admin_cluster_id, $user_id);
        $stmt->execute();
    } elseif (is_lab_supervisor($user_type) && $lab_scope_ids) {
        $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
        $types = str_repeat('i', count($lab_scope_ids));
        $params = array_merge($lab_scope_ids, $lab_scope_ids, [$user_id]);
        $stmt = $mysqli->prepare("
            SELECT
                atr.*,
                a.asset_name,
                sl.lab_name AS source_lab_name,
                dl.lab_name AS destination_lab_name,
                ru.name AS requested_by_name,
                ru.user_type AS requester_role,
                au.name AS approved_by_name,
                sl.cluster_id AS source_cluster_id,
                dl.cluster_id AS destination_cluster_id
            FROM asset_transfer_requests atr
            JOIN assets a ON a.asset_id = atr.asset_id
            JOIN labs sl ON sl.lab_id = atr.source_lab_id
            JOIN labs dl ON dl.lab_id = atr.destination_lab_id
            JOIN users ru ON ru.id = atr.requested_by
            LEFT JOIN users au ON au.id = atr.approved_by
            WHERE atr.source_lab_id IN ($placeholders)
               OR atr.destination_lab_id IN ($placeholders)
               OR atr.requested_by = ?
            ORDER BY atr.requested_at DESC, atr.request_id DESC
        ");
        $stmt->bind_param($types . $types . 'i', ...$params);
        $stmt->execute();
    } else {
        return [];
    }

    $result = $stmt->get_result();
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
    return $requests;
}

function asset_transfer_add_destination_stock(mysqli $mysqli, array $asset, int $destination_lab_id, int $quantity, bool $has_image_column): void {
    $existing_stmt = $mysqli->prepare('
        SELECT asset_id
        FROM assets
        WHERE lab_id = ? AND asset_name = ? AND asset_status = ?
        LIMIT 1
        FOR UPDATE
    ');
    $existing_stmt->bind_param('iss', $destination_lab_id, $asset['asset_name'], $asset['asset_status']);
    $existing_stmt->execute();
    $existing = $existing_stmt->get_result()->fetch_assoc();
    $existing_stmt->close();

    if ($existing) {
        $update_stmt = $mysqli->prepare('UPDATE assets SET asset_count = asset_count + ?, updated_at = NOW() WHERE asset_id = ?');
        $existing_asset_id = (int) $existing['asset_id'];
        $update_stmt->bind_param('ii', $quantity, $existing_asset_id);
        $update_stmt->execute();
        $update_stmt->close();
        return;
    }

    if ($has_image_column) {
        $insert_stmt = $mysqli->prepare('
            INSERT INTO assets (lab_id, asset_name, asset_status, asset_count, asset_image_path, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $asset_image_path = $asset['asset_image_path'] ?? null;
        $insert_stmt->bind_param('issis', $destination_lab_id, $asset['asset_name'], $asset['asset_status'], $quantity, $asset_image_path);
    } else {
        $insert_stmt = $mysqli->prepare('
            INSERT INTO assets (lab_id, asset_name, asset_status, asset_count, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ');
        $insert_stmt->bind_param('issi', $destination_lab_id, $asset['asset_name'], $asset['asset_status'], $quantity);
    }
    $insert_stmt->execute();
    $insert_stmt->close();
}

$has_asset_image_column = asset_transfer_has_asset_image_path($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_request') {
        if (!$can_request_transfer) {
            set_flash('error', 'Only cluster admins and lab supervisors can request asset transfers.');
            header('Location: asset-transfer-requests.php');
            exit;
        }

        $asset_id = (int) ($_POST['asset_id'] ?? 0);
        $source_lab_id = (int) ($_POST['source_lab_id'] ?? 0);
        $destination_lab_id = (int) ($_POST['destination_lab_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $asset_columns = $has_asset_image_column ? 'a.asset_id, a.lab_id, a.asset_name, a.asset_status, a.asset_count, a.asset_image_path, l.cluster_id' : 'a.asset_id, a.lab_id, a.asset_name, a.asset_status, a.asset_count, l.cluster_id';

        $asset_stmt = $mysqli->prepare("
            SELECT {$asset_columns}
            FROM assets a
            JOIN labs l ON l.lab_id = a.lab_id
            WHERE a.asset_id = ?
            LIMIT 1
        ");
        $asset_stmt->bind_param('i', $asset_id);
        $asset_stmt->execute();
        $asset = $asset_stmt->get_result()->fetch_assoc();
        $asset_stmt->close();

        $dest_stmt = $mysqli->prepare('SELECT lab_id, cluster_id FROM labs WHERE lab_id = ? LIMIT 1');
        $dest_stmt->bind_param('i', $destination_lab_id);
        $dest_stmt->execute();
        $destination_lab = $dest_stmt->get_result()->fetch_assoc();
        $dest_stmt->close();

        $error = '';
        if (!$asset || !$destination_lab) {
            $error = 'Invalid asset or destination lab selected.';
        } elseif ((int) $asset['lab_id'] !== $source_lab_id) {
            $error = 'Source lab must match the selected asset current lab.';
        } elseif ($source_lab_id === $destination_lab_id) {
            $error = 'Destination lab must be different from source lab.';
        } elseif ($quantity <= 0 || $quantity > (int) $asset['asset_count']) {
            $error = 'Quantity must be between 1 and the current asset count.';
        } elseif (strlen($reason) < 10) {
            $error = 'Please provide a complete reason for the transfer.';
        } elseif ($is_cluster_admin && ((int) $asset['cluster_id'] !== $admin_cluster_id || (int) $destination_lab['cluster_id'] !== $admin_cluster_id)) {
            $error = 'Cluster admins can only request transfers within their cluster.';
        } elseif ($is_lab_supervisor && (!in_array($source_lab_id, $lab_scope_ids, true) || (int) $destination_lab['cluster_id'] !== (int) $asset['cluster_id'])) {
            $error = 'Lab supervisors can only request transfers from assigned labs within the same cluster.';
        }

        if ($error !== '') {
            set_flash('error', $error);
            header('Location: asset-transfer-requests.php');
            exit;
        }

        $stmt = $mysqli->prepare('
            INSERT INTO asset_transfer_requests
                (asset_id, source_lab_id, destination_lab_id, quantity, reason, requested_by, requested_at, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), "pending", NOW(), NOW())
        ');
        $stmt->bind_param('iiiisi', $asset_id, $source_lab_id, $destination_lab_id, $quantity, $reason, $user_id);
        $stmt->execute();
        $request_id = (int) $stmt->insert_id;
        $stmt->close();
        asset_transfer_log($mysqli, $request_id, 'requested', $user_id, $reason);

        set_flash('info', 'Asset transfer request submitted for approval.');
        header('Location: asset-transfer-requests.php');
        exit;
    }

    if (in_array($action, ['approve_request', 'reject_request'], true)) {
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $rejection_reason = trim((string) ($_POST['rejection_reason'] ?? ''));
        $is_reject = $action === 'reject_request';

        if ($request_id <= 0 || ($is_reject && strlen($rejection_reason) < 5)) {
            set_flash('error', $is_reject ? 'Rejection reason is required.' : 'Invalid transfer request.');
            header('Location: asset-transfer-requests.php');
            exit;
        }

        try {
            $mysqli->begin_transaction();
            $request = asset_transfer_get_request($mysqli, $request_id, true);
            if (!$request || !asset_transfer_user_can_approve($request, $user_type, $user_id, $admin_cluster_id, $lab_scope_ids)) {
                throw new RuntimeException('You are not allowed to approve or reject this transfer request.');
            }

            if ($is_reject) {
                $stmt = $mysqli->prepare('
                    UPDATE asset_transfer_requests
                    SET status = "rejected", approved_by = ?, approved_at = NOW(), rejection_reason = ?, updated_at = NOW()
                    WHERE request_id = ? AND status = "pending"
                ');
                $stmt->bind_param('isi', $user_id, $rejection_reason, $request_id);
                $stmt->execute();
                $stmt->close();
                asset_transfer_log($mysqli, $request_id, 'rejected', $user_id, $rejection_reason);
                $mysqli->commit();
                set_flash('info', 'Asset transfer request rejected.');
                header('Location: asset-transfer-requests.php');
                exit;
            }

            $asset_columns = $has_asset_image_column ? 'asset_id, lab_id, asset_name, asset_status, asset_count, asset_image_path' : 'asset_id, lab_id, asset_name, asset_status, asset_count';
            $asset_stmt = $mysqli->prepare("SELECT {$asset_columns} FROM assets WHERE asset_id = ? LIMIT 1 FOR UPDATE");
            $asset_id = (int) $request['asset_id'];
            $asset_stmt->bind_param('i', $asset_id);
            $asset_stmt->execute();
            $asset = $asset_stmt->get_result()->fetch_assoc();
            $asset_stmt->close();

            $quantity = (int) $request['quantity'];
            $source_lab_id = (int) $request['source_lab_id'];
            $destination_lab_id = (int) $request['destination_lab_id'];
            if (!$asset || (int) $asset['lab_id'] !== $source_lab_id || (int) $asset['asset_count'] < $quantity) {
                throw new RuntimeException('Asset count or source lab has changed. Transfer cannot be approved.');
            }

            if ((int) $asset['asset_count'] === $quantity) {
                $move_stmt = $mysqli->prepare('UPDATE assets SET lab_id = ?, updated_at = NOW() WHERE asset_id = ?');
                $move_stmt->bind_param('ii', $destination_lab_id, $asset_id);
                $move_stmt->execute();
                $move_stmt->close();
            } else {
                $decrement_stmt = $mysqli->prepare('UPDATE assets SET asset_count = asset_count - ?, updated_at = NOW() WHERE asset_id = ?');
                $decrement_stmt->bind_param('ii', $quantity, $asset_id);
                $decrement_stmt->execute();
                $decrement_stmt->close();
                asset_transfer_add_destination_stock($mysqli, $asset, $destination_lab_id, $quantity, $has_asset_image_column);
            }

            $stmt = $mysqli->prepare('
                UPDATE asset_transfer_requests
                SET status = "approved", approved_by = ?, approved_at = NOW(), rejection_reason = NULL, updated_at = NOW()
                WHERE request_id = ? AND status = "pending"
            ');
            $stmt->bind_param('ii', $user_id, $request_id);
            $stmt->execute();
            $stmt->close();
            asset_transfer_log($mysqli, $request_id, 'approved', $user_id, null);
            asset_transfer_log($mysqli, $request_id, 'transferred', $user_id, 'Asset transfer completed.');
            $mysqli->commit();

            set_flash('info', 'Asset transfer approved and completed.');
            header('Location: asset-transfer-requests.php');
            exit;
        } catch (Throwable $exception) {
            $mysqli->rollback();
            set_flash('error', $exception->getMessage());
            header('Location: asset-transfer-requests.php');
            exit;
        }
    }
}

$lab_options = [];
if ($is_cluster_admin && $admin_cluster_id) {
    $stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, l.cluster_id, c.cluster_name
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        WHERE l.cluster_id = ?
        ORDER BY l.lab_name ASC
    ');
    $stmt->bind_param('i', $admin_cluster_id);
    $stmt->execute();
} elseif ($is_lab_supervisor && $lab_scope_ids) {
    $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
    $types = str_repeat('i', count($lab_scope_ids));
    $cluster_stmt = $mysqli->prepare("SELECT DISTINCT cluster_id FROM labs WHERE lab_id IN ($placeholders)");
    $cluster_stmt->bind_param($types, ...$lab_scope_ids);
    $cluster_stmt->execute();
    $cluster_result = $cluster_stmt->get_result();
    $cluster_ids = [];
    while ($row = $cluster_result->fetch_assoc()) {
        $cluster_ids[] = (int) $row['cluster_id'];
    }
    $cluster_stmt->close();

    if ($cluster_ids) {
        $cluster_placeholders = implode(',', array_fill(0, count($cluster_ids), '?'));
        $cluster_types = str_repeat('i', count($cluster_ids));
        $stmt = $mysqli->prepare("
            SELECT l.lab_id, l.lab_name, l.cluster_id, c.cluster_name
            FROM labs l
            JOIN clusters c ON c.cluster_id = l.cluster_id
            WHERE l.cluster_id IN ($cluster_placeholders)
            ORDER BY l.lab_name ASC
        ");
        $stmt->bind_param($cluster_types, ...$cluster_ids);
        $stmt->execute();
    } else {
        $stmt = null;
    }
} else {
    $stmt = null;
}
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lab_options[] = $row;
    }
    $stmt->close();
}

$asset_options = [];
$asset_columns = $has_asset_image_column ? 'a.asset_id, a.lab_id, a.asset_name, a.asset_status, a.asset_count, l.lab_name' : 'a.asset_id, a.lab_id, a.asset_name, a.asset_status, a.asset_count, l.lab_name';
if ($is_cluster_admin && $admin_cluster_id) {
    $stmt = $mysqli->prepare("
        SELECT {$asset_columns}
        FROM assets a
        JOIN labs l ON l.lab_id = a.lab_id
        WHERE l.cluster_id = ? AND a.asset_count > 0
        ORDER BY l.lab_name ASC, a.asset_name ASC
    ");
    $stmt->bind_param('i', $admin_cluster_id);
    $stmt->execute();
} elseif ($is_lab_supervisor && $lab_scope_ids) {
    $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
    $types = str_repeat('i', count($lab_scope_ids));
    $stmt = $mysqli->prepare("
        SELECT {$asset_columns}
        FROM assets a
        JOIN labs l ON l.lab_id = a.lab_id
        WHERE a.lab_id IN ($placeholders) AND a.asset_count > 0
        ORDER BY l.lab_name ASC, a.asset_name ASC
    ");
    $stmt->bind_param($types, ...$lab_scope_ids);
    $stmt->execute();
} else {
    $stmt = null;
}
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $asset_options[] = $row;
    }
    $stmt->close();
}

$requests = asset_transfer_visible_requests($mysqli, $user_type, $user_id, $admin_cluster_id, $lab_scope_ids);
$logs_by_request = [];
if ($requests) {
    $request_ids = array_map(static fn($request) => (int) $request['request_id'], $requests);
    $placeholders = implode(',', array_fill(0, count($request_ids), '?'));
    $types = str_repeat('i', count($request_ids));
    $log_stmt = $mysqli->prepare("
        SELECT atl.*, u.name AS actor_name
        FROM asset_transfer_requests_log atl
        JOIN users u ON u.id = atl.actor_id
        WHERE atl.request_id IN ($placeholders)
        ORDER BY atl.created_at ASC, atl.log_id ASC
    ");
    $log_stmt->bind_param($types, ...$request_ids);
    $log_stmt->execute();
    $log_result = $log_stmt->get_result();
    while ($row = $log_result->fetch_assoc()) {
        $logs_by_request[(int) $row['request_id']][] = $row;
    }
    $log_stmt->close();
}

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$layout_path = $is_lab_supervisor ? __DIR__ . '/templates/layouts/lab_supervisor.php' : __DIR__ . '/templates/layouts/admin.php';
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
    <title>Asset Transfer Requests</title>
    <link rel="stylesheet" href="assets/app.css?v=<?php echo (int) $app_css_version; ?>">
</head>
<body data-login-url="index.php">
    <div class="app">
        <?php include __DIR__ . '/templates/layouts/sidebar.php'; ?>

        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-button" id="toggle-sidebar" aria-label="Toggle sidebar">
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z" fill="currentColor"/></svg>
                    </button>
                    <div class="search">
                        <span class="search-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z" fill="currentColor"/></svg></span>
                        <input type="text" id="global-search" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-button" aria-label="Notifications"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z" fill="currentColor"/></svg></button>
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
                        <a class="menu-item" href="profile.php">Edit Profile</a>
                        <form method="POST" action="logout.php"><button class="menu-item danger" type="submit">Sign Out</button></form>
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
                        <h1>Asset Management</h1>
                        <p>Request and approve asset movement between labs.</p>
                    </div>
                    <div class="breadcrumb">Home / Management / Asset Management</div>
                </div>

                <div class="section-stack">
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2>Assets</h2>
                                <p>Assign assets to labs and track availability.</p>
                            </div>
                        </div>
                        <?php if (!$is_super_admin): ?>
                            <form class="filters" method="GET" action="assets-management.php">
                                <input type="text" name="search" placeholder="Search lab or cluster" value="<?php echo htmlspecialchars($search); ?>">
                                <select name="lab">
                                    <option value="0">All Labs</option>
                                    <?php foreach ($lab_options as $lab): ?>
                                        <option value="<?php echo (int) $lab['lab_id']; ?>"<?php echo $selected_lab === (int) $lab['lab_id'] ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars(($lab['cluster_name'] ?? '') . ' - ' . $lab['lab_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn primary" type="submit">Filter</button>
                            </form>
                        <?php endif; ?>
                        <div class="asset-management-tabs" role="tablist" aria-label="Asset management sections">
                            <a class="asset-management-tab" href="assets-management.php">Asset Records</a>
                            <a class="asset-management-tab is-active" href="asset-transfer-requests.php" aria-current="page">Transfer Requests</a>
                        </div>
                    </div>

                    <?php if ($can_request_transfer): ?>
                        <div class="card">
                            <div class="banner">
                                <div>
                                    <h2>New Transfer Request</h2>
                                    <p>Every request must include a complete reason before it can be approved.</p>
                                </div>
                            </div>
                            <form class="asset-transfer-form" method="POST">
                                <input type="hidden" name="action" value="create_request">
                                <div class="form-field">
                                    <label for="asset-id">Asset</label>
                                    <select id="asset-id" name="asset_id" required>
                                        <option value="">Select asset</option>
                                        <?php foreach ($asset_options as $asset_option): ?>
                                            <option
                                                value="<?php echo (int) $asset_option['asset_id']; ?>"
                                                data-source-lab-id="<?php echo (int) $asset_option['lab_id']; ?>"
                                                data-max-quantity="<?php echo (int) $asset_option['asset_count']; ?>"
                                            >
                                                <?php echo htmlspecialchars($asset_option['lab_name'] . ' - ' . $asset_option['asset_name'] . ' (' . $asset_option['asset_count'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="source-lab-id">Source Lab</label>
                                    <select id="source-lab-id" name="source_lab_id" required>
                                        <option value="">Auto from asset</option>
                                        <?php foreach ($lab_options as $lab_option): ?>
                                            <option value="<?php echo (int) $lab_option['lab_id']; ?>"><?php echo htmlspecialchars($lab_option['lab_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="destination-lab-id">Destination Lab</label>
                                    <select id="destination-lab-id" name="destination_lab_id" required>
                                        <option value="">Select destination</option>
                                        <?php foreach ($lab_options as $lab_option): ?>
                                            <option value="<?php echo (int) $lab_option['lab_id']; ?>"><?php echo htmlspecialchars($lab_option['lab_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="quantity">Quantity</label>
                                    <input id="quantity" name="quantity" type="number" min="1" value="1" required>
                                </div>
                                <div class="form-field asset-transfer-reason">
                                    <label for="reason">Reason</label>
                                    <textarea id="reason" name="reason" rows="4" placeholder="Explain why this asset needs to be transferred." required></textarea>
                                    <span class="field-hint">Minimum 10 characters. This will be recorded in the audit log.</span>
                                </div>
                                <div class="asset-transfer-actions">
                                    <button class="btn primary" type="submit">Submit Request</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2>Transfer Requests</h2>
                                <p>Approved requests update asset location or stock quantity. Rejected requests do not change assets.</p>
                            </div>
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Route</th>
                                        <th>Qty</th>
                                        <th>Requested</th>
                                        <th>Status</th>
                                        <th>Decision</th>
                                        <th>Audit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <?php
                                            $can_approve = asset_transfer_user_can_approve($request, $user_type, $user_id, $admin_cluster_id, $lab_scope_ids);
                                            $status_class = $request['status'] === 'approved' ? 'badge-success' : ($request['status'] === 'rejected' ? 'badge-danger' : 'badge-warning');
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($request['asset_name']); ?></strong>
                                                <div class="muted-text">Reason: <?php echo htmlspecialchars($request['reason']); ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($request['source_lab_name']); ?><br>
                                                <span class="muted-text">to <?php echo htmlspecialchars($request['destination_lab_name']); ?></span>
                                            </td>
                                            <td><?php echo (int) $request['quantity']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($request['requested_by_name']); ?><br>
                                                <span class="muted-text"><?php echo htmlspecialchars(format_display_date($request['requested_at'])); ?></span>
                                            </td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($request['status'])); ?></span></td>
                                            <td>
                                                <?php if ($can_approve): ?>
                                                    <div class="transfer-decision-actions">
                                                        <form method="POST" class="inline-action-form" onsubmit="return confirm('Approve this transfer request?');">
                                                            <input type="hidden" name="action" value="approve_request">
                                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['request_id']; ?>">
                                                            <button class="btn primary small" type="submit">Approve</button>
                                                        </form>
                                                        <button
                                                            class="btn danger small reject-transfer-button"
                                                            type="button"
                                                            data-request-id="<?php echo (int) $request['request_id']; ?>"
                                                            data-asset-name="<?php echo htmlspecialchars($request['asset_name']); ?>"
                                                        >
                                                            Reject
                                                        </button>
                                                    </div>
                                                <?php elseif ($request['status'] === 'approved'): ?>
                                                    <?php echo htmlspecialchars($request['approved_by_name'] ?? '-'); ?><br>
                                                    <span class="muted-text"><?php echo htmlspecialchars(format_display_date($request['approved_at'])); ?></span>
                                                <?php elseif ($request['status'] === 'rejected'): ?>
                                                    <span class="muted-text"><?php echo htmlspecialchars($request['rejection_reason'] ?? '-'); ?></span>
                                                <?php else: ?>
                                                    <span class="muted-text">Waiting for approver</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="status-log">
                                                    <?php foreach ($logs_by_request[(int) $request['request_id']] ?? [] as $log): ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars(ucfirst($log['event'])); ?></strong>
                                                            <span class="muted-text"><?php echo htmlspecialchars($log['actor_name'] . ' - ' . format_display_date($log['created_at'])); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$requests): ?>
                                        <tr>
                                            <td colspan="7">No transfer requests found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <footer class="footer">&copy; Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <div class="modal" id="reject-transfer-modal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject_request">
                <input type="hidden" name="request_id" id="reject-transfer-request-id">
                <div class="modal-header">
                    <h2>Reject Transfer Request</h2>
                    <button class="icon-button" type="button" data-close="reject-transfer-modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="muted-text" id="reject-transfer-summary">Please provide a reason for rejecting this transfer request.</p>
                    <div>
                        <label for="reject-transfer-reason">Rejection Reason</label>
                        <textarea id="reject-transfer-reason" name="rejection_reason" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn ghost" type="button" data-close="reject-transfer-modal">Cancel</button>
                    <button class="btn danger" type="submit">Reject Request</button>
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
            var assetSelect = document.getElementById('asset-id');
            var sourceSelect = document.getElementById('source-lab-id');
            var quantityInput = document.getElementById('quantity');
            if (!assetSelect || !sourceSelect || !quantityInput) {
                return;
            }
            assetSelect.addEventListener('change', function () {
                var option = assetSelect.options[assetSelect.selectedIndex];
                var sourceLabId = option ? option.getAttribute('data-source-lab-id') : '';
                var maxQuantity = option ? option.getAttribute('data-max-quantity') : '';
                sourceSelect.value = sourceLabId || '';
                if (maxQuantity) {
                    quantityInput.max = maxQuantity;
                    if (parseInt(quantityInput.value || '0', 10) > parseInt(maxQuantity, 10)) {
                        quantityInput.value = maxQuantity;
                    }
                }
            });
        })();
        (function () {
            var modal = document.getElementById('reject-transfer-modal');
            var requestInput = document.getElementById('reject-transfer-request-id');
            var reasonInput = document.getElementById('reject-transfer-reason');
            var summary = document.getElementById('reject-transfer-summary');

            function closeModal() {
                if (modal) {
                    modal.classList.remove('active');
                }
            }

            document.querySelectorAll('.reject-transfer-button').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!modal || !requestInput) {
                        return;
                    }
                    requestInput.value = button.getAttribute('data-request-id') || '';
                    if (reasonInput) {
                        reasonInput.value = '';
                    }
                    if (summary) {
                        var assetName = button.getAttribute('data-asset-name') || 'this asset';
                        summary.textContent = 'Please provide a reason for rejecting the transfer request for ' + assetName + '.';
                    }
                    modal.classList.add('active');
                    if (reasonInput) {
                        reasonInput.focus();
                    }
                });
            });

            document.querySelectorAll('[data-close="reject-transfer-modal"]').forEach(function (button) {
                button.addEventListener('click', closeModal);
            });
        })();
    </script>
</body>
</html>
