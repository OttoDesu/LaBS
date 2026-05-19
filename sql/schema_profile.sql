CREATE TABLE IF NOT EXISTS users (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    ic_no VARCHAR(20) DEFAULT NULL,
    student_staff_id VARCHAR(50) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('public','uthm_student','uthm_staff','super_admin','cluster_admin','lab_supervisor','admin') NOT NULL DEFAULT 'public',
    cluster_id BIGINT(20) UNSIGNED DEFAULT NULL,
    staff_status ENUM('Yes','No') NOT NULL DEFAULT 'No',
    department VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
