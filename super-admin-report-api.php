<?php
ob_start();
require_once __DIR__ . '/init.php';

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

function report_json_response(array $payload, int $status = 200) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

set_error_handler(static function ($severity, $message, $file, $line) {
    report_json_response([
        'success' => false,
        'message' => 'Report endpoint error.',
        'details' => $message . ' in ' . basename($file) . ':' . $line
    ], 500);
});

set_exception_handler(static function (Throwable $throwable) {
    report_json_response([
        'success' => false,
        'message' => 'Report endpoint exception.',
        'details' => $throwable->getMessage()
    ], 500);
});

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? 'public';
if ($user_id <= 0) {
    report_json_response([
        'success' => false,
        'message' => 'Authentication required.'
    ], 401);
}

if (!is_super_admin($user_type)) {
    report_json_response([
        'success' => false,
        'message' => 'Super admin access required.'
    ], 403);
}

function get_month_name(int $month): string {
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];

    return $months[$month] ?? 'Unknown';
}

function get_iso_week_range(int $year, int $week): array {
    $start = new DateTime();
    $start->setISODate($year, $week);
    $start->setTime(0, 0, 0);

    $end = clone $start;
    $end->modify('+6 days');

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'label' => sprintf(
            'Week %02d (%s - %s)',
            $week,
            $start->format('d M Y'),
            $end->format('d M Y')
        )
    ];
}

function get_date_range_days(string $start_date, string $end_date): int {
    $start = new DateTimeImmutable($start_date);
    $end = new DateTimeImmutable($end_date);
    return (int) $start->diff($end)->days + 1;
}

function build_report_conditions(array $filters, string &$types, array &$params): string {
    $conditions = [
        'lb.booking_date IS NOT NULL'
    ];

    $filter_type = $filters['filter_type'];
    $year = (int) ($filters['year'] ?? 0);

    if ($filter_type === 'year') {
        $conditions[] = 'YEAR(COALESCE(lr.booking_date, lb.booking_date)) = ?';
        $types .= 'i';
        $params[] = $year;
    } elseif ($filter_type === 'month') {
        $conditions[] = 'YEAR(COALESCE(lr.booking_date, lb.booking_date)) = ?';
        $types .= 'i';
        $params[] = $year;
        $month = (int) $filters['month'];
        $conditions[] = 'MONTH(COALESCE(lr.booking_date, lb.booking_date)) = ?';
        $types .= 'i';
        $params[] = $month;
    } elseif ($filter_type === 'week') {
        $range = get_iso_week_range($year, (int) $filters['week']);
        $conditions[] = 'COALESCE(lr.booking_date, lb.booking_date) BETWEEN ? AND ?';
        $types .= 'ss';
        $params[] = $range['start'];
        $params[] = $range['end'];
    } elseif ($filter_type === 'date') {
        $conditions[] = 'COALESCE(lr.booking_date, lb.booking_date) BETWEEN ? AND ?';
        $types .= 'ss';
        $params[] = (string) $filters['start_date'];
        $params[] = (string) $filters['end_date'];
    }

    if (!empty($filters['cluster_id'])) {
        $conditions[] = 'l.cluster_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['cluster_id'];
    }

    if (!empty($filters['lab_id'])) {
        $conditions[] = 'l.lab_id = ?';
        $types .= 'i';
        $params[] = (int) $filters['lab_id'];
    }

    return ' WHERE ' . implode(' AND ', $conditions) . ' ';
}

