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
        'class_section' => "ALTER TABLE lab_reservations ADD COLUMN class_section VARCHAR(50) DEFAULT NULL AFTER class_subject_name",
        'booking_mode' => "ALTER TABLE lab_reservations ADD COLUMN booking_mode ENUM('slot','group') NOT NULL DEFAULT 'slot' AFTER booking_purpose",
        'group_booking_type' => "ALTER TABLE lab_reservations ADD COLUMN group_booking_type ENUM('lecture','lab') DEFAULT NULL AFTER booking_mode",
        'group_weeks_count' => "ALTER TABLE lab_reservations ADD COLUMN group_weeks_count INT UNSIGNED DEFAULT NULL AFTER group_booking_type",
        'group_reference_date' => "ALTER TABLE lab_reservations ADD COLUMN group_reference_date DATE DEFAULT NULL AFTER group_weeks_count",
        'group_start_date' => "ALTER TABLE lab_reservations ADD COLUMN group_start_date DATE DEFAULT NULL AFTER group_reference_date",
        'group_end_date' => "ALTER TABLE lab_reservations ADD COLUMN group_end_date DATE DEFAULT NULL AFTER group_start_date",
        'group_midsem_start_date' => "ALTER TABLE lab_reservations ADD COLUMN group_midsem_start_date DATE DEFAULT NULL AFTER group_end_date",
        'group_midsem_end_date' => "ALTER TABLE lab_reservations ADD COLUMN group_midsem_end_date DATE DEFAULT NULL AFTER group_midsem_start_date",
        'group_sessions_json' => "ALTER TABLE lab_reservations ADD COLUMN group_sessions_json LONGTEXT DEFAULT NULL AFTER group_end_date",
        'group_booking_key' => "ALTER TABLE lab_reservations ADD COLUMN group_booking_key VARCHAR(64) DEFAULT NULL AFTER group_sessions_json"
    ];

    $existing_columns = [];
    $stmt = $mysqli->prepare("
        SELECT column_name
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'lab_reservations'
          AND column_name IN ('booking_purpose', 'class_course_code', 'class_subject_name', 'class_section', 'booking_mode', 'group_booking_type', 'group_weeks_count', 'group_reference_date', 'group_start_date', 'group_end_date', 'group_midsem_start_date', 'group_midsem_end_date', 'group_sessions_json', 'group_booking_key')
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

    $length_stmt = $mysqli->prepare("
        SELECT CHARACTER_MAXIMUM_LENGTH
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'lab_reservations'
          AND column_name = 'class_course_code'
        LIMIT 1
    ");
    if ($length_stmt && $length_stmt->execute()) {
        $should_resize_course_code = false;
        $length_stmt->bind_result($course_code_length);
        if ($length_stmt->fetch()) {
            $should_resize_course_code = (int) $course_code_length !== 8;
        }
        $length_stmt->close();
        if ($should_resize_course_code) {
            $mysqli->query("ALTER TABLE lab_reservations MODIFY COLUMN class_course_code VARCHAR(8) DEFAULT NULL");
        }
    }

    $ensured = true;
}

function ensure_password_reset_table($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $mysqli->query('
        CREATE TABLE IF NOT EXISTS password_reset_codes (
            reset_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_password_reset_email (email, used_at, expires_at),
            KEY idx_password_reset_user (user_id),
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    $ensured = true;
}

function ensure_user_contact_columns($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $required_columns = [
        'notify_email' => "ALTER TABLE users ADD COLUMN notify_email TINYINT(1) NOT NULL DEFAULT 1 AFTER student_staff_id"
    ];

    $existing_columns = [];
    $stmt = $mysqli->prepare("
        SELECT column_name
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'users'
          AND column_name IN ('notify_email')
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

function ensure_user_notifications_table($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $mysqli->query('
        CREATE TABLE IF NOT EXISTS user_notifications (
            notification_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            notification_type VARCHAR(32) NOT NULL DEFAULT "info",
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_user_notifications_user (user_id, is_read, created_at),
            CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    $ensured = true;
}

function ensure_booking_holds_table($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $mysqli->query('
        CREATE TABLE IF NOT EXISTS booking_holds (
            hold_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hold_token VARCHAR(64) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            lab_id BIGINT(20) UNSIGNED NOT NULL,
            booking_date DATE NOT NULL,
            time_slot VARCHAR(32) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_booking_hold_slot (lab_id, booking_date, time_slot),
            KEY idx_booking_hold_token (hold_token),
            KEY idx_booking_hold_user (user_id),
            KEY idx_booking_hold_expiry (expires_at),
            CONSTRAINT fk_booking_holds_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_booking_holds_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    $ensured = true;
}

function cleanup_expired_booking_holds($mysqli): void {
    if (!$mysqli) {
        return;
    }
    $mysqli->query('DELETE FROM booking_holds WHERE expires_at <= NOW()');
}

function labs_generate_hold_token(): string {
    try {
        return 'hold_' . bin2hex(random_bytes(16));
    } catch (Throwable $throwable) {
        return 'hold_' . uniqid('', true);
    }
}

function get_or_create_booking_hold($mysqli, int $user_id, int $lab_id, string $booking_date, array $time_slots, int $hold_minutes = 15): array {
    if (!$mysqli || $user_id <= 0 || $lab_id <= 0 || $booking_date === '' || !$time_slots) {
        return ['ok' => false, 'error' => 'Invalid hold request.'];
    }

    cleanup_expired_booking_holds($mysqli);
    $time_slots = array_values(array_unique(array_filter(array_map('trim', $time_slots))));
    sort($time_slots);

    $existing_rows = [];
    $stmt = $mysqli->prepare('
        SELECT hold_token, time_slot, expires_at
        FROM booking_holds
        WHERE user_id = ?
          AND lab_id = ?
          AND booking_date = ?
          AND expires_at > NOW()
        ORDER BY hold_token ASC, time_slot ASC
    ');
    if ($stmt) {
        $stmt->bind_param('iis', $user_id, $lab_id, $booking_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existing_rows[] = $row;
        }
        $stmt->close();
    }

    if ($existing_rows) {
        $existing_token = (string) ($existing_rows[0]['hold_token'] ?? '');
        $existing_expiry = (string) ($existing_rows[0]['expires_at'] ?? '');
        $existing_slots = [];
        $same_token = true;
        foreach ($existing_rows as $row) {
            $existing_slots[] = trim((string) ($row['time_slot'] ?? ''));
            if ((string) ($row['hold_token'] ?? '') !== $existing_token) {
                $same_token = false;
            }
        }
        sort($existing_slots);
        if ($same_token && $existing_slots === $time_slots) {
            return [
                'ok' => true,
                'token' => $existing_token,
                'expires_at' => $existing_expiry
            ];
        }
    }

    $approved_stmt = $mysqli->prepare('
        SELECT 1
        FROM lab_bookings
        WHERE lab_id = ?
          AND booking_date = ?
          AND time_slot = ?
          AND status = "Approved"
        LIMIT 1
    ');
    $hold_conflict_stmt = $mysqli->prepare('
        SELECT 1
        FROM booking_holds
        WHERE lab_id = ?
          AND booking_date = ?
          AND time_slot = ?
          AND expires_at > NOW()
        LIMIT 1
    ');
    if (!$approved_stmt || !$hold_conflict_stmt) {
        if ($approved_stmt) {
            $approved_stmt->close();
        }
        if ($hold_conflict_stmt) {
            $hold_conflict_stmt->close();
        }
        return ['ok' => false, 'error' => 'Unable to prepare booking hold.'];
    }

    foreach ($time_slots as $slot) {
        $approved_stmt->bind_param('iss', $lab_id, $booking_date, $slot);
        $approved_stmt->execute();
        $approved_result = $approved_stmt->get_result();
        if ($approved_result && $approved_result->fetch_assoc()) {
            $approved_stmt->close();
            $hold_conflict_stmt->close();
            return ['ok' => false, 'error' => 'One or more selected slots are already booked.'];
        }

        $hold_conflict_stmt->bind_param('iss', $lab_id, $booking_date, $slot);
        $hold_conflict_stmt->execute();
        $hold_result = $hold_conflict_stmt->get_result();
        if ($hold_result && $hold_result->fetch_assoc()) {
            $approved_stmt->close();
            $hold_conflict_stmt->close();
            return ['ok' => false, 'error' => 'One or more selected slots are temporarily held by another user.'];
        }
    }
    $approved_stmt->close();
    $hold_conflict_stmt->close();

    $hold_token = labs_generate_hold_token();
    $hold_minutes = max(1, $hold_minutes);
    $expires_at = '';
    $expiry_stmt = $mysqli->prepare('SELECT DATE_FORMAT(DATE_ADD(NOW(), INTERVAL ? MINUTE), "%Y-%m-%d %H:%i:%s") AS expires_at');
    if ($expiry_stmt) {
        $expiry_stmt->bind_param('i', $hold_minutes);
        $expiry_stmt->execute();
        $expiry_result = $expiry_stmt->get_result();
        $expiry_row = $expiry_result ? $expiry_result->fetch_assoc() : null;
        $expires_at = (string) ($expiry_row['expires_at'] ?? '');
        $expiry_stmt->close();
    }
    if ($expires_at === '') {
        return ['ok' => false, 'error' => 'Unable to calculate booking hold expiry.'];
    }

    $mysqli->begin_transaction();
    try {
        $delete_stmt = $mysqli->prepare('
            DELETE FROM booking_holds
            WHERE user_id = ?
              AND lab_id = ?
              AND booking_date = ?
        ');
        if ($delete_stmt) {
            $delete_stmt->bind_param('iis', $user_id, $lab_id, $booking_date);
            $delete_stmt->execute();
            $delete_stmt->close();
        }

        $insert_stmt = $mysqli->prepare('
            INSERT INTO booking_holds (hold_token, user_id, lab_id, booking_date, time_slot, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        if (!$insert_stmt) {
            throw new Exception('Unable to create booking hold.');
        }
        foreach ($time_slots as $slot) {
            $insert_stmt->bind_param('siisss', $hold_token, $user_id, $lab_id, $booking_date, $slot, $expires_at);
            $insert_stmt->execute();
        }
        $insert_stmt->close();
        $mysqli->commit();
    } catch (Throwable $throwable) {
        $mysqli->rollback();
        return ['ok' => false, 'error' => 'Unable to lock selected slots for confirmation.'];
    }

    return [
        'ok' => true,
        'token' => $hold_token,
        'expires_at' => $expires_at
    ];
}

function validate_booking_hold($mysqli, string $hold_token, int $user_id, int $lab_id, string $booking_date, array $time_slots): array {
    if (!$mysqli || $hold_token === '' || $user_id <= 0 || $lab_id <= 0 || $booking_date === '' || !$time_slots) {
        return ['ok' => false, 'error' => 'Booking hold is missing.'];
    }

    cleanup_expired_booking_holds($mysqli);
    $time_slots = array_values(array_unique(array_filter(array_map('trim', $time_slots))));
    sort($time_slots);

    $rows = [];
    $stmt = $mysqli->prepare('
        SELECT time_slot, expires_at
        FROM booking_holds
        WHERE hold_token = ?
          AND user_id = ?
          AND lab_id = ?
          AND booking_date = ?
          AND expires_at > NOW()
        ORDER BY time_slot ASC
    ');
    if ($stmt) {
        $stmt->bind_param('siis', $hold_token, $user_id, $lab_id, $booking_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }

    if (!$rows) {
        return ['ok' => false, 'error' => 'Your 15-minute hold has expired. Please select the slot again.'];
    }

    $held_slots = [];
    $expires_at = (string) ($rows[0]['expires_at'] ?? '');
    foreach ($rows as $row) {
        $held_slots[] = trim((string) ($row['time_slot'] ?? ''));
    }
    sort($held_slots);

    if ($held_slots !== $time_slots) {
        return ['ok' => false, 'error' => 'Selected slot hold is no longer valid. Please select the slot again.'];
    }

    return ['ok' => true, 'expires_at' => $expires_at];
}

function release_booking_hold($mysqli, string $hold_token, int $user_id): void {
    if (!$mysqli || $hold_token === '' || $user_id <= 0) {
        return;
    }
    $stmt = $mysqli->prepare('DELETE FROM booking_holds WHERE hold_token = ? AND user_id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $hold_token, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

function create_user_notification($mysqli, int $user_id, string $title, string $message, string $type = 'info', ?string $link_url = null): bool {
    $user_id = (int) $user_id;
    if (!$mysqli || $user_id <= 0) {
        return false;
    }

    $type = trim($type) !== '' ? trim($type) : 'info';
    $title = trim($title);
    $message = trim($message);
    if ($title === '' || $message === '') {
        return false;
    }

    $stmt = $mysqli->prepare('
        INSERT INTO user_notifications (user_id, notification_type, title, message, link_url, is_read, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())
    ');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('issss', $user_id, $type, $title, $message, $link_url);
    $created = $stmt->execute();
    $stmt->close();
    return $created;
}

function get_user_notifications($mysqli, int $user_id, int $limit = 10): array {
    $user_id = (int) $user_id;
    $limit = max(1, min(50, (int) $limit));
    if (!$mysqli || $user_id <= 0) {
        return [];
    }

    $notifications = [];
    $stmt = $mysqli->prepare("
        SELECT notification_id, notification_type, title, message, link_url, is_read, read_at, created_at
        FROM user_notifications
        WHERE user_id = ?
        ORDER BY is_read ASC, created_at DESC
        LIMIT {$limit}
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    return $notifications;
}

function get_unread_notification_count($mysqli, int $user_id): int {
    $user_id = (int) $user_id;
    if (!$mysqli || $user_id <= 0) {
        return 0;
    }

    $stmt = $mysqli->prepare('SELECT COUNT(*) AS total FROM user_notifications WHERE user_id = ? AND is_read = 0');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

function get_mail_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'transport' => 'auto',
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'timeout' => 15,
        'from_email' => 'no-reply@labs.local',
        'from_name' => 'LaBS PPMKCP',
        'debug_log' => __DIR__ . '/logs/mail.log'
    ];

    $config = $defaults;

    $custom_config_path = __DIR__ . '/mail-config.php';
    if (is_file($custom_config_path)) {
        $custom = require $custom_config_path;
        if (is_array($custom)) {
            $config = array_merge($config, $custom);
        }
    }

    $config['transport'] = strtolower(trim((string) ($config['transport'] ?? 'mail')));
    $config['host'] = trim((string) ($config['host'] ?? ''));
    $config['port'] = (int) ($config['port'] ?? 587);
    $config['encryption'] = strtolower(trim((string) ($config['encryption'] ?? 'tls')));
    $config['username'] = trim((string) ($config['username'] ?? ''));
    $config['password'] = (string) ($config['password'] ?? '');
    $config['timeout'] = max(5, (int) ($config['timeout'] ?? 15));
    $config['from_email'] = trim((string) ($config['from_email'] ?? 'no-reply@labs.local'));
    $config['from_name'] = trim((string) ($config['from_name'] ?? 'LaBS PPMKCP'));
    $config['debug_log'] = trim((string) ($config['debug_log'] ?? (__DIR__ . '/logs/mail.log')));

    return $config;
}

function load_composer_autoload(): bool {
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    $autoload_path = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload_path)) {
        $loaded = false;
        return false;
    }

    require_once $autoload_path;
    $loaded = true;
    return true;
}

function log_mail_debug(string $message): void {
    $config = get_mail_config();
    $log_path = $config['debug_log'] ?? '';
    if ($log_path === '') {
        return;
    }

    $directory = dirname($log_path);
    if (!is_dir($directory)) {
        @mkdir($directory, 0777, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($log_path, $line, FILE_APPEND);
}

function encode_mail_header(string $value): string {
    if ($value === '') {
        return '';
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function build_mail_headers(array $config, string $to_email, string $to_name, string $subject, string $plain_text, ?string $html = null): array {
    $from_name = trim((string) ($config['from_name'] ?? 'LaBS PPMKCP'));
    $from_email = trim((string) ($config['from_email'] ?? 'no-reply@labs.local'));
    $boundary = 'labs-' . bin2hex(random_bytes(8));

    $headers = [
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@labs.local>',
        'MIME-Version: 1.0',
        'From: ' . encode_mail_header($from_name) . ' <' . $from_email . '>',
        'To: ' . encode_mail_header($to_name) . ' <' . $to_email . '>',
        'Subject: ' . encode_mail_header($subject),
    ];

    if ($html !== null && trim($html) !== '') {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $plain_text,
            '',
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $html,
            '',
            '--' . $boundary . '--',
            ''
        ]);
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = $plain_text;
    }

    return [
        'headers' => $headers,
        'body' => $body,
        'envelope_from' => $from_email
    ];
}

function smtp_read_response($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }
    return $response;
}

function smtp_expect_code(string $response, array $expected_codes): bool {
    $code = (int) substr(trim($response), 0, 3);
    return in_array($code, $expected_codes, true);
}

function smtp_send_command($socket, string $command, array $expected_codes): array {
    fwrite($socket, $command . "\r\n");
    $response = smtp_read_response($socket);
    return [
        'ok' => smtp_expect_code($response, $expected_codes),
        'response' => $response
    ];
}

function send_via_smtp(array $config, string $to_email, string $to_name, string $subject, string $plain_text, ?string $html = null): bool {
    $host = $config['host'] ?? '';
    $port = (int) ($config['port'] ?? 587);
    $timeout = (int) ($config['timeout'] ?? 15);
    $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');

    if ($host === '' || $port <= 0) {
        log_mail_debug('SMTP skipped: host or port missing.');
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        log_mail_debug('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $response = smtp_read_response($socket);
    if (!smtp_expect_code($response, [220])) {
        log_mail_debug('SMTP greeting failed: ' . trim($response));
        fclose($socket);
        return false;
    }

    $host_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $hello = smtp_send_command($socket, 'EHLO ' . $host_name, [250]);
    if (!$hello['ok']) {
        $hello = smtp_send_command($socket, 'HELO ' . $host_name, [250]);
        if (!$hello['ok']) {
            log_mail_debug('SMTP HELO/EHLO failed: ' . trim($hello['response']));
            fclose($socket);
            return false;
        }
    }

    if ($encryption === 'tls') {
        $tls = smtp_send_command($socket, 'STARTTLS', [220]);
        if (!$tls['ok']) {
            log_mail_debug('SMTP STARTTLS failed: ' . trim($tls['response']));
            fclose($socket);
            return false;
        }
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            log_mail_debug('SMTP TLS crypto enable failed.');
            fclose($socket);
            return false;
        }
        $hello = smtp_send_command($socket, 'EHLO ' . $host_name, [250]);
        if (!$hello['ok']) {
            log_mail_debug('SMTP EHLO after STARTTLS failed: ' . trim($hello['response']));
            fclose($socket);
            return false;
        }
    }

    if ($username !== '') {
        $auth = smtp_send_command($socket, 'AUTH LOGIN', [334]);
        if (!$auth['ok']) {
            log_mail_debug('SMTP AUTH LOGIN failed: ' . trim($auth['response']));
            fclose($socket);
            return false;
        }
        $user = smtp_send_command($socket, base64_encode($username), [334]);
        if (!$user['ok']) {
            log_mail_debug('SMTP username rejected: ' . trim($user['response']));
            fclose($socket);
            return false;
        }
        $pass = smtp_send_command($socket, base64_encode($password), [235]);
        if (!$pass['ok']) {
            log_mail_debug('SMTP password rejected: ' . trim($pass['response']));
            fclose($socket);
            return false;
        }
    }

    $message = build_mail_headers($config, $to_email, $to_name, $subject, $plain_text, $html);

    $mail_from = smtp_send_command($socket, 'MAIL FROM:<' . $message['envelope_from'] . '>', [250]);
    if (!$mail_from['ok']) {
        log_mail_debug('SMTP MAIL FROM failed: ' . trim($mail_from['response']));
        fclose($socket);
        return false;
    }

    $rcpt_to = smtp_send_command($socket, 'RCPT TO:<' . $to_email . '>', [250, 251]);
    if (!$rcpt_to['ok']) {
        log_mail_debug('SMTP RCPT TO failed: ' . trim($rcpt_to['response']));
        fclose($socket);
        return false;
    }

    $data = smtp_send_command($socket, 'DATA', [354]);
    if (!$data['ok']) {
        log_mail_debug('SMTP DATA failed: ' . trim($data['response']));
        fclose($socket);
        return false;
    }

    $payload = implode("\r\n", $message['headers']) . "\r\n\r\n" . str_replace("\n.", "\n..", $message['body']) . "\r\n.";
    fwrite($socket, $payload . "\r\n");
    $data_response = smtp_read_response($socket);
    if (!smtp_expect_code($data_response, [250])) {
        log_mail_debug('SMTP message rejected: ' . trim($data_response));
        fclose($socket);
        return false;
    }

    smtp_send_command($socket, 'QUIT', [221]);
    fclose($socket);
    log_mail_debug('SMTP sent: ' . $subject . ' -> ' . $to_email);
    return true;
}

function send_via_phpmailer(array $config, string $to_email, string $to_name, string $subject, string $plain_text, ?string $html = null): bool {
    if (!load_composer_autoload() || !class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        log_mail_debug('PHPMailer skipped: vendor autoload or class missing.');
        return false;
    }

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = (int) ($config['timeout'] ?? 15);
        $mailer->setFrom((string) ($config['from_email'] ?? 'no-reply@labs.local'), (string) ($config['from_name'] ?? 'LaBS PPMKCP'));
        $mailer->addAddress($to_email, $to_name);
        $mailer->Subject = $subject;
        $mailer->Body = $html !== null && trim($html) !== '' ? $html : nl2br(htmlspecialchars($plain_text, ENT_QUOTES, 'UTF-8'));
        $mailer->AltBody = $plain_text;
        $mailer->isHTML(true);

        $transport = strtolower((string) ($config['transport'] ?? 'auto'));
        $host = trim((string) ($config['host'] ?? ''));
        if (in_array($transport, ['phpmailer', 'smtp', 'auto'], true) && $host !== '') {
            $mailer->isSMTP();
            $mailer->Host = $host;
            $mailer->Port = (int) ($config['port'] ?? 587);
            $mailer->SMTPAuth = trim((string) ($config['username'] ?? '')) !== '';
            $mailer->Username = (string) ($config['username'] ?? '');
            $mailer->Password = (string) ($config['password'] ?? '');

            $encryption = strtolower(trim((string) ($config['encryption'] ?? 'tls')));
            if ($encryption === 'ssl') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mailer->SMTPSecure = false;
                $mailer->SMTPAutoTLS = false;
            }
        } else {
            $mailer->isMail();
        }

        $mailer->send();
        log_mail_debug('PHPMailer sent: ' . $subject . ' -> ' . $to_email);
        return true;
    } catch (\Throwable $throwable) {
        log_mail_debug('PHPMailer failed: ' . $throwable->getMessage());
        return false;
    }
}

function send_labs_email(string $email, string $name, string $subject, string $plain_text, ?string $html = null): bool {
    $email = trim($email);
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        log_mail_debug('Email skipped: invalid recipient [' . $email . ']');
        return false;
    }

    $config = get_mail_config();
    $transport = strtolower((string) ($config['transport'] ?? 'auto'));
    if (in_array($transport, ['auto', 'phpmailer'], true)) {
        $phpmailer_sent = send_via_phpmailer($config, $email, $name, $subject, $plain_text, $html);
        if ($phpmailer_sent) {
            return true;
        }
        if ($transport === 'phpmailer') {
            return false;
        }
    }

    if ($transport === 'smtp') {
        return send_via_smtp($config, $email, $name, $subject, $plain_text, $html);
    }

    $message = build_mail_headers($config, $email, $name, $subject, $plain_text, $html);
    $headers = $message['headers'];

    $header_string = implode("\r\n", array_filter($headers, static function ($line) {
        return strpos($line, 'To: ') !== 0 && strpos($line, 'Subject: ') !== 0;
    }));

    $sent = @mail($email, $subject, $message['body'], $header_string);
    log_mail_debug(($sent ? 'mail() sent: ' : 'mail() failed: ') . $subject . ' -> ' . $email);
    return $sent;
}

function mark_user_notifications_read($mysqli, int $user_id, ?int $notification_id = null): bool {
    $user_id = (int) $user_id;
    if (!$mysqli || $user_id <= 0) {
        return false;
    }

    if ($notification_id !== null && $notification_id > 0) {
        $stmt = $mysqli->prepare('
            UPDATE user_notifications
            SET is_read = 1, read_at = NOW(), updated_at = NOW()
            WHERE notification_id = ? AND user_id = ?
        ');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $notification_id, $user_id);
    } else {
        $stmt = $mysqli->prepare('
            UPDATE user_notifications
            SET is_read = 1, read_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $user_id);
    }

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function send_system_notification_email(string $email, string $name, string $subject, string $message): bool {
    $email = trim($email);
    if ($email === '') {
        return false;
    }

    $display_name = trim($name) !== '' ? trim($name) : 'User';
    $payload = implode("\r\n", [
        'Hello ' . $display_name . ',',
        '',
        trim($message),
        '',
        'This is an automated notification from LaBS PPMKCP.'
    ]);
    $html = '<p>Hello ' . htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>' . nl2br(htmlspecialchars(trim($message), ENT_QUOTES, 'UTF-8')) . '</p>'
        . '<p>This is an automated notification from LaBS PPMKCP.</p>';

    return send_labs_email($email, $display_name, $subject, $payload, $html);
}

function get_user_notification_channels($mysqli, int $user_id): array {
    $defaults = [
        'notify_email' => true
    ];
    if (!$mysqli || $user_id <= 0) {
        return $defaults;
    }

    $stmt = $mysqli->prepare('SELECT notify_email FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return $defaults;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return $defaults;
    }

    return [
        'notify_email' => !empty($row['notify_email'])
    ];
}

function create_and_send_user_notification($mysqli, int $user_id, string $title, string $message, string $type = 'info', ?string $link_url = null, bool $send_email = false): bool {
    $created = create_user_notification($mysqli, $user_id, $title, $message, $type, $link_url);
    if ($send_email && $mysqli && $user_id > 0) {
        $stmt = $mysqli->prepare('SELECT name, email, notify_email FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            if ($send_email && $row && !empty($row['notify_email']) && !empty($row['email'])) {
                send_system_notification_email((string) $row['email'], (string) ($row['name'] ?? 'User'), $title, $message);
            }
        }
    }
    return $created;
}

function get_cluster_admin_user_ids($mysqli, int $cluster_id): array {
    $cluster_id = (int) $cluster_id;
    if (!$mysqli || $cluster_id <= 0) {
        return [];
    }

    $ids = [];
    $stmt = $mysqli->prepare("
        SELECT id
        FROM users
        WHERE user_type = 'cluster_admin' AND cluster_id = ?
        ORDER BY id ASC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $cluster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $stmt->close();
    }

    return $ids;
}

function get_lab_supervisor_user_ids($mysqli, int $lab_id): array {
    $lab_id = (int) $lab_id;
    if (!$mysqli || $lab_id <= 0) {
        return [];
    }

    $ids = [];
    $stmt = $mysqli->prepare("
        SELECT DISTINCT lsl.user_id
        FROM lab_supervisor_labs lsl
        JOIN users u ON u.id = lsl.user_id
        WHERE lsl.lab_id = ? AND u.user_type = 'lab_supervisor'
        ORDER BY lsl.user_id ASC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int) $row['user_id'];
        }
        $stmt->close();
    }

    return $ids;
}

function get_lab_notification_recipient_user_ids($mysqli, int $lab_id, int $cluster_id = 0, array $exclude_user_ids = []): array {
    $recipient_ids = array_merge(
        get_lab_supervisor_user_ids($mysqli, $lab_id),
        get_cluster_admin_user_ids($mysqli, $cluster_id)
    );

    $recipient_ids = array_values(array_unique(array_map('intval', $recipient_ids)));
    $excluded = array_values(array_unique(array_map('intval', $exclude_user_ids)));
    if ($excluded) {
        $recipient_ids = array_values(array_filter($recipient_ids, static function ($user_id) use ($excluded) {
            return !in_array((int) $user_id, $excluded, true);
        }));
    }

    return $recipient_ids;
}

function create_and_send_bulk_user_notifications($mysqli, array $user_ids, string $title, string $message, string $type = 'info', ?string $link_url = null, bool $send_email = false): void {
    foreach (array_values(array_unique(array_map('intval', $user_ids))) as $user_id) {
        if ($user_id <= 0) {
            continue;
        }
        create_and_send_user_notification($mysqli, $user_id, $title, $message, $type, $link_url, $send_email);
    }
}

function send_password_reset_code_email(string $email, string $name, string $code): bool {
    $subject = 'LaBS Password Reset Code';
    $display_name = trim($name) !== '' ? $name : 'User';
    $message_lines = [
        'Hello ' . $display_name . ',',
        '',
        'We received a request to reset your LaBS account password.',
        'Your verification code is: ' . $code,
        '',
        'This code expires in 15 minutes.',
        'If you did not request this reset, you can ignore this email.'
    ];
    $message = implode("\r\n", $message_lines);
    $html = '<p>Hello ' . htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>We received a request to reset your LaBS account password.</p>'
        . '<p><strong>Your verification code is: ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong></p>'
        . '<p>This code expires in 15 minutes.</p>'
        . '<p>If you did not request this reset, you can ignore this email.</p>';

    return send_labs_email($email, $display_name, $subject, $message, $html);
}

function ensure_lab_supervisor_history($mysqli) {
    static $ensured = false;
    if ($ensured || !$mysqli) {
        return;
    }

    $mysqli->query('
        CREATE TABLE IF NOT EXISTS lab_supervisor_history (
            history_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lab_id BIGINT(20) UNSIGNED NOT NULL,
            previous_supervisor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            supervisor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lab_history_lab (lab_id, ended_at, started_at),
            KEY idx_lab_history_supervisor (supervisor_id),
            KEY idx_lab_history_previous_supervisor (previous_supervisor_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    $mysqli->query('
        INSERT INTO lab_supervisor_history (
            lab_id,
            previous_supervisor_id,
            supervisor_id,
            started_at,
            ended_at,
            created_at,
            updated_at
        )
        SELECT
            l.lab_id,
            NULL,
            l.supervisor_id,
            COALESCE(l.updated_at, l.created_at, NOW()),
            NULL,
            NOW(),
            NOW()
        FROM labs l
        LEFT JOIN lab_supervisor_history h
            ON h.lab_id = l.lab_id
           AND h.ended_at IS NULL
        WHERE l.supervisor_id IS NOT NULL
          AND h.history_id IS NULL
    ');

    $ensured = true;
}

function sync_lab_supervisor_history($mysqli, $lab_id, $new_supervisor_id, $started_at = null) {
    if (!$mysqli) {
        return;
    }

    $lab_id = (int) $lab_id;
    $new_supervisor_id = (int) $new_supervisor_id;
    if ($lab_id <= 0) {
        return;
    }

    $current_history = null;
    $stmt = $mysqli->prepare('
        SELECT history_id, supervisor_id, previous_supervisor_id
        FROM lab_supervisor_history
        WHERE lab_id = ? AND ended_at IS NULL
        ORDER BY started_at DESC, history_id DESC
        LIMIT 1
    ');
    if ($stmt) {
        $stmt->bind_param('i', $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_history = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }

    if ($current_history && (int) $current_history['supervisor_id'] === $new_supervisor_id) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $previous_supervisor_id = null;

    if ($current_history) {
        $previous_supervisor_id = $current_history['supervisor_id'] !== null ? (int) $current_history['supervisor_id'] : null;

        $stmt = $mysqli->prepare('
            UPDATE lab_supervisor_history
            SET ended_at = ?, updated_at = ?
            WHERE history_id = ?
        ');
        if ($stmt) {
            $history_id = (int) $current_history['history_id'];
            $stmt->bind_param('ssi', $now, $now, $history_id);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($new_supervisor_id > 0) {
        $last_history = null;
        $stmt = $mysqli->prepare('
            SELECT supervisor_id
            FROM lab_supervisor_history
            WHERE lab_id = ? AND ended_at IS NOT NULL
            ORDER BY ended_at DESC, history_id DESC
            LIMIT 1
        ');
        if ($stmt) {
            $stmt->bind_param('i', $lab_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $last_history = $result ? $result->fetch_assoc() : null;
            $stmt->close();
        }
        if ($last_history && $last_history['supervisor_id'] !== null) {
            $previous_supervisor_id = (int) $last_history['supervisor_id'];
        }
    }

    if ($new_supervisor_id > 0) {
        $started_at = $started_at ?: $now;
        if ($previous_supervisor_id !== null) {
            $stmt = $mysqli->prepare('
                INSERT INTO lab_supervisor_history (
                    lab_id,
                    previous_supervisor_id,
                    supervisor_id,
                    started_at,
                    ended_at,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, NULL, ?, ?)
            ');
            if ($stmt) {
                $stmt->bind_param('iiisss', $lab_id, $previous_supervisor_id, $new_supervisor_id, $started_at, $now, $now);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $mysqli->prepare('
                INSERT INTO lab_supervisor_history (
                    lab_id,
                    previous_supervisor_id,
                    supervisor_id,
                    started_at,
                    ended_at,
                    created_at,
                    updated_at
                ) VALUES (?, NULL, ?, ?, NULL, ?, ?)
            ');
            if ($stmt) {
                $stmt->bind_param('iisss', $lab_id, $new_supervisor_id, $started_at, $now, $now);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
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
