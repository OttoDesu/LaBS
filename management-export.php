<?php
require_once __DIR__ . '/init.php';
require_login();
require_management();

$user_type = $_SESSION['user_type'] ?? 'public';
$session_user_id = (int) ($_SESSION['user_id'] ?? 0);
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$type = $_GET['type'] ?? '';

function export_has_column(mysqli $mysqli, string $table, string $column): bool {
    $stmt = $mysqli->prepare('
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function export_role_label(string $role): string {
    switch ($role) {
        case 'super_admin':
        case 'admin':
            return 'Admin';
        case 'cluster_admin':
            return 'Cluster Admin';
        case 'lab_supervisor':
            return 'Lab Supervisor';
        case 'uthm_student':
            return 'Student';
        case 'uthm_staff':
            return 'Staff';
        case 'public':
        default:
            return 'Public User';
    }
}

function export_status_label(?string $status): string {
    return $status === 'Approved' ? 'Booked' : (string) $status;
}

function export_fetch_all(mysqli $mysqli, string $sql, string $types = '', array $params = []): array {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function export_download(string $filename, string $title, array $headers, array $rows): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<table border="1"><thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="' . count($headers) . '">No records found</td></tr>';
    }
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

function export_lab_scope(mysqli $mysqli, int $user_id): array {
    if ($user_id <= 0) {
        return [];
    }
    return get_lab_supervisor_lab_ids($mysqli, $user_id);
}

function export_visible_cluster_ids(mysqli $mysqli, bool $is_super_admin, ?int $admin_cluster_id, bool $is_lab_supervisor, array $lab_scope_ids): array {
    if ($is_super_admin) {
        $rows = export_fetch_all($mysqli, 'SELECT cluster_id FROM clusters ORDER BY cluster_id ASC');
        return array_map('intval', array_column($rows, 'cluster_id'));
    }
    if ($admin_cluster_id) {
        return [(int) $admin_cluster_id];
    }
    if ($is_lab_supervisor && $lab_scope_ids) {
        $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
        $types = str_repeat('i', count($lab_scope_ids));
        $rows = export_fetch_all($mysqli, "SELECT DISTINCT cluster_id FROM labs WHERE lab_id IN ($placeholders)", $types, $lab_scope_ids);
        return array_map('intval', array_column($rows, 'cluster_id'));
    }

    return [];
}

$lab_scope_ids = $is_lab_supervisor ? export_lab_scope($mysqli, $session_user_id) : [];
$visible_cluster_ids = export_visible_cluster_ids($mysqli, $is_super_admin, $admin_cluster_id, $is_lab_supervisor, $lab_scope_ids);

if ($type === 'users') {
    $search = trim($_GET['search'] ?? '');
    $role_filter = $_GET['role'] ?? 'all';
    $cluster_filter = (int) ($_GET['cluster'] ?? 0);
    if ($role_filter !== 'all' && !in_array($role_filter, ['public', 'uthm_student', 'uthm_staff', 'cluster_admin', 'lab_supervisor', 'super_admin', 'admin'], true)) {
        $role_filter = 'all';
    }
    $where = ['(u.name LIKE ? OR u.email LIKE ? OR u.ic_no LIKE ?)'];
    $types = 'sss';
    $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
    if ($role_filter !== 'all') {
        $where[] = 'u.user_type = ?';
        $types .= 's';
        $params[] = $role_filter;
    }
    if ($is_super_admin && $cluster_filter > 0) {
        $where[] = 'u.cluster_id = ?';
        $types .= 'i';
        $params[] = $cluster_filter;
    } elseif (!$is_super_admin) {
        if (!$visible_cluster_ids) {
            export_download('users-export.xls', 'User Management Export', ['ID', 'Name', 'Email', 'Phone', 'IC No', 'Role', 'Cluster'], []);
        }
        $placeholders = implode(',', array_fill(0, count($visible_cluster_ids), '?'));
        $where[] = "(u.user_type = 'super_admin' OR u.user_type = 'public' OR u.cluster_id IN ($placeholders) OR (u.user_type = 'lab_supervisor' AND ls.cluster_id IN ($placeholders)))";
        $types .= str_repeat('i', count($visible_cluster_ids) * 2);
        $params = array_merge($params, $visible_cluster_ids, $visible_cluster_ids);
    }
    $rows = export_fetch_all($mysqli, '
        SELECT DISTINCT u.id, u.name, u.email, u.phone, u.ic_no, u.user_type, c.cluster_name
        FROM users u
        LEFT JOIN clusters c ON c.cluster_id = u.cluster_id
        LEFT JOIN lab_supervisor_labs lsl ON lsl.user_id = u.id
        LEFT JOIN labs ls ON ls.lab_id = lsl.lab_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY u.id ASC
    ', $types, $params);
    $export_rows = array_map(static function ($row) {
        return [
            $row['id'] ?? '',
            $row['name'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['ic_no'] ?? '',
            export_role_label((string) ($row['user_type'] ?? '')),
            $row['cluster_name'] ?? '-'
        ];
    }, $rows);
    export_download('users-export.xls', 'User Management Export', ['ID', 'Name', 'Email', 'Phone', 'IC No', 'Role', 'Cluster'], $export_rows);
}

if ($type === 'labs') {
    $where = [];
    $types = '';
    $params = [];
    if ($is_lab_supervisor) {
        if (!$lab_scope_ids) {
            export_download('labs-export.xls', 'Lab Management Export', ['Lab ID', 'Cluster', 'Lab Name', 'Capacity', 'Status', 'Maintenance Start', 'Maintenance End', 'Supervisor', 'Description'], []);
        }
        $where[] = 'l.lab_id IN (' . implode(',', array_fill(0, count($lab_scope_ids), '?')) . ')';
        $types .= str_repeat('i', count($lab_scope_ids));
        $params = array_merge($params, $lab_scope_ids);
    } elseif (!$is_super_admin) {
        $where[] = 'l.cluster_id = ?';
        $types .= 'i';
        $params[] = (int) $admin_cluster_id;
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $rows = export_fetch_all($mysqli, "
        SELECT l.lab_id, c.cluster_name, l.lab_name, l.lab_capacity, l.maintenance_status,
               l.maintenance_start_date, l.maintenance_end_date, COALESCE(s.supervisor_name, 'Unassigned') AS supervisor_name,
               l.lab_description
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
        $where_sql
        ORDER BY c.cluster_name ASC, l.lab_name ASC
    ", $types, $params);
    $export_rows = array_map(static function ($row) {
        return [
            $row['lab_id'] ?? '',
            $row['cluster_name'] ?? '',
            $row['lab_name'] ?? '',
            $row['lab_capacity'] ?? '',
            (($row['maintenance_status'] ?? 'available') === 'maintenance') ? 'Maintenance' : 'Available',
            $row['maintenance_start_date'] ?? '',
            $row['maintenance_end_date'] ?? '',
            $row['supervisor_name'] ?? 'Unassigned',
            $row['lab_description'] ?? ''
        ];
    }, $rows);
    export_download('labs-export.xls', 'Lab Management Export', ['Lab ID', 'Cluster', 'Lab Name', 'Capacity', 'Status', 'Maintenance Start', 'Maintenance End', 'Supervisor', 'Description'], $export_rows);
}

if ($type === 'assets') {
    if (!export_has_column($mysqli, 'assets', 'asset_unavailable_reason')) {
        $mysqli->query("ALTER TABLE assets ADD COLUMN asset_unavailable_reason TEXT DEFAULT NULL AFTER asset_status");
    }
    $search = trim($_GET['search'] ?? '');
    $selected_lab = (int) ($_GET['lab'] ?? 0);
    $selected_cluster = (int) ($_GET['cluster'] ?? 0);
    $selected_supervisor = (int) ($_GET['supervisor'] ?? 0);
    $where = ['(a.asset_name LIKE ? OR l.lab_name LIKE ? OR c.cluster_name LIKE ?)'];
    $types = 'sss';
    $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
    if ($selected_lab > 0) {
        $where[] = 'l.lab_id = ?';
        $types .= 'i';
        $params[] = $selected_lab;
    }
    if ($is_super_admin) {
        if ($selected_cluster > 0) {
            $where[] = 'l.cluster_id = ?';
            $types .= 'i';
            $params[] = $selected_cluster;
        }
        if ($selected_supervisor > 0) {
            $where[] = 'l.supervisor_id = ?';
            $types .= 'i';
            $params[] = $selected_supervisor;
        }
    } elseif ($is_lab_supervisor) {
        if (!$lab_scope_ids) {
            export_download('assets-export.xls', 'Asset Management Export', ['Asset ID', 'Cluster', 'Lab', 'Asset Name', 'Status', 'Unavailable Reason', 'Quantity'], []);
        }
        $where[] = 'l.lab_id IN (' . implode(',', array_fill(0, count($lab_scope_ids), '?')) . ')';
        $types .= str_repeat('i', count($lab_scope_ids));
        $params = array_merge($params, $lab_scope_ids);
    } else {
        $where[] = 'l.cluster_id = ?';
        $types .= 'i';
        $params[] = (int) $admin_cluster_id;
    }
    $rows = export_fetch_all($mysqli, '
        SELECT a.asset_id, c.cluster_name, l.lab_name, a.asset_name, a.asset_status, a.asset_unavailable_reason, a.asset_count
        FROM assets a
        JOIN labs l ON l.lab_id = a.lab_id
        JOIN clusters c ON c.cluster_id = l.cluster_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY c.cluster_name ASC, l.lab_name ASC, a.asset_name ASC
    ', $types, $params);
    $export_rows = array_map(static function ($row) {
        return [
            $row['asset_id'] ?? '',
            $row['cluster_name'] ?? '',
            $row['lab_name'] ?? '',
            $row['asset_name'] ?? '',
            $row['asset_status'] ?? '',
            $row['asset_unavailable_reason'] ?? '',
            $row['asset_count'] ?? ''
        ];
    }, $rows);
    export_download('assets-export.xls', 'Asset Management Export', ['Asset ID', 'Cluster', 'Lab', 'Asset Name', 'Status', 'Unavailable Reason', 'Quantity'], $export_rows);
}

if ($type === 'bookings') {
    $search = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status'] ?? 'all';
    $cluster_filter = (int) ($_GET['cluster'] ?? 0);
    $status_options = ['Approved', 'Cancelled', 'Rejected'];
    if ($status_filter !== 'all' && !in_array($status_filter, $status_options, true)) {
        $status_filter = 'all';
    }
    $booking_pk = get_booking_pk_column($mysqli);
    $has_rejected_by = export_has_column($mysqli, 'lab_bookings', 'rejected_by');
    $rejected_join = $has_rejected_by ? 'LEFT JOIN users ru ON ru.id = lb.rejected_by' : '';
    $rejected_select = $has_rejected_by ? 'ru.name AS rejected_by_name' : 'NULL AS rejected_by_name';
    $where = ['(u.name LIKE ? OR u.email LIKE ? OR l.lab_name LIKE ? OR lb.' . $booking_pk . ' LIKE ?)'];
    $types = 'ssss';
    $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
    if ($status_filter !== 'all') {
        $where[] = 'lb.status = ?';
        $types .= 's';
        $params[] = $status_filter;
    }
    if ($is_super_admin) {
        if ($cluster_filter > 0) {
            $where[] = 'c.cluster_id = ?';
            $types .= 'i';
            $params[] = $cluster_filter;
        }
    } elseif ($is_lab_supervisor) {
        if (!$lab_scope_ids) {
            export_download('bookings-export.xls', 'Booking Management Export', ['Booking ID', 'User', 'Email', 'Cluster', 'Lab', 'Date', 'Time Slot', 'Status', 'Reasons', 'Rejected By', 'Submitted At', 'Updated At'], []);
        }
        $where[] = 'lb.lab_id IN (' . implode(',', array_fill(0, count($lab_scope_ids), '?')) . ')';
        $types .= str_repeat('i', count($lab_scope_ids));
        $params = array_merge($params, $lab_scope_ids);
    } else {
        $where[] = 'l.cluster_id = ?';
        $types .= 'i';
        $params[] = (int) $admin_cluster_id;
    }
    $rows = export_fetch_all($mysqli, "
        SELECT lb.$booking_pk AS booking_id, lb.booking_date, lb.time_slot, lb.status,
               lb.rejection_reason, lb.cancellation_reason, lb.created_at, lb.updated_at,
               l.lab_name, c.cluster_name, u.name AS user_name, u.email AS user_email, $rejected_select
        FROM lab_bookings lb
        JOIN labs l ON lb.lab_id = l.lab_id
        JOIN clusters c ON c.cluster_id = l.cluster_id
        JOIN users u ON lb.user_id = u.id
        $rejected_join
        WHERE " . implode(' AND ', $where) . '
        ORDER BY lb.created_at DESC
    ', $types, $params);
    $export_rows = array_map(static function ($row) {
        $reason = '-';
        if (($row['status'] ?? '') === 'Rejected' && !empty($row['rejection_reason'])) {
            $reason = $row['rejection_reason'];
        } elseif (($row['status'] ?? '') === 'Cancelled' && !empty($row['cancellation_reason'])) {
            $reason = $row['cancellation_reason'];
        }
        return [
            $row['booking_id'] ?? '',
            $row['user_name'] ?? '',
            $row['user_email'] ?? '',
            $row['cluster_name'] ?? '',
            $row['lab_name'] ?? '',
            $row['booking_date'] ?? '',
            $row['time_slot'] ?? '',
            export_status_label($row['status'] ?? ''),
            $reason,
            $row['rejected_by_name'] ?? '',
            $row['created_at'] ?? '',
            $row['updated_at'] ?? ''
        ];
    }, $rows);
    export_download('bookings-export.xls', 'Booking Management Export', ['Booking ID', 'User', 'Email', 'Cluster', 'Lab', 'Date', 'Time Slot', 'Status', 'Reasons', 'Rejected By', 'Submitted At', 'Updated At'], $export_rows);
}

set_flash('error', 'Invalid export selected.');
header('Location: dashboard.php');
exit;
