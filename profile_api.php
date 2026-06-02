<?php
require_once __DIR__ . '/init.php';
require_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $mysqli->prepare('SELECT name, email, ic_no, phone, department AS organization, user_type, notify_email FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['ok' => false, 'error' => 'User not found.']);
        exit;
    }

    echo json_encode(['ok' => true, 'user' => $user]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $ic_no = trim($_POST['ic_no'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $notify_email = isset($_POST['notify_email']) ? 1 : 0;
    $user_type = $_SESSION['user_type'] ?? 'public';

    $current_ic = '';
    $stmt = $mysqli->prepare('SELECT ic_no FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_ic = $row['ic_no'] ?? '';
    }
    $stmt->close();

    $errors = [];
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($current_ic !== '') {
        $ic_no = $current_ic;
    } elseif ($ic_no !== '' && !preg_match('/^\d{12}$/', $ic_no)) {
        $errors[] = 'IC number must be 12 digits.';
    }
    if ($phone !== '' && !preg_match('/^\d{9,12}$/', $phone)) {
        $errors[] = 'Contact number must be 9 to 12 digits.';
    }
    if ($errors) {
        echo json_encode(['ok' => false, 'errors' => $errors]);
        exit;
    }

    $stmt = $mysqli->prepare('UPDATE users SET name = ?, email = ?, ic_no = ?, phone = ?, department = ?, notify_email = ?, updated_at = NOW() WHERE id = ?');
    $organization_value = $user_type === 'uthm_staff' ? $organization : null;
    $stmt->bind_param('sssssii', $name, $email, $ic_no, $phone, $organization_value, $notify_email, $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['ok' => false, 'errors' => ['Unable to update profile.']]);
        exit;
    }
    $stmt->close();

    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
