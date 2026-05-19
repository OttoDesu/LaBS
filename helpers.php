<?php
function is_valid_public_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function is_uthm_staff_email($email) {
    return preg_match('/^[a-z0-9._%+-]+@uthm\.edu\.my$/i', $email) === 1;
}

function is_uthm_student_email($email) {
    return preg_match('/^[a-z0-9._%+-]+@student\.uthm\.edu\.my$/i', $email) === 1;
}

function set_flash($key, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$key] = $message;
}

function get_flash($key) {
    if (!isset($_SESSION['flash'][$key])) {
        return '';
    }
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        set_flash('error', 'Please sign in to continue.');
        header('Location: index.php');
        exit;
    }
}

function is_super_admin($user_type) {
    return in_array($user_type, ['super_admin', 'admin'], true);
}

function is_cluster_admin($user_type) {
    return $user_type === 'cluster_admin';
}

function is_lab_supervisor($user_type) {
    return $user_type === 'lab_supervisor';
}

function is_admin_user($user_type) {
    return is_super_admin($user_type) || is_cluster_admin($user_type);
}

function is_management_user($user_type) {
    return is_admin_user($user_type) || is_lab_supervisor($user_type);
}

function get_admin_cluster_id() {
    if (!isset($_SESSION['cluster_id'])) {
        return null;
    }
    $cluster_id = (int) $_SESSION['cluster_id'];
    return $cluster_id > 0 ? $cluster_id : null;
}

function get_lab_supervisor_lab_ids($mysqli, $user_id) {
    static $cache = [];
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !$mysqli) {
        return [];
    }
    if (array_key_exists($user_id, $cache)) {
        return $cache[$user_id];
    }
    $lab_ids = [];
    $stmt = $mysqli->prepare('SELECT lab_id FROM lab_supervisor_labs WHERE user_id = ? ORDER BY lab_id ASC');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $lab_ids[] = (int) $row['lab_id'];
        }
        $stmt->close();
    }
    $cache[$user_id] = $lab_ids;
    return $lab_ids;
}

function require_admin() {
    $user_type = $_SESSION['user_type'] ?? 'public';
    if (!is_admin_user($user_type)) {
        set_flash('error', 'You do not have permission to access that page.');
        header('Location: dashboard.php');
        exit;
    }
    if (is_cluster_admin($user_type) && !get_admin_cluster_id()) {
        set_flash('error', 'Your admin account is missing a cluster assignment.');
        header('Location: dashboard.php');
        exit;
    }
}

function require_management() {
    $user_type = $_SESSION['user_type'] ?? 'public';
    if (!is_management_user($user_type)) {
        set_flash('error', 'You do not have permission to access that page.');
        header('Location: dashboard.php');
        exit;
    }
    if (is_cluster_admin($user_type) && !get_admin_cluster_id()) {
        set_flash('error', 'Your admin account is missing a cluster assignment.');
        header('Location: dashboard.php');
        exit;
    }
}

function require_super_admin() {
    $user_type = $_SESSION['user_type'] ?? 'public';
    if (!is_super_admin($user_type)) {
        set_flash('error', 'You do not have permission to access that page.');
        header('Location: dashboard.php');
        exit;
    }
}

function get_booking_pk_column($mysqli) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = 'booking_id';
    if (!$mysqli) {
        return $cached;
    }
    $stmt = $mysqli->prepare("
        SELECT column_name
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'lab_bookings'
          AND column_name IN ('booking_id', 'id')
        ORDER BY FIELD(column_name, 'booking_id', 'id')
        LIMIT 1
    ");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $cached = $row['column_name'] ?: $cached;
        }
        $stmt->close();
    }
    return $cached;
}

function get_booking_status_label($status) {
    return $status === 'Approved' ? 'Booked' : $status;
}

