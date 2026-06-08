<?php
require_once __DIR__ . '/init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function notifications_json(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($mysqli) || !$mysqli) {
    notifications_json([
        'success' => false,
        'message' => 'Database connection is unavailable.'
    ], 500);
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    notifications_json([
        'success' => false,
        'message' => 'Unauthenticated.'
    ], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'mark_read') {
    $notification_id = isset($_POST['notification_id']) ? (int) $_POST['notification_id'] : 0;
    $ok = mark_user_notifications_read($mysqli, $user_id, $notification_id > 0 ? $notification_id : null);
    notifications_json([
        'success' => $ok,
        'unread_count' => get_unread_notification_count($mysqli, $user_id)
    ], $ok ? 200 : 500);
}

create_active_booking_hold_resume_notifications($mysqli, $user_id);
create_due_booking_hold_reminders($mysqli, $user_id);

$notifications = get_user_notifications($mysqli, $user_id, 12);
$items = array_map(static function (array $item): array {
    return [
        'notification_id' => (int) ($item['notification_id'] ?? 0),
        'notification_type' => (string) ($item['notification_type'] ?? 'info'),
        'title' => (string) ($item['title'] ?? ''),
        'message' => (string) ($item['message'] ?? ''),
        'link_url' => (string) ($item['link_url'] ?? ''),
        'is_read' => (int) ($item['is_read'] ?? 0) === 1,
        'created_at' => (string) ($item['created_at'] ?? '')
    ];
}, $notifications);

notifications_json([
    'success' => true,
    'unread_count' => get_unread_notification_count($mysqli, $user_id),
    'items' => $items
]);