function execute_prepared_query(mysqli $mysqli, string $sql, string $types, array $params): array {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        report_json_response([
            'success' => false,
            'message' => 'Unable to prepare report query.'
        ], 500);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        report_json_response([
            'success' => false,
            'message' => 'Unable to execute report query.'
        ], 500);
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function get_filter_label(array $filters, string $cluster_name = '', string $lab_name = ''): string {
    $year = (int) $filters['year'];
    $filter_type = $filters['filter_type'];
    $segments = [];

    if ($filter_type === 'year') {
        $segments[] = 'Year ' . $year;
    } elseif ($filter_type === 'month') {
        $segments[] = get_month_name((int) $filters['month']) . ' ' . $year;
    } elseif ($filter_type === 'week') {
        $range = get_iso_week_range($year, (int) $filters['week']);
        $segments[] = $range['label'];
    } else {
        $segments[] = sprintf(
            'Date %s - %s (%d day%s)',
            (string) $filters['start_date'],
            (string) $filters['end_date'],
            (int) $filters['selected_days'],
            (int) $filters['selected_days'] === 1 ? '' : 's'
        );
    }

    if ($cluster_name !== '') {
        $segments[] = $cluster_name;
    }
    if ($lab_name !== '') {
        $segments[] = $lab_name;
    }

    return implode(' | ', $segments);
}

$action = $_GET['action'] ?? 'report';

if ($action === 'labs') {
    $cluster_id = (int) ($_GET['cluster_id'] ?? 0);
    if ($cluster_id <= 0) {
        report_json_response([
            'success' => true,
            'labs' => []
        ]);
    }

    $labs = execute_prepared_query(
        $mysqli,
        'SELECT lab_id, lab_name FROM labs WHERE cluster_id = ? ORDER BY lab_name ASC',
        'i',
        [$cluster_id]
    );

    report_json_response([
        'success' => true,
        'labs' => array_map(static function (array $lab): array {
            return [
                'lab_id' => (int) $lab['lab_id'],
                'lab_name' => (string) $lab['lab_name']
            ];
        }, $labs)
    ]);
}

$filter_type = $_GET['filter_type'] ?? 'year';
$allowed_types = ['year', 'month', 'week', 'date'];
if (!in_array($filter_type, $allowed_types, true)) {
    $filter_type = 'year';
}

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? 0);
$week = (int) ($_GET['week'] ?? 0);
$start_date = trim((string) ($_GET['start_date'] ?? ''));
$end_date = trim((string) ($_GET['end_date'] ?? ''));
$cluster_id = (int) ($_GET['cluster_id'] ?? 0);
$lab_id = (int) ($_GET['lab_id'] ?? 0);

if ($filter_type !== 'date' && ($year < 2000 || $year > 2100)) {
    report_json_response([
        'success' => false,
        'message' => 'Invalid year selected.'
    ], 422);
}

if ($filter_type === 'month' && ($month < 1 || $month > 12)) {
    report_json_response([
        'success' => false,
        'message' => 'Invalid month selected.'
    ], 422);
}

if ($filter_type === 'week' && ($week < 1 || $week > 53)) {
    report_json_response([
        'success' => false,
        'message' => 'Invalid week selected.'
    ], 422);
}

if ($filter_type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) !== 1) {
    report_json_response([
        'success' => false,
        'message' => 'Invalid start date selected.'
    ], 422);
}

if ($filter_type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) !== 1) {
    report_json_response([
        'success' => false,
        'message' => 'Invalid end date selected.'
    ], 422);
}

if ($filter_type === 'date' && $start_date > $end_date) {
    report_json_response([
        'success' => false,
        'message' => 'End date must be on or after start date.'
    ], 422);
}

$selected_days = $filter_type === 'date' ? get_date_range_days($start_date, $end_date) : 0;

$cluster_name = '';
if ($cluster_id > 0) {
    $cluster_rows = execute_prepared_query(
        $mysqli,
        'SELECT cluster_name FROM clusters WHERE cluster_id = ? LIMIT 1',
        'i',
        [$cluster_id]
    );
    if (!$cluster_rows) {
        report_json_response([
            'success' => false,
            'message' => 'Selected cluster was not found.'
        ], 404);
    }
    $cluster_name = (string) $cluster_rows[0]['cluster_name'];
}

$lab_name = '';
if ($lab_id > 0) {
    if ($cluster_id > 0) {
        $lab_rows = execute_prepared_query(
            $mysqli,
            'SELECT lab_name FROM labs WHERE lab_id = ? AND cluster_id = ? LIMIT 1',
            'ii',
            [$lab_id, $cluster_id]
        );
    } else {
        $lab_rows = execute_prepared_query(
            $mysqli,
            'SELECT lab_name FROM labs WHERE lab_id = ? LIMIT 1',
            'i',
            [$lab_id]
        );
    }

    if (!$lab_rows) {
        report_json_response([
            'success' => false,
            'message' => 'Selected lab was not found.'
        ], 404);
    }
    $lab_name = (string) $lab_rows[0]['lab_name'];
}

$filters = [
    'filter_type' => $filter_type,
    'year' => $year,
    'month' => $month,
    'week' => $week,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'selected_days' => $selected_days,
    'cluster_id' => $cluster_id,
    'lab_id' => $lab_id
];

$types = '';
$params = [];
$where_clause = build_report_conditions($filters, $types, $params);
$booking_pk = get_booking_pk_column($mysqli);