function ensure_lab_maintenance_columns($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $required_columns = [
        'maintenance_status' => "ALTER TABLE labs ADD COLUMN maintenance_status ENUM('available','maintenance') NOT NULL DEFAULT 'available' AFTER lab_capacity",
        'maintenance_start_date' => "ALTER TABLE labs ADD COLUMN maintenance_start_date DATE DEFAULT NULL AFTER maintenance_status",
        'maintenance_end_date' => "ALTER TABLE labs ADD COLUMN maintenance_end_date DATE DEFAULT NULL AFTER maintenance_start_date"
    ];

    $existing_columns = [];
    $stmt = $mysqli->prepare("
        SELECT column_name
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'labs'
          AND column_name IN ('maintenance_status', 'maintenance_start_date', 'maintenance_end_date')
    ");

    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existing_columns[$row['column_name']] = true;
        }
        $stmt->close();
    }

    foreach ($required_columns as $column_name => $sql) {
        if (!isset($existing_columns[$column_name])) {
            $mysqli->query($sql);
        }
    }

    $ensured = true;
}

function ensure_class_booking_columns($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $required_columns = [
        'booking_purpose' => "ALTER TABLE lab_reservations ADD COLUMN booking_purpose ENUM('lab','class') NOT NULL DEFAULT 'lab' AFTER phone",
        'class_course_code' => "ALTER TABLE lab_reservations ADD COLUMN class_course_code VARCHAR(8) DEFAULT NULL AFTER booking_purpose",
        'class_subject_name' => "ALTER TABLE lab_reservations ADD COLUMN class_subject_name VARCHAR(255) DEFAULT NULL AFTER class_course_code",
        'class_section' => "ALTER TABLE lab_reservations ADD COLUMN class_section VARCHAR(50) DEFAULT NULL AFTER class_subject_name"
    ];

    $existing_columns = [];
    $stmt = $mysqli->prepare("
        SELECT column_name
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'lab_reservations'
          AND column_name IN ('booking_purpose', 'class_course_code', 'class_subject_name', 'class_section')
    ");

    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existing_columns[$row['column_name']] = true;
        }
        $stmt->close();
    }

    foreach ($required_columns as $column_name => $sql) {
        if (!isset($existing_columns[$column_name])) {
            $mysqli->query($sql);
        }
    }

    $ensured = true;
}

function normalize_lab_maintenance_input(array $source, array &$errors) {
    $status = ($source['maintenance_status'] ?? 'available') === 'maintenance' ? 'maintenance' : 'available';
    $start_date = trim((string) ($source['maintenance_start_date'] ?? ''));
    $end_date = trim((string) ($source['maintenance_end_date'] ?? ''));
    $today = date('Y-m-d');

    if ($status !== 'maintenance') {
        return [
            'status' => 'available',
            'start_date' => null,
            'end_date' => null
        ];
    }

    if ($start_date === '' || $end_date === '') {
        $errors[] = 'Maintenance start and end dates are required when a lab is under maintenance.';
    } else {
        $is_valid_start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) === 1;
        $is_valid_end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) === 1;

          if (!$is_valid_start || !$is_valid_end) {
              $errors[] = 'Maintenance dates must use the YYYY-MM-DD format.';
          } elseif ($start_date < $today || $end_date < $today) {
              $errors[] = 'Maintenance dates cannot be in the past.';
          } elseif ($end_date < $start_date) {
              $errors[] = 'Maintenance end date cannot be earlier than the start date.';
          }
    }

    return [
        'status' => $status,
        'start_date' => $start_date !== '' ? $start_date : null,
        'end_date' => $end_date !== '' ? $end_date : null
    ];
}

function is_lab_under_maintenance(array $lab, $date = null) {
    if (($lab['maintenance_status'] ?? 'available') !== 'maintenance') {
        return false;
    }

    $start_date = $lab['maintenance_start_date'] ?? null;
    $end_date = $lab['maintenance_end_date'] ?? null;

    if ($date === null || $date === '') {
        return true;
    }

    if ($start_date && $date < $start_date) {
        return false;
    }

    if ($end_date && $date > $end_date) {
        return false;
    }

    return true;
}

function get_lab_maintenance_period_label($start_date, $end_date) {
    if (!$start_date && !$end_date) {
        return 'Maintenance schedule not set.';
    }

    if ($start_date && $end_date) {
        return $start_date . ' to ' . $end_date;
    }

    return $start_date ?: $end_date;
}

