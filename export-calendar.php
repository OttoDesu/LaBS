<?php
require_once __DIR__ . '/init.php';
require_login();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? 'public';
$is_management = is_management_user($user_type);
$booking_pk = get_booking_pk_column($mysqli);

$booking_id = (int) ($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    http_response_code(404);
    exit('Booking not found.');
}

$params = [$booking_id];
$types = 'i';
$where = 'lb.' . $booking_pk . ' = ?';

if (!$is_management) {
    $where .= ' AND lb.user_id = ?';
    $params[] = $user_id;
    $types .= 'i';
}

$stmt = $mysqli->prepare('
    SELECT lb.' . $booking_pk . ' AS booking_id, lb.user_id, lb.booking_date, lb.status,
           l.lab_name, c.cluster_name,
           lr.title, lr.activity_details, lr.full_name, lr.email AS reservation_email, lr.phone,
           lr.start_time, lr.end_time
    FROM lab_bookings lb
    JOIN labs l ON l.lab_id = lb.lab_id
    JOIN clusters c ON c.cluster_id = l.cluster_id
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.' . $booking_pk . '
    WHERE ' . $where . '
    LIMIT 1
');
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

$calendar_payload = build_booking_calendar_payload($booking);
if ($calendar_payload === null) {
    http_response_code(400);
    exit('This booking cannot be exported to calendar.');
}

$ics = build_booking_ics_content($calendar_payload);
$filename = 'labs-booking-' . (int) $booking['booking_id'] . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($ics));
echo $ics;