$summary_rows = execute_prepared_query(
    $mysqli,
    "
    SELECT COUNT(*) AS total_bookings,
           COUNT(DISTINCT lb.user_id) AS unique_users,
           COALESCE(SUM(TIMESTAMPDIFF(MINUTE, lr.start_time, lr.end_time)), 0) / 60 AS total_hours
    FROM lab_bookings lb
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.{$booking_pk}
    JOIN labs l ON lb.lab_id = l.lab_id
    JOIN clusters c ON l.cluster_id = c.cluster_id
    {$where_clause}
    ",
    $types,
    $params
);

$summary = $summary_rows[0] ?? [
    'total_bookings' => 0,
    'unique_users' => 0,
    'total_hours' => 0
];

$bar_sql = "
    SELECT %s AS bucket_key,
           %s AS bucket_label,
           COUNT(*) AS total
    FROM lab_bookings lb
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.{$booking_pk}
    JOIN labs l ON lb.lab_id = l.lab_id
    JOIN clusters c ON l.cluster_id = c.cluster_id
    {$where_clause}
    GROUP BY bucket_key, bucket_label
    ORDER BY bucket_key ASC
";

$status_bar_sql = "
    SELECT %s AS bucket_key,
           %s AS bucket_label,
           lb.status,
           COUNT(*) AS total
    FROM lab_bookings lb
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.{$booking_pk}
    JOIN labs l ON lb.lab_id = l.lab_id
    JOIN clusters c ON l.cluster_id = c.cluster_id
    {$where_clause}
    GROUP BY bucket_key, bucket_label, lb.status
    ORDER BY bucket_key ASC, lb.status ASC
";

$is_scoped_bar = $cluster_id > 0 || $lab_id > 0;

if ($filter_type === 'year') {
    $bar_rows = execute_prepared_query(
        $mysqli,
        sprintf($bar_sql, 'MONTH(COALESCE(lr.booking_date, lb.booking_date))', 'DATE_FORMAT(COALESCE(lr.booking_date, lb.booking_date), "%b")'),
        $types,
        $params
    );

    $bar_map = [];
    foreach ($bar_rows as $row) {
        $bar_map[(int) $row['bucket_key']] = (int) $row['total'];
    }

    $bar_chart = [];
    for ($i = 1; $i <= 12; $i++) {
        $bar_chart[] = [
            'label' => date('M', mktime(0, 0, 0, $i, 1, $year)),
            'value' => $bar_map[$i] ?? 0
        ];
    }
} else {
    $bar_rows = execute_prepared_query(
        $mysqli,
        sprintf($bar_sql, 'COALESCE(lr.booking_date, lb.booking_date)', 'DATE_FORMAT(COALESCE(lr.booking_date, lb.booking_date), "%d %b")'),
        $types,
        $params
    );

    $bar_chart = array_map(static function (array $row): array {
        return [
            'label' => (string) $row['bucket_label'],
            'value' => (int) $row['total']
        ];
    }, $bar_rows);
}

if ($filter_type === 'year') {
    $stacked_rows = execute_prepared_query(
        $mysqli,
        sprintf($status_bar_sql, 'MONTH(COALESCE(lr.booking_date, lb.booking_date))', 'DATE_FORMAT(COALESCE(lr.booking_date, lb.booking_date), "%b")'),
        $types,
        $params
    );

    $bucket_labels = [];
    for ($i = 1; $i <= 12; $i++) {
        $bucket_labels[$i] = date('M', mktime(0, 0, 0, $i, 1, $year));
    }
} else {
    $stacked_rows = execute_prepared_query(
        $mysqli,
        sprintf($status_bar_sql, 'COALESCE(lr.booking_date, lb.booking_date)', 'DATE_FORMAT(COALESCE(lr.booking_date, lb.booking_date), "%d %b")'),
        $types,
        $params
    );

    $bucket_labels = [];
    foreach ($bar_chart as $entry) {
        $bucket_labels[$entry['label']] = $entry['label'];
    }
}

$stacked_map = [];
foreach ($bucket_labels as $bucket_key => $bucket_label) {
    $stacked_map[(string) $bucket_key] = [
        'label' => (string) $bucket_label,
        'Approved' => 0,
        'Cancelled' => 0,
        'Rejected' => 0
    ];
}

foreach ($stacked_rows as $row) {
    $bucket_key = (string) $row['bucket_key'];
    if (!isset($stacked_map[$bucket_key])) {
        $stacked_map[$bucket_key] = [
            'label' => (string) $row['bucket_label'],
            'Approved' => 0,
            'Cancelled' => 0,
            'Rejected' => 0
        ];
    }

    $status_key = (string) $row['status'];
    if (isset($stacked_map[$bucket_key][$status_key])) {
        $stacked_map[$bucket_key][$status_key] = (int) $row['total'];
    }
}

