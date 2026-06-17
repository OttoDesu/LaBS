<?php
require_once __DIR__ . '/init.php';
require_login();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$lab_id = isset($_GET['lab_id']) ? (int) $_GET['lab_id'] : 0;
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$user_type = $_SESSION['user_type'] ?? 'public';
$is_lab_supervisor = is_lab_supervisor($user_type);
$can_view_calendar_history = is_management_user($user_type);
$booking_mode = $_GET['booking_mode'] ?? $_POST['booking_mode'] ?? 'slot';
$booking_mode = $is_lab_supervisor && $booking_mode === 'group' ? 'group' : 'slot';

if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$lab = null;
$cluster = null;
$lab_assets = [];

if ($lab_id > 0) {
    $stmt = $mysqli->prepare('
        SELECT l.lab_id, l.lab_name, l.lab_description, l.lab_capacity,
               l.maintenance_status, l.maintenance_start_date, l.maintenance_end_date,
               s.supervisor_name, s.supervisor_email, s.supervisor_room_no,
               c.cluster_id AS cluster_id, c.cluster_name AS cluster_name
        FROM labs l
        JOIN clusters c ON c.cluster_id = l.cluster_id
        LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
        WHERE l.lab_id = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lab = $result->fetch_assoc();
    $stmt->close();
}

if ($lab_id > 0) {
    $stmt = $mysqli->prepare('
        SELECT asset_name, asset_status, asset_count
        FROM assets
        WHERE lab_id = ? AND asset_status <> "Disposed"
        ORDER BY asset_name ASC
    ');
    $stmt->bind_param('i', $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lab_assets[] = $row;
    }
    $stmt->close();
}

if (!$lab) {
    header('Location: booking.php');
    exit;
}

$default_group_reference = new DateTime('today');
$default_group_reference->modify('+3 days');
$guard = 0;
while (
    (($lab['maintenance_status'] ?? 'available') === 'maintenance')
    && is_lab_under_maintenance($lab, $default_group_reference->format('Y-m-d'))
    && $guard < 366
) {
    $default_group_reference->modify('+1 day');
    $guard++;
}
$default_group_reference_date = $default_group_reference->format('Y-m-d');

if ($is_lab_supervisor && $booking_mode === 'group' && $_SERVER['REQUEST_METHOD'] !== 'POST') {

    $query = http_build_query([
        'lab_id' => $lab_id,
        'booking_date' => $default_group_reference_date,
        'booking_mode' => 'group'
    ]);
    header('Location: reservation-form.php?' . $query);
    exit;
}

$time_slots = [
    '08:00-09:00',
    '09:00-10:00',
    '10:00-11:00',
    '11:00-12:00',
    '12:00-13:00',
    '13:00-14:00',
    '14:00-15:00',
    '15:00-16:00',
    '16:00-17:00',
    '17:00-18:00',
    '18:00-19:00',
    '19:00-20:00',
    '20:00-21:00',
    '21:00-22:00',
    '22:00-23:00'
];

$maintenance_label = get_lab_maintenance_period_label($lab['maintenance_start_date'] ?? null, $lab['maintenance_end_date'] ?? null);
$maintenance_days = get_lab_maintenance_day_count($lab['maintenance_start_date'] ?? null, $lab['maintenance_end_date'] ?? null);
$lab_has_maintenance_window = (($lab['maintenance_status'] ?? 'available') === 'maintenance');

$errors = [];
$booking_pk = get_booking_pk_column($mysqli);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_date = $_POST['booking_date'] ?? '';
    $time_slot = $_POST['time_slot'] ?? '';
    $booking_mode = $_POST['booking_mode'] ?? $booking_mode;
    $booking_mode = $is_lab_supervisor && $booking_mode === 'group' ? 'group' : 'slot';
    if (is_lab_under_maintenance($lab, $booking_date)) {
        $errors[] = 'This lab is under maintenance on the selected date.';
    }
    if (!$errors) {
        $query = http_build_query([
            'lab_id' => $lab_id,
            'booking_date' => $booking_date,
            'time_slot' => $time_slot,
            'booking_mode' => $booking_mode
        ]);
        header('Location: reservation-form.php?' . $query);
        exit;
    }
}

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));
$calendar_query_start_date = $start_date;
$booked_by_date = [];
$booked_details_by_date = [];

