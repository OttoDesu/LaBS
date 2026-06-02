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
    notify_email TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_codes (
    reset_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_password_reset_email (email, used_at, expires_at),
    KEY idx_password_reset_user (user_id),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