$stacked_bar_chart = array_values(array_map(static function (array $row): array {
    return [
        'label' => $row['label'],
        'approved' => (int) $row['Approved'],
        'cancelled' => (int) $row['Cancelled'],
        'rejected' => (int) $row['Rejected'],
        'total' => (int) $row['Approved'] + (int) $row['Cancelled'] + (int) $row['Rejected']
    ];
}, $stacked_map));

$status_rows = execute_prepared_query(
    $mysqli,
    "
    SELECT lb.status, COUNT(*) AS total
    FROM lab_bookings lb
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.{$booking_pk}
    JOIN labs l ON lb.lab_id = l.lab_id
    JOIN clusters c ON l.cluster_id = c.cluster_id
    {$where_clause}
    GROUP BY lb.status
    ORDER BY lb.status ASC
    ",
    $types,
    $params
);

$status_map = [
    'Approved' => 0,
    'Cancelled' => 0,
    'Rejected' => 0
];
foreach ($status_rows as $row) {
    $status = (string) $row['status'];
    if (array_key_exists($status, $status_map)) {
        $status_map[$status] = (int) $row['total'];
    }
}

$status_chart = [
    ['label' => 'Booked', 'value' => $status_map['Approved'], 'color' => '#2f9e44'],
    ['label' => 'Cancelled', 'value' => $status_map['Cancelled'], 'color' => '#64748b'],
    ['label' => 'Rejected', 'value' => $status_map['Rejected'], 'color' => '#c0392b']
];

$table_rows = execute_prepared_query(
    $mysqli,
    "
    SELECT lb.{$booking_pk} AS booking_id,
           COALESCE(lr.title, CONCAT('Booking #', lb.{$booking_pk})) AS title,
           COALESCE(lr.full_name, 'N/A') AS full_name,
           lb.user_id,
           c.cluster_name,
           l.lab_name,
           COALESCE(lr.booking_date, lb.booking_date) AS booking_date,
           lr.start_time,
           lr.end_time,
           lb.time_slot,
           lb.status,
           ROUND(TIMESTAMPDIFF(MINUTE, lr.start_time, lr.end_time) / 60, 2) AS total_hours
    FROM lab_bookings lb
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.{$booking_pk}
    JOIN labs l ON lb.lab_id = l.lab_id
    JOIN clusters c ON l.cluster_id = c.cluster_id
    {$where_clause}
    ORDER BY COALESCE(lr.booking_date, lb.booking_date) DESC, lr.start_time DESC, lb.{$booking_pk} DESC
    LIMIT 250
    ",
    $types,
    $params
);

$table = array_map(static function (array $row): array {
    return [
        'booking_id' => (int) $row['booking_id'],
        'title' => (string) $row['title'],
        'full_name' => (string) $row['full_name'],
        'user_id' => (int) $row['user_id'],
        'cluster_name' => (string) $row['cluster_name'],
        'lab_name' => (string) $row['lab_name'],
        'booking_date' => (string) $row['booking_date'],
        'start_time' => $row['start_time'] ? substr((string) $row['start_time'], 0, 5) : '',
        'end_time' => $row['end_time'] ? substr((string) $row['end_time'], 0, 5) : '',
        'time_slot' => (string) ($row['time_slot'] ?? ''),
        'status' => (string) $row['status'],
        'total_hours' => round((float) $row['total_hours'], 2)
    ];
}, $table_rows);

$total_bookings = (int) ($summary['total_bookings'] ?? 0);
$unique_users = (int) ($summary['unique_users'] ?? 0);
$total_hours = round((float) ($summary['total_hours'] ?? 0), 2);
$average_hours = $total_bookings > 0 ? round($total_hours / $total_bookings, 2) : 0;

report_json_response([
    'success' => true,
    'filter_label' => get_filter_label($filters, $cluster_name, $lab_name),
    'selected_days' => $selected_days,
    'bar_mode' => $is_scoped_bar ? 'stacked' : 'single',
    'summary' => [
        'total_bookings' => $total_bookings,
        'unique_users' => $unique_users,
        'total_hours' => $total_hours,
        'average_hours' => $average_hours
    ],
    'bar_chart' => $bar_chart,
    'stacked_bar_chart' => $stacked_bar_chart,
    'status_chart' => $status_chart,
    'table' => $table
]);