$stmt = $mysqli->prepare("
    SELECT
        b.{$booking_pk} AS booking_id,
        b.user_id,
        b.booking_date,
        b.time_slot,
        r.reservation_id,
        r.created_at AS reservation_created_at,
        COALESCE(NULLIF(r.title, ''), NULLIF(r.class_course_code, ''), NULLIF(r.class_subject_name, ''), 'Booked')
            AS booking_label,
        CASE
            WHEN r.group_booking_type IN ('lecture', 'lab') THEN r.group_booking_type
            WHEN r.booking_purpose = 'class' THEN 'lecture'
            ELSE 'lab'
        END AS booking_type,
        r.booking_mode,
        r.group_booking_key,
        r.activity_details
    FROM lab_bookings b
    LEFT JOIN lab_reservations r ON r.booking_id = b.{$booking_pk}
    WHERE b.lab_id = ?
      AND b.booking_date BETWEEN ? AND ?
      AND b.status = 'Approved'
    ORDER BY b.booking_date ASC, b.time_slot ASC
");
$stmt->bind_param('iss', $lab_id, $calendar_query_start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $date_key = $row['booking_date'];
    if (!isset($booked_by_date[$date_key])) {
        $booked_by_date[$date_key] = [];
    }
    $booked_by_date[$date_key][] = $row['time_slot'];
    if (!isset($booked_details_by_date[$date_key])) {
        $booked_details_by_date[$date_key] = [];
    }
    $booked_details_by_date[$date_key][] = [
        'booking_id' => (int) ($row['booking_id'] ?? 0),
        'reservation_id' => (int) ($row['reservation_id'] ?? 0),
        'time_slot' => $row['time_slot'],
        'label' => $row['booking_label'],
        'booking_type' => $row['booking_type'],
        'booking_mode' => $row['booking_mode'] ?? 'slot',
        'group_booking_key' => $row['group_booking_key'] ?? null,
        'modal_group_key' => ($row['group_booking_key'] ?? '') !== ''
            ? (string) $row['group_booking_key']
            : implode('|', [
                'slot',
                (string) ($row['user_id'] ?? 0),
                (string) ($row['booking_date'] ?? ''),
                (string) ($row['reservation_created_at'] ?? ''),
                (string) ($row['booking_label'] ?? ''),
                (string) ($row['activity_details'] ?? '')
            ]),
        'can_edit_group' => $is_lab_supervisor
            && (($row['booking_mode'] ?? 'slot') === 'group')
            && ((int) ($row['user_id'] ?? 0) === $user_id)
    ];
}
$stmt->close();

$hold_stmt = $mysqli->prepare("
    SELECT
        h.user_id,
        h.booking_date,
        h.time_slot,
        h.expires_at
    FROM booking_holds h
    WHERE h.lab_id = ?
      AND h.booking_date BETWEEN ? AND ?
      AND h.expires_at > NOW()
    ORDER BY h.booking_date ASC, h.time_slot ASC
");
$hold_stmt->bind_param('iss', $lab_id, $calendar_query_start_date, $end_date);
$hold_stmt->execute();
$hold_result = $hold_stmt->get_result();
while ($row = $hold_result->fetch_assoc()) {
    $date_key = $row['booking_date'];
    if (!isset($booked_by_date[$date_key])) {
        $booked_by_date[$date_key] = [];
    }
    if (!in_array($row['time_slot'], $booked_by_date[$date_key], true)) {
        $booked_by_date[$date_key][] = $row['time_slot'];
    }
    if (!isset($booked_details_by_date[$date_key])) {
        $booked_details_by_date[$date_key] = [];
    }
    $booked_details_by_date[$date_key][] = [
        'booking_id' => 0,
        'reservation_id' => 0,
        'time_slot' => $row['time_slot'],
        'label' => 'Temporarily reserved (15-minute hold)',
        'booking_type' => 'hold',
        'booking_mode' => 'hold',
        'group_booking_key' => null,
        'modal_group_key' => implode('|', [
            'hold',
            (string) ($row['user_id'] ?? 0),
            (string) ($row['booking_date'] ?? ''),
            (string) ($row['expires_at'] ?? '')
        ]),
        'can_edit_group' => false
    ];
}
$hold_stmt->close();

$flash_info = get_flash('info');
$active_hold_resumes = get_active_booking_hold_resumes($mysqli, $user_id, $lab_id);
$active_hold_resume = $active_hold_resumes[0] ?? null;
$active_hold_resume_expires_at_unix = 0;
if ($active_hold_resume && !empty($active_hold_resume['expires_at'])) {
    $active_hold_resume_expires_at_unix = (new DateTime(
        (string) $active_hold_resume['expires_at'],
        new DateTimeZone('Asia/Singapore')
    ))->getTimestamp();
}
create_active_booking_hold_resume_notifications($mysqli, $user_id);
$user_type = $_SESSION['user_type'] ?? 'public';
$is_admin = is_admin_user($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$layout_path = __DIR__ . '/templates/layouts/public.php';
if ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
} elseif ($is_admin) {
    $layout_path = __DIR__ . '/templates/layouts/admin.php';
} elseif ($user_type === 'uthm_staff') {
    $layout_path = __DIR__ . '/templates/layouts/uthm_staff.php';
} elseif ($user_type === 'uthm_student') {
    $layout_path = __DIR__ . '/templates/layouts/uthm_student.php';
}
$layout = require $layout_path;
$active = 'booking';
$app_css_version = @filemtime(__DIR__ . '/assets/app.css') ?: time();
$app_js_version = @filemtime(__DIR__ . '/assets/app.js') ?: time();
$booking_js_version = @filemtime(__DIR__ . '/assets/booking.js') ?: time();

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Availability Calendar</title>
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
                        <h1><?php echo htmlspecialchars($lab['lab_name']); ?></h1>
                        <p><?php echo $booking_mode === 'group' ? 'Pick a reference date to continue with recurring class booking.' : 'Pick a date and time slot to reserve this lab.'; ?></p>
                    </div>
                    <div class="breadcrumb">Home / <?php echo htmlspecialchars($lab['lab_name']); ?></div>
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
                            <p class="badge"><?php echo htmlspecialchars($lab['cluster_name']); ?></p>
                            <h2><?php echo htmlspecialchars($lab['lab_name']); ?></h2>
                            <p><?php echo htmlspecialchars($lab['lab_description']); ?></p>
                            <?php if ($lab_has_maintenance_window): ?>
                                <p class="muted-text">Under maintenance: <?php echo htmlspecialchars($maintenance_label); ?><?php if ($maintenance_days !== null): ?> (<?php echo (int) $maintenance_days; ?> day<?php echo $maintenance_days === 1 ? '' : 's'; ?>)<?php endif; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="banner-links">
                            <span class="badge">Capacity: <?php echo htmlspecialchars((string) $lab['lab_capacity']); ?> seats</span>
                            <a class="btn ghost" href="labs.php?cluster_id=<?php echo (int) $lab['cluster_id']; ?>">Choose another lab</a>
                        </div>
                    </div>
                    <?php if ($is_lab_supervisor): ?>
                        <div class="asset-management-tabs booking-mode-tabs" id="booking-mode-switch" role="tablist" aria-label="Booking mode">
                            <button
                                class="asset-management-tab booking-mode-tab <?php echo $booking_mode !== 'group' ? 'is-active' : ''; ?>"
                                type="button"
                                data-mode="slot"
                                role="tab"
                                aria-selected="<?php echo $booking_mode !== 'group' ? 'true' : 'false'; ?>"
                            >
                                Book Slot Lab
                            </button>
                            <button
                                class="asset-management-tab booking-mode-tab <?php echo $booking_mode === 'group' ? 'is-active' : ''; ?>"
                                type="button"
                                data-mode="group"
                                role="tab"
                                aria-selected="<?php echo $booking_mode === 'group' ? 'true' : 'false'; ?>"
                            >
                                Group Booking
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($active_hold_resume): ?>
                        <a
                            class="alert alert-info hold-resume-link"
                            href="<?php echo htmlspecialchars($active_hold_resume['link_url']); ?>"
                            data-hold-expires-at-unix="<?php echo (int) $active_hold_resume_expires_at_unix; ?>"
                        >
                            You have an <span class="hold-resume-emphasis">unfinished booking</span> form for this lab on <?php echo htmlspecialchars(format_display_date((string) ($active_hold_resume['booking_date'] ?? '')) ?: 'selected date'); ?>
                            <?php if (!empty($active_hold_resume['time_slots'])): ?>
                                (<?php echo htmlspecialchars(implode(', ', $active_hold_resume['time_slots'])); ?>)
                            <?php endif; ?>
                            . Time left: <span class="hold-resume-countdown">checking...</span>. <span class="hold-resume-emphasis">Click here</span> to continue before the hold timer ends.
                        </a>
                    <?php endif; ?>
                </div>

                <div class="availability-grid">
                    <div class="availability-left">
                        <?php if ($lab_has_maintenance_window): ?>
                            <div class="card">
                                <h3>Maintenance Notice</h3>
                                <p class="muted-text">This lab is only unavailable within the maintenance date range below.</p>
                                <div class="info-row">
                                    <span>Period</span>
                                    <strong><?php echo htmlspecialchars($maintenance_label); ?><?php if ($maintenance_days !== null): ?> (<?php echo (int) $maintenance_days; ?> day<?php echo $maintenance_days === 1 ? '' : 's'; ?>)<?php endif; ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <h3>Lab Supervisor</h3>
                            <div class="info-row">
                                <span>Name </span>
                                <strong><?php echo htmlspecialchars($lab['supervisor_name'] ?: 'Unassigned'); ?></strong>
                            </div>
                            <div class="info-row">
                                <span>Cluster</span>
                                <strong><?php echo htmlspecialchars($lab['cluster_name']); ?></strong>
                            </div>
                            <div class="info-row">
                                <span>Email</span>
                                <?php if (!empty($lab['supervisor_email'])): ?>
                                    <a class="link" href="mailto:<?php echo htmlspecialchars($lab['supervisor_email']); ?>"><?php echo htmlspecialchars($lab['supervisor_email']); ?></a>
                                <?php else: ?>
                                    <span class="muted-text">No email provided</span>
                                <?php endif; ?>
                            </div>
                            <div class="info-row">
                                <span>Supervisor Room</span>
                                <strong><?php echo htmlspecialchars($lab['supervisor_room_no'] ?: 'Not provided'); ?></strong>
                            </div>
                        </div>

                        <div class="card">
                            <h3>Lab Assets</h3>
                            <div class="asset-list">
                                <?php if (empty($lab_assets)): ?>
                                    <span class="muted-text">No assets listed.</span>
                                <?php else: ?>
                                    <?php foreach ($lab_assets as $asset): ?>
                                        <div class="asset-item">
                                            <span><?php echo htmlspecialchars($asset['asset_name']); ?></span>
                                            <span class="asset-status status-<?php echo htmlspecialchars(strtolower($asset['asset_status'])); ?>">
                                                <?php echo htmlspecialchars($asset['asset_status']); ?>
                                            </span>
                                            <span>Qty: <?php echo htmlspecialchars((string) $asset['asset_count']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <div class="card availability-card">
                        <div class="calendar-header">
                            <div>
                                <p class="badge">Availability Calendar</p>
                                <h3>Pick a date to view slots</h3>
                                <?php if ($lab_has_maintenance_window): ?>
                                    <p class="muted-text">Maintenance period: <?php echo htmlspecialchars($maintenance_label); ?><?php if ($maintenance_days !== null): ?> (<?php echo (int) $maintenance_days; ?> day<?php echo $maintenance_days === 1 ? '' : 's'; ?>)<?php endif; ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="calendar-nav">
                                <a class="icon-button" href="availability.php?lab_id=<?php echo (int) $lab_id; ?>&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">‹</a>
                                <span id="calendar-title"></span>
                                <a class="icon-button" href="availability.php?lab_id=<?php echo (int) $lab_id; ?>&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">›</a>
                            </div>
                        </div>
                        <div class="calendar-grid" id="calendar-grid"></div>

                        <div class="slot-section">
                            <div class="slot-header">
                                <h4 id="slot-title"><?php echo $booking_mode === 'group' ? 'Select a reference date to continue' : 'Select a date to view slots'; ?></h4>
                                <div class="slot-legend">
                                    <span class="dot available"></span> Available
                                    <span class="dot booked"></span> Booked
                                    <span class="dot selected"></span> Selected
                                </div>
                            </div>
                            <form method="POST" action="reservation-form.php" class="slot-grid" id="booking-form">
                                <input type="hidden" name="lab_id" value="<?php echo (int) $lab_id; ?>">
                                <input type="hidden" name="booking_date" id="booking-date">
                                <input type="hidden" name="time_slots" id="booking-slots">
                                <input type="hidden" name="booking_mode" value="<?php echo htmlspecialchars($booking_mode); ?>">
                                <div id="slot-grid"></div>
                                <button class="btn primary" id="booking-submit" type="submit" disabled><?php echo $booking_mode === 'group' ? 'Continue Group Booking' : 'Make Reservation'; ?></button>
                                <p class="muted-text" id="booking-hint">
                                    <?php if ($lab_has_maintenance_window): ?>
                                        Dates inside the maintenance period are locked automatically. Maintenance period: <?php echo htmlspecialchars($maintenance_label); ?><?php if ($maintenance_days !== null): ?> (<?php echo (int) $maintenance_days; ?> day<?php echo $maintenance_days === 1 ? '' : 's'; ?>)<?php endif; ?>.
                                    <?php else: ?>
                                        <?php if ($booking_mode === 'group'): ?>
                                            Group booking selected. Pick a reference date first. Number of weeks, days, labs, and times are set in the next form.
                                        <?php else: ?>
                                            Please pick a date on or after <?php echo htmlspecialchars((new DateTime('today'))->modify('+3 days')->format('Y-m-d')); ?>.
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>

                <footer class="footer">&copy; Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <div class="modal" id="booked-slots-modal" aria-hidden="true">
        <div class="modal-content modal-content-wide booked-slots-modal-content">
            <div class="modal-header">
                <div>
                    <h3 id="booked-slots-modal-title">Booked Slots</h3>
                    <p class="muted-text" id="booked-slots-modal-subtitle">Booking details for the selected date.</p>
                </div>
                <button class="icon-button" type="button" id="booked-slots-modal-close" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 5L15 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M15 5L5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div id="booked-slots-modal-list" class="booked-slot-modal-list"></div>
            </div>
            <div class="modal-footer">
                <button class="btn primary" type="button" id="booked-slots-modal-book-now">Book Now</button>
            </div>
        </div>
    </div>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
        window.LABS_AVAILABILITY = <?php echo json_encode([
            'month' => $month,
            'year' => $year,
            'bookedByDate' => $booked_by_date,
            'bookedDetailsByDate' => $booked_details_by_date,
            'timeSlots' => $time_slots,
            'maintenanceStatus' => $lab['maintenance_status'] ?? 'available',
            'maintenanceStartDate' => $lab['maintenance_start_date'] ?? null,
            'maintenanceEndDate' => $lab['maintenance_end_date'] ?? null,
            'maintenanceLabel' => $maintenance_label,
            'bookingMode' => $booking_mode,
            'labName' => $lab['lab_name'],
            'labId' => (int) $lab_id,
            'canViewCalendarHistory' => $can_view_calendar_history
        ]); ?>;
    </script>
    <script src="assets/app.js?v=<?php echo (int) $app_js_version; ?>"></script>
    <script src="assets/booking.js?v=<?php echo (int) $booking_js_version; ?>"></script>
    <script>
        (function () {
            var holdResumeLink = document.querySelector('.hold-resume-link');
            if (!holdResumeLink) {
                return;
            }
            var countdown = holdResumeLink.querySelector('.hold-resume-countdown');
            var expiresAtUnix = Number(holdResumeLink.getAttribute('data-hold-expires-at-unix') || '0');
            function updateHoldResumeCountdown() {
                if (!countdown || !expiresAtUnix) {
                    return;
                }
                var remainingMs = (expiresAtUnix * 1000) - Date.now();
                if (remainingMs <= 0) {
                    countdown.textContent = '00:00';
                    holdResumeLink.classList.add('is-expired');
                    return;
                }
                var totalSeconds = Math.floor(remainingMs / 1000);
                var minutes = Math.floor(totalSeconds / 60);
                var seconds = totalSeconds % 60;
                countdown.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            }
            updateHoldResumeCountdown();
            window.setInterval(updateHoldResumeCountdown, 1000);
        })();

        (function () {
            var switcher = document.getElementById('booking-mode-switch');
            if (!switcher) {
                return;
            }
            var links = document.querySelectorAll('a[href*="availability.php"]');
            var buttons = switcher.querySelectorAll('[data-mode]');
            function buildGroupBookingUrl() {
                var url = new URL('reservation-form.php', window.location.href);
                url.searchParams.set('lab_id', <?php echo (int) $lab_id; ?>);
                url.searchParams.set('booking_date', <?php echo json_encode($default_group_reference_date); ?>);
                url.searchParams.set('booking_mode', 'group');
                return url.toString();
            }
            function currentUrlWithMode(mode) {
                var url = new URL(window.location.href);
                url.searchParams.set('booking_mode', mode);
                return url.toString();
            }
            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var mode = button.getAttribute('data-mode') || 'slot';
                    window.location.href = mode === 'group' ? buildGroupBookingUrl() : currentUrlWithMode(mode);
                });
            });
            links.forEach(function (link) {
                try {
                    var url = new URL(link.href, window.location.origin);
                    url.searchParams.set('booking_mode', <?php echo json_encode($booking_mode); ?>);
                    link.href = url.toString();
                } catch (error) {
                    // ignore malformed links
                }
            });
        })();
    </script>
</body>
</html>
