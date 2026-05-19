<?php
require_once __DIR__ . '/../init.php';

function fail($message) {
    echo "FAIL: " . $message . PHP_EOL;
    exit(1);
}

$stmt = $mysqli->prepare("SELECT id FROM users WHERE user_type = 'lab_supervisor' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    fail('No lab_supervisor user found.');
}

$user_id = (int) $user['id'];
$lab_ids = get_lab_supervisor_lab_ids($mysqli, $user_id);
if (!$lab_ids) {
    fail('Lab supervisor has no lab scope assigned.');
}

$placeholders = implode(',', array_fill(0, count($lab_ids), '?'));
$types = str_repeat('i', count($lab_ids));

$stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM lab_bookings WHERE lab_id IN ($placeholders)");
$stmt->bind_param($types, ...$lab_ids);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

echo "OK: Lab supervisor scoped booking count = " . (int) ($row['total'] ?? 0) . PHP_EOL;
echo "OK: Lab supervisor scope lab_ids = " . implode(',', $lab_ids) . PHP_EOL;
