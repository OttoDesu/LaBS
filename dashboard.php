<?php
require_once __DIR__ . '/init.php';
require_login();

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

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'public';
$is_admin = is_admin_user($user_type);
$is_management = is_management_user($user_type);
$is_super_admin = is_super_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$admin_cluster_id = get_admin_cluster_id();
$flash_info = get_flash('info');
$flash_error = get_flash('error');
$errors = [];
$booking_pk = get_booking_pk_column($mysqli);
$lab_scope_ids = [];
if ($is_lab_supervisor) {
    $lab_scope_ids = get_lab_supervisor_lab_ids($mysqli, $user_id);
}

$user_payload = [
    'name' => $user_name,
    'email' => $user_email,
    'userType' => $user_type
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel_booking' && !$is_management) {
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
        $booking_target = null;
        if ($booking_id <= 0) {
            $errors[] = 'Invalid booking selected.';
        }
        if ($cancellation_reason === '') {
            $errors[] = 'Cancellation reason is required.';
        }
        if (!$errors) {
            $target_stmt = $mysqli->prepare('
                SELECT lb.' . $booking_pk . ' AS booking_id, lb.user_id, lb.booking_date, lb.time_slot, l.lab_id, l.cluster_id, l.lab_name
                FROM lab_bookings lb
                JOIN labs l ON l.lab_id = lb.lab_id
                WHERE lb.' . $booking_pk . ' = ? AND lb.user_id = ?
                LIMIT 1
            ');
            if ($target_stmt) {
                $target_stmt->bind_param('ii', $booking_id, $user_id);
                $target_stmt->execute();
                $target_result = $target_stmt->get_result();
                $booking_target = $target_result ? $target_result->fetch_assoc() : null;
                $target_stmt->close();
            }
        }
        if (!$errors) {
            $stmt = $mysqli->prepare("
                UPDATE lab_bookings
                SET status = 'Cancelled', cancellation_reason = ?, updated_at = NOW()
                WHERE {$booking_pk} = ? AND user_id = ? AND status = 'Approved'
            ");
            $stmt->bind_param('sii', $cancellation_reason, $booking_id, $user_id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $stmt->close();
                if ($booking_target) {
                    $management_recipient_ids = get_lab_notification_recipient_user_ids(
                        $mysqli,
                        (int) ($booking_target['lab_id'] ?? 0),
                        (int) ($booking_target['cluster_id'] ?? 0),
                        array_filter([
                            (int) ($booking_target['user_id'] ?? 0)
                        ])
                    );
                    if ($management_recipient_ids) {
                        create_and_send_bulk_user_notifications(
                            $mysqli,
                            $management_recipient_ids,
                            'Booking cancelled for your lab',
                            ($booking_target['lab_name'] ?? 'A booking') . ' on ' . ($booking_target['booking_date'] ?? 'selected date') . ' (' . ($booking_target['time_slot'] ?? '-') . ') was cancelled by the user. Reason: ' . $cancellation_reason,
                            'warning',
                            'booking-management.php',
                            true
                        );
                    }
                }
                set_flash('info', 'Booking cancelled successfully.');
                header('Location: dashboard.php');
                exit;
            }
            $stmt->close();
            $errors[] = 'Unable to cancel booking.';
        }
    }
}
$admin_stats = null;
$admin_chart = [];
if ($is_management) {
    $total_users = 0;
    $total_bookings = 0;
    $approved_bookings = 0;
    $rejected_bookings = 0;
    $cancelled_bookings = 0;

    if ($is_super_admin) {
        $result = $mysqli->query('SELECT COUNT(*) AS total FROM users');
        if ($result) {
            $row = $result->fetch_assoc();
            $total_users = (int) ($row['total'] ?? 0);
        }
        $result = $mysqli->query('SELECT COUNT(*) AS total FROM lab_bookings');
        if ($result) {
            $row = $result->fetch_assoc();
            $total_bookings = (int) ($row['total'] ?? 0);
        }
        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM lab_bookings WHERE status = 'Approved'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $approved_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM lab_bookings WHERE status = 'Rejected'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $rejected_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM lab_bookings WHERE status = 'Cancelled'");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $cancelled_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();
    } elseif ($is_lab_supervisor) {
        if ($lab_scope_ids) {
            $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
            $types = str_repeat('i', count($lab_scope_ids));

            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS total
                FROM lab_bookings
                WHERE lab_id IN ($placeholders)
            ");
            $stmt->bind_param($types, ...$lab_scope_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $total_bookings = (int) ($row['total'] ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS total
                FROM lab_bookings
                WHERE status = 'Approved' AND lab_id IN ($placeholders)
            ");
            $stmt->bind_param($types, ...$lab_scope_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $approved_bookings = (int) ($row['total'] ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS total
                FROM lab_bookings
                WHERE status = 'Rejected' AND lab_id IN ($placeholders)
            ");
            $stmt->bind_param($types, ...$lab_scope_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $rejected_bookings = (int) ($row['total'] ?? 0);
            $stmt->close();

            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS total
                FROM lab_bookings
                WHERE status = 'Cancelled' AND lab_id IN ($placeholders)
            ");
            $stmt->bind_param($types, ...$lab_scope_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $cancelled_bookings = (int) ($row['total'] ?? 0);
            $stmt->close();
        }
    } else {
        $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM users WHERE cluster_id = ?');
        $stmt->bind_param('i', $admin_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_users = (int) ($row['total'] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare('
            SELECT COUNT(*) AS total
            FROM lab_bookings lb
            JOIN labs l ON l.lab_id = lb.lab_id
            WHERE l.cluster_id = ?
        ');
        $stmt->bind_param('i', $admin_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare("
            SELECT COUNT(*) AS total
            FROM lab_bookings lb
            JOIN labs l ON l.lab_id = lb.lab_id
            WHERE lb.status = 'Approved' AND l.cluster_id = ?
        ");
        $stmt->bind_param('i', $admin_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $approved_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare("
            SELECT COUNT(*) AS total
            FROM lab_bookings lb
            JOIN labs l ON l.lab_id = lb.lab_id
            WHERE lb.status = 'Rejected' AND l.cluster_id = ?
        ");
        $stmt->bind_param('i', $admin_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $rejected_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();

        $stmt = $mysqli->prepare("
            SELECT COUNT(*) AS total
            FROM lab_bookings lb
            JOIN labs l ON l.lab_id = lb.lab_id
            WHERE lb.status = 'Cancelled' AND l.cluster_id = ?
        ");
        $stmt->bind_param('i', $admin_cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $cancelled_bookings = (int) ($row['total'] ?? 0);
        $stmt->close();
    }

    $admin_stats = [
        'total_users' => $total_users,
        'total_bookings' => $total_bookings,
        'approved_bookings' => $approved_bookings,
        'rejected_bookings' => $rejected_bookings,
        'cancelled_bookings' => $cancelled_bookings
    ];

    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $key = date('Y-m-d', strtotime('-' . $i . ' days'));
        $days[$key] = 0;
    }
    if ($is_super_admin) {
        $stmt = $mysqli->prepare("SELECT booking_date, COUNT(*) AS total FROM lab_bookings WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY booking_date ORDER BY booking_date");
        $stmt->execute();
    } elseif ($is_lab_supervisor) {
        if ($lab_scope_ids) {
            $placeholders = implode(',', array_fill(0, count($lab_scope_ids), '?'));
            $types = str_repeat('i', count($lab_scope_ids));
            $stmt = $mysqli->prepare("
                SELECT booking_date, COUNT(*) AS total
                FROM lab_bookings
                WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                  AND lab_id IN ($placeholders)
                GROUP BY booking_date
                ORDER BY booking_date
            ");
            $stmt->bind_param($types, ...$lab_scope_ids);
            $stmt->execute();
        } else {
            $stmt = null;
        }
    } else {
        $stmt = $mysqli->prepare("
            SELECT lb.booking_date, COUNT(*) AS total
            FROM lab_bookings lb
            JOIN labs l ON l.lab_id = lb.lab_id
            WHERE lb.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
              AND l.cluster_id = ?
            GROUP BY lb.booking_date
            ORDER BY lb.booking_date
        ");
        $stmt->bind_param('i', $admin_cluster_id);
        $stmt->execute();
    }
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $date_key = $row['booking_date'];
            if (isset($days[$date_key])) {
                $days[$date_key] = (int) $row['total'];
            }
        }
        $stmt->close();
    }
    foreach ($days as $date_key => $count) {
        $admin_chart[] = [
            'date' => $date_key,
            'count' => $count
        ];
    }

} else {
    $search = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status'] ?? 'all';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $bookings = [];
    $status_options = ['Approved', 'Cancelled', 'Rejected'];
    if ($status_filter !== 'all' && !in_array($status_filter, $status_options, true)) {
        $status_filter = 'all';
    }
    $search_like = '%' . $search . '%';
    if ($status_filter === 'all') {
        $stmt = $mysqli->prepare('
            SELECT lb.' . $booking_pk . ' AS booking_id, lb.booking_date, lb.time_slot, lb.status, lb.rejection_reason,
                   lb.cancellation_reason, lb.created_at, l.lab_name, c.cluster_name,
                   lr.title, lr.activity_details, lr.start_time, lr.end_time, lr.full_name,
                   lr.email AS reservation_email, lr.phone
            FROM lab_bookings lb
            JOIN labs l ON lb.lab_id = l.lab_id
            JOIN clusters c ON c.cluster_id = l.cluster_id
            LEFT JOIN lab_reservations lr ON lr.booking_id = lb.' . $booking_pk . '
            WHERE lb.user_id = ?
              AND (l.lab_name LIKE ? OR lb.' . $booking_pk . ' LIKE ?)
            ORDER BY lb.created_at DESC
        ');
        $stmt->bind_param('iss', $user_id, $search_like, $search_like);
    } else {
        $stmt = $mysqli->prepare('
            SELECT lb.' . $booking_pk . ' AS booking_id, lb.booking_date, lb.time_slot, lb.status, lb.rejection_reason,
                   lb.cancellation_reason, lb.created_at, l.lab_name, c.cluster_name,
                   lr.title, lr.activity_details, lr.start_time, lr.end_time, lr.full_name,
                   lr.email AS reservation_email, lr.phone
            FROM lab_bookings lb
            JOIN labs l ON lb.lab_id = l.lab_id
            JOIN clusters c ON c.cluster_id = l.cluster_id
            LEFT JOIN lab_reservations lr ON lr.booking_id = lb.' . $booking_pk . '
            WHERE lb.user_id = ?
              AND (l.lab_name LIKE ? OR lb.' . $booking_pk . ' LIKE ?)
              AND lb.status = ?
            ORDER BY lb.created_at DESC
        ');
        $stmt->bind_param('isss', $user_id, $search_like, $search_like, $status_filter);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $calendar_payload = build_booking_calendar_payload($row);
        $row['google_calendar_url'] = ($row['status'] ?? '') === 'Approved' && $calendar_payload
            ? build_google_calendar_url($calendar_payload)
            : '';
        $bookings[] = $row;
    }
    $stmt->close();

    $booking_pagination = paginate_items($bookings, $page, 15);
    $bookings = $booking_pagination['items'];
    $pagination_params = [
        'search' => $search,
        'status' => $status_filter
    ];
}
$layout_path = __DIR__ . '/templates/layouts/public.php';
if ($is_admin) {
    $layout_path = __DIR__ . '/templates/layouts/admin.php';
} elseif ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
} elseif ($user_type === 'uthm_staff') {
    $layout_path = __DIR__ . '/templates/layouts/uthm_staff.php';
} elseif ($user_type === 'uthm_student') {
    $layout_path = __DIR__ . '/templates/layouts/uthm_student.php';
}
$layout = require $layout_path;
$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaBS PPMKCP Dashboard</title>
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
                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($is_management): ?>
                    <div class="content-header">
                        <div>
                            <h1>Admin Dashboard</h1>
                            <p>Monitor lab booking activity.</p>
                        </div>
                        <div class="breadcrumb">Home / Admin Dashboard</div>
                    </div>

                    <div class="stats-grid">
                        <?php if ($is_admin): ?>
                            <div class="card stat-card">
                                <p class="stat-label">Total Users</p>
                                <h2 class="stat-value"><?php echo (int) ($admin_stats['total_users'] ?? 0); ?></h2>
                                <span class="stat-meta">All registered accounts</span>
                            </div>
                        <?php endif; ?>
                        <div class="card stat-card">
                            <p class="stat-label">Total Bookings</p>
                            <h2 class="stat-value"><?php echo (int) ($admin_stats['total_bookings'] ?? 0); ?></h2>
                            <span class="stat-meta">All booking records</span>
                        </div>
                        <div class="card stat-card">
                            <p class="stat-label">Booked Bookings</p>
                            <h2 class="stat-value"><?php echo (int) ($admin_stats['approved_bookings'] ?? 0); ?></h2>
                            <span class="stat-meta">Booked to date</span>
                        </div>
                        <div class="card stat-card">
                            <p class="stat-label">Rejected Bookings</p>
                            <h2 class="stat-value"><?php echo (int) ($admin_stats['rejected_bookings'] ?? 0); ?></h2>
                            <span class="stat-meta">Rejected by admin</span>
                        </div>
                        <div class="card stat-card">
                            <p class="stat-label">Cancelled Bookings</p>
                            <h2 class="stat-value"><?php echo (int) ($admin_stats['cancelled_bookings'] ?? 0); ?></h2>
                            <span class="stat-meta">Cancelled by users</span>
                        </div>
                    </div>

                    <div class="card chart-card">
                        <div class="chart-header">
                            <div>
                                <p class="badge">Booking Trends</p>
                                <h3>Bookings in the last 7 days</h3>
                            </div>
                            <span class="muted-text">Daily booking count</span>
                        </div>
                        <?php
                        $max_count = 0;
                        foreach ($admin_chart as $entry) {
                            if ($entry['count'] > $max_count) {
                                $max_count = $entry['count'];
                            }
                        }
                        $max_count = $max_count > 0 ? $max_count : 1;
                        ?>
                        <div class="chart-grid">
                            <?php foreach ($admin_chart as $entry): ?>
                                <?php
                                $height = (int) round(($entry['count'] / $max_count) * 100);
                                $label = date('D', strtotime($entry['date']));
                                ?>
                                <div class="chart-bar">
                                    <div class="chart-bar-fill" style="height: <?php echo $height; ?>%"></div>
                                    <span class="chart-bar-value"><?php echo (int) $entry['count']; ?></span>
                                    <span class="chart-bar-label"><?php echo htmlspecialchars($label); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="content-header">
                        <div>
                            <h1>My Booking</h1>
                        </div>
                        <div class="breadcrumb">Home / My Booking</div>
                    </div>

                    <div class="card">
                        <form class="filters" method="GET" action="dashboard.php">
                            <input type="text" name="search" placeholder="Search booking ID or lab" value="<?php echo htmlspecialchars($search); ?>">
                            <select name="status">
                                <option value="all"<?php echo $status_filter === 'all' ? ' selected' : ''; ?>>All status</option>
                                <option value="Approved"<?php echo $status_filter === 'Approved' ? ' selected' : ''; ?>>Booked</option>
                                <option value="Cancelled"<?php echo $status_filter === 'Cancelled' ? ' selected' : ''; ?>>Cancelled</option>
                                <option value="Rejected"<?php echo $status_filter === 'Rejected' ? ' selected' : ''; ?>>Rejected</option>
                            </select>
                            <input type="hidden" name="page" value="1">
                            <button class="btn primary" type="submit">Filter</button>
                            <a class="btn ghost" href="booking.php">Add Booking Now</a>
                        </form>
                        <p class="table-meta">Showing <?php echo count($bookings); ?> of <?php echo (int) $booking_pagination['total_items']; ?> booking(s)</p>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Booking ID</th>
                                        <th>Lab</th>
                                        <th>Lab Date/Time</th>
                                        <th>Booked At</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $index => $booking): ?>
                                        <?php
                                        $note = '-';
                                        if ($booking['status'] === 'Rejected' && $booking['rejection_reason']) {
                                            $note = $booking['rejection_reason'];
                                        } elseif ($booking['status'] === 'Cancelled' && $booking['cancellation_reason']) {
                                            $note = $booking['cancellation_reason'];
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo (int) ((($booking_pagination['current_page'] - 1) * $booking_pagination['per_page']) + $index + 1); ?></td>
                                            <td><?php echo (int) $booking['booking_id']; ?></td>
                                            <td><?php echo htmlspecialchars($booking['lab_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($booking['booking_date']); ?>
                                                <span class="muted-text">/ <?php echo htmlspecialchars($booking['time_slot']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo !empty($booking['created_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($booking['created_at']))) : '-'; ?>
                                            </td>
                                            <td><span class="status <?php echo htmlspecialchars($booking['status']); ?>"><?php echo htmlspecialchars(get_booking_status_label($booking['status'] ?? '')); ?></span></td>
                                            <td><?php echo htmlspecialchars($note); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a class="btn ghost small" href="booking-receipt.php?booking_id=<?php echo (int) $booking['booking_id']; ?>" target="_blank" rel="noopener">View/Print</a>
                                                    <?php if (!empty($booking['google_calendar_url'])): ?>
                                                        <a class="btn ghost small" href="<?php echo htmlspecialchars($booking['google_calendar_url']); ?>" target="_blank" rel="noopener">Add to Google Calendar</a>
                                                    <?php endif; ?>
                                                    <?php if ($booking['status'] === 'Approved'): ?>
                                                        <button
                                                            class="btn ghost small cancel-booking"
                                                            type="button"
                                                            data-booking-id="<?php echo (int) $booking['booking_id']; ?>"
                                                            data-booking-label="<?php echo htmlspecialchars($booking['lab_name']); ?>"
                                                        >
                                                            Cancel Booking
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$bookings): ?>
                                        <tr>
                                            <td colspan="8">No bookings found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($booking_pagination['total_pages'] > 1): ?>
                            <div class="pagination">
                                <?php
                                $prev_page = max(1, $booking_pagination['current_page'] - 1);
                                $next_page = min($booking_pagination['total_pages'], $booking_pagination['current_page'] + 1);
                                $prev_params = array_merge($pagination_params, ['page' => $prev_page]);
                                $next_params = array_merge($pagination_params, ['page' => $next_page]);
                                ?>
                                <a class="btn ghost small<?php echo $booking_pagination['current_page'] <= 1 ? ' is-disabled' : ''; ?>" href="dashboard.php?<?php echo htmlspecialchars(http_build_query($prev_params)); ?>">Previous</a>
                                <div class="pagination-status">Page <?php echo (int) $booking_pagination['current_page']; ?> of <?php echo (int) $booking_pagination['total_pages']; ?></div>
                                <a class="btn ghost small<?php echo $booking_pagination['current_page'] >= $booking_pagination['total_pages'] ? ' is-disabled' : ''; ?>" href="dashboard.php?<?php echo htmlspecialchars(http_build_query($next_params)); ?>">Next</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <footer class="footer">Â© Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <?php if (!$is_management): ?>
        <div class="modal" id="cancel-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Cancel Booking</h2>
                    <button class="icon-button" data-close="cancel-modal">x</button>
                </div>
                <form class="modal-body" method="POST" action="dashboard.php" id="cancel-form">
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" id="cancel-booking-id">
                    <label>Booking</label>
                    <input type="text" id="cancel-booking-label" readonly>
                    <label>Reason for cancellation</label>
                    <textarea name="cancellation_reason" id="cancel-reason" rows="4" placeholder="Enter reason" required></textarea>
                    <div class="modal-footer">
                        <button type="button" class="btn ghost" data-close="cancel-modal">Back</button>
                        <button type="submit" class="btn danger">Confirm Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
        (function () {
            var cancelModal = document.getElementById('cancel-modal');
            if (!cancelModal) {
                return;
            }
            var cancelId = document.getElementById('cancel-booking-id');
            var cancelLabel = document.getElementById('cancel-booking-label');
            document.querySelectorAll('.cancel-booking').forEach(function (button) {
                button.addEventListener('click', function () {
                    cancelId.value = button.getAttribute('data-booking-id') || '';
                    cancelLabel.value = button.getAttribute('data-booking-label') || '';
                    cancelModal.classList.add('active');
                });
            });
            document.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var target = button.getAttribute('data-close');
                    var modal = document.getElementById(target);
                    if (modal) {
                        modal.classList.remove('active');
                    }
                });
            });
        })();
    </script>
    <script src="assets/app.js?v=<?php echo (int) (@filemtime(__DIR__ . '/assets/app.js') ?: time()); ?>"></script>
</body>
</html>
