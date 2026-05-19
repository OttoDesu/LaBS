<?php
require_once __DIR__ . '/init.php';

// Ensure enum supports admin roles and cluster association.
$mysqli->query("ALTER TABLE users MODIFY user_type ENUM('public','uthm_staff','uthm_student','super_admin','cluster_admin','lab_supervisor','admin') NOT NULL DEFAULT 'public'");
$mysqli->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS cluster_id BIGINT(20) UNSIGNED DEFAULT NULL");
$mysqli->query('
    CREATE TABLE IF NOT EXISTS lab_supervisor_labs (
        lab_supervisor_lab_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        lab_id BIGINT(20) UNSIGNED NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_lab_supervisor_scope (user_id, lab_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
');

function upsert_user($mysqli, $name, $email, $password, $user_type, $student_staff_id = null, $cluster_id = null) {
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('UPDATE users SET name = ?, password = ?, user_type = ?, student_staff_id = ?, cluster_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->bind_param('ssssii', $name, $hashed, $user_type, $student_staff_id, $cluster_id, $existing['id']);
        $stmt->execute();
        $stmt->close();
        return $existing['id'];
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare('INSERT INTO users (name, email, password, user_type, student_staff_id, cluster_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('sssssi', $name, $email, $hashed, $user_type, $student_staff_id, $cluster_id);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    return $new_id;
}

$cluster_map = [];
$cluster_stmt = $mysqli->prepare('SELECT cluster_id, cluster_name FROM clusters');
$cluster_stmt->execute();
$cluster_result = $cluster_stmt->get_result();
while ($row = $cluster_result->fetch_assoc()) {
    $cluster_map[$row['cluster_name']] = (int) $row['cluster_id'];
}
$cluster_stmt->close();

$admin_id = upsert_user($mysqli, 'Admin', 'admin@uthm.edu.my', 'Admin123', 'super_admin');
$student_id = upsert_user($mysqli, 'CI230027', 'ci230027@student.uthm.edu.my', 'ci230027', 'uthm_student', 'ci230027', $cluster_map['Kluster Sains Gunaan & Teknologi'] ?? null);

$cluster_admins = [
    'Kluster Teknologi Kejuruteraan Elektrik & Multimedia' => 'Pengurus Kluster Teknologi Kejuruteraan Elektrik & Multimedia',
    'Kluster Teknologi Kejuruteraan Awam & Kimia' => 'Pengurus Kluster Teknologi Kejuruteraan Awam & Kimia',
    'Kluster Teknologi Kejuruteraan Mekanikal & Pengangkutan' => 'Pengurus Kluster Teknologi Kejuruteraan Mekanikal & Pengangkutan',
    'Kluster Sains Gunaan & Teknologi' => 'Pengurus Kluster Sains Gunaan & Teknologi'
];
foreach ($cluster_admins as $cluster_name => $admin_name) {
    $cluster_id = $cluster_map[$cluster_name] ?? null;
    if (!$cluster_id) {
        continue;
    }
    $email_safe = strtolower($admin_name);
    $email_safe = preg_replace('/[^a-z0-9]+/', '.', $email_safe);
    $email_safe = trim($email_safe, '.');
    $email = $email_safe . '@uthm.edu.my';
    upsert_user($mysqli, $admin_name, $email, 'Admin123', 'cluster_admin', null, $cluster_id);
}

$lab_supervisor_id = upsert_user($mysqli, 'Lab Supervisor', 'lab.supervisor@uthm.edu.my', 'Admin123', 'lab_supervisor');
$lab_stmt = $mysqli->prepare('SELECT lab_id FROM labs ORDER BY lab_id ASC LIMIT 3');
$lab_stmt->execute();
$lab_result = $lab_stmt->get_result();
while ($row = $lab_result->fetch_assoc()) {
    $stmt = $mysqli->prepare('INSERT IGNORE INTO lab_supervisor_labs (user_id, lab_id, created_at) VALUES (?, ?, NOW())');
    $stmt->bind_param('ii', $lab_supervisor_id, $row['lab_id']);
    $stmt->execute();
    $stmt->close();
}
$lab_stmt->close();

echo 'Seed complete. Admin ID: ' . $admin_id . ' Student ID: ' . $student_id;
