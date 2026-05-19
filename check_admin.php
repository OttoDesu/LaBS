<?php
require_once __DIR__ . '/init.php';

$email = 'admin@uthm.edu.my';
$password = 'Admin123';

$stmt = $mysqli->prepare('SELECT id, name, email, password, user_type, cluster_id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo 'Admin user not found in users table.';
    exit;
}

echo 'User found. user_type=' . $user['user_type'] . ' cluster_id=' . ($user['cluster_id'] ?? 'NULL') . ' ';
echo 'password_verify=' . (password_verify($password, $user['password']) ? 'true' : 'false');