function get_lab_maintenance_day_count($start_date, $end_date) {
    if (!$start_date || !$end_date) {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) !== 1) {
        return null;
    }

    try {
        $start = new DateTimeImmutable($start_date);
        $end = new DateTimeImmutable($end_date);
    } catch (Throwable $throwable) {
        return null;
    }

    if ($end < $start) {
        return null;
    }

    return (int) $start->diff($end)->days + 1;
}

function build_booking_calendar_payload(array $booking) {
    $booking_date = trim((string) ($booking['booking_date'] ?? ''));
    $start_time = trim((string) ($booking['start_time'] ?? ''));
    $end_time = trim((string) ($booking['end_time'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
        return null;
    }
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start_time) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end_time)) {
        return null;
    }

    $timezone = date_default_timezone_get() ?: 'Asia/Singapore';
    $start = new DateTime($booking_date . ' ' . $start_time, new DateTimeZone($timezone));
    $end = new DateTime($booking_date . ' ' . $end_time, new DateTimeZone($timezone));

    if ($end <= $start) {
        return null;
    }

    $title = trim((string) ($booking['title'] ?? 'Lab Booking'));
    if ($title === '') {
        $title = 'Lab Booking';
    }

    $description_lines = [];
    $description_lines[] = 'Lab: ' . trim((string) ($booking['lab_name'] ?? '-'));
    if (!empty($booking['cluster_name'])) {
        $description_lines[] = 'Cluster: ' . trim((string) $booking['cluster_name']);
    }
    if (!empty($booking['activity_details'])) {
        $description_lines[] = 'Activity: ' . trim((string) $booking['activity_details']);
    }
    if (!empty($booking['full_name'])) {
        $description_lines[] = 'Applicant: ' . trim((string) $booking['full_name']);
    }
    if (!empty($booking['reservation_email'])) {
        $description_lines[] = 'Email: ' . trim((string) $booking['reservation_email']);
    } elseif (!empty($booking['email'])) {
        $description_lines[] = 'Email: ' . trim((string) $booking['email']);
    }
    if (!empty($booking['phone'])) {
        $description_lines[] = 'Phone: ' . trim((string) $booking['phone']);
    }

    return [
        'booking_id' => (int) ($booking['booking_id'] ?? 0),
        'title' => $title,
        'description' => implode("\n", $description_lines),
        'location' => trim((string) ($booking['lab_name'] ?? '')),
        'timezone' => $timezone,
        'start' => $start,
        'end' => $end
    ];
}

function format_google_calendar_datetime(DateTimeInterface $date_time) {
    return $date_time->format('Ymd\THis');
}

function build_google_calendar_url(array $calendar_payload) {
    $params = [
        'action' => 'TEMPLATE',
        'text' => $calendar_payload['title'],
        'dates' => format_google_calendar_datetime($calendar_payload['start']) . '/' . format_google_calendar_datetime($calendar_payload['end']),
        'details' => $calendar_payload['description'],
        'location' => $calendar_payload['location'],
        'ctz' => $calendar_payload['timezone']
    ];

    return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
}

function escape_ics_text($value) {
    $value = str_replace('\\', '\\\\', (string) $value);
    $value = str_replace(';', '\;', $value);
    $value = str_replace(',', '\,', $value);
    $value = preg_replace("/\r\n|\r|\n/", '\\n', $value);
    return $value;
}

function build_booking_ics_content(array $calendar_payload) {
    $uid = 'labs-booking-' . ((int) $calendar_payload['booking_id']) . '@labs.local';
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $start_utc = (clone $calendar_payload['start'])->setTimezone(new DateTimeZone('UTC'));
    $end_utc = (clone $calendar_payload['end'])->setTimezone(new DateTimeZone('UTC'));

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//LaBS//Booking Calendar//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $now->format('Ymd\THis\Z'),
        'DTSTART:' . $start_utc->format('Ymd\THis\Z'),
        'DTEND:' . $end_utc->format('Ymd\THis\Z'),
        'SUMMARY:' . escape_ics_text($calendar_payload['title']),
        'DESCRIPTION:' . escape_ics_text($calendar_payload['description']),
        'LOCATION:' . escape_ics_text($calendar_payload['location']),
        'END:VEVENT',
        'END:VCALENDAR'
    ];

    return implode("\r\n", $lines) . "\r\n";
}
