CREATE TABLE clusters (
    cluster_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cluster_name VARCHAR(255) NOT NULL,
    cluster_description TEXT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE supervisors (
    supervisor_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cluster_id BIGINT(20) UNSIGNED NOT NULL,
    supervisor_name VARCHAR(255) NOT NULL,
    supervisor_email VARCHAR(255) DEFAULT NULL,
    supervisor_room_no VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cluster_supervisor (cluster_id, supervisor_name),
    CONSTRAINT fk_supervisors_cluster FOREIGN KEY (cluster_id) REFERENCES clusters(cluster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE labs (
    lab_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cluster_id BIGINT(20) UNSIGNED NOT NULL,
    supervisor_id BIGINT(20) UNSIGNED DEFAULT NULL,
    lab_name VARCHAR(255) NOT NULL,
    lab_description TEXT,
    lab_capacity INT,
    maintenance_status ENUM('available','maintenance') NOT NULL DEFAULT 'available',
    maintenance_start_date DATE DEFAULT NULL,
    maintenance_end_date DATE DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_labs_cluster FOREIGN KEY (cluster_id) REFERENCES clusters(cluster_id),
    CONSTRAINT fk_labs_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors(supervisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lab_supervisor_labs (
    lab_supervisor_lab_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    lab_id BIGINT(20) UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_lab_supervisor_scope (user_id, lab_id),
    CONSTRAINT fk_lab_supervisor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_lab_supervisor_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lab_bookings (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    lab_id BIGINT(20) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    time_slot VARCHAR(20) NOT NULL,
    status ENUM('Approved','Cancelled','Rejected') NOT NULL DEFAULT 'Approved',
    rejection_reason TEXT,
    cancellation_reason TEXT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_booking_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id),
    UNIQUE KEY uniq_lab_slot (lab_id, booking_date, time_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lab_reservations (
    reservation_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT(20) UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    activity_details TEXT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    ic_no VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    booking_purpose ENUM('lab','class') NOT NULL DEFAULT 'lab',
    class_course_code VARCHAR(8) DEFAULT NULL,
    class_subject_name VARCHAR(255) DEFAULT NULL,
    class_section VARCHAR(50) DEFAULT NULL,
    booking_mode ENUM('slot','group') NOT NULL DEFAULT 'slot',
    group_booking_type ENUM('lecture','lab') DEFAULT NULL,
    group_weeks_count INT UNSIGNED DEFAULT NULL,
    group_reference_date DATE DEFAULT NULL,
    group_start_date DATE DEFAULT NULL,
    group_end_date DATE DEFAULT NULL,
    group_midsem_start_date DATE DEFAULT NULL,
    group_midsem_end_date DATE DEFAULT NULL,
    group_sessions_json LONGTEXT DEFAULT NULL,
    group_booking_key VARCHAR(64) DEFAULT NULL,
    affiliation_type ENUM('uthm','public') NOT NULL,
    cluster_id BIGINT(20) UNSIGNED DEFAULT NULL,
    public_agency_type ENUM('private','government') DEFAULT NULL,
    public_sector VARCHAR(255) DEFAULT NULL,
    government_info VARCHAR(255) DEFAULT NULL,
    include_equipment TINYINT(1) NOT NULL DEFAULT 0,
    include_chemicals TINYINT(1) NOT NULL DEFAULT 0,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_student TINYINT(1) NOT NULL DEFAULT 0,
    supervisor_name VARCHAR(255) DEFAULT NULL,
    supervisor_matric VARCHAR(100) DEFAULT NULL,
    supervisor_phone VARCHAR(30) DEFAULT NULL,
    supervisor_email VARCHAR(255) DEFAULT NULL,
    document_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_booking FOREIGN KEY (booking_id) REFERENCES lab_bookings(id),
    CONSTRAINT fk_reservation_cluster FOREIGN KEY (cluster_id) REFERENCES clusters(cluster_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE booking_holds (
    hold_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hold_token VARCHAR(64) NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    lab_id BIGINT(20) UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    time_slot VARCHAR(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    reminder_sent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_booking_hold_slot (lab_id, booking_date, time_slot),
    KEY idx_booking_hold_token (hold_token),
    KEY idx_booking_hold_user (user_id),
    KEY idx_booking_hold_expiry (expires_at),
    CONSTRAINT fk_booking_holds_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_holds_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_notifications (
    notification_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    notification_type VARCHAR(32) NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_notifications_user (user_id, is_read, created_at),
    CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_equipment (
    equipment_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT(20) UNSIGNED NOT NULL,
    equipment_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_equipment_reservation FOREIGN KEY (reservation_id) REFERENCES lab_reservations(reservation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservation_chemicals (
    chemical_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reservation_id BIGINT(20) UNSIGNED NOT NULL,
    chemical_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    ppe_required TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chemicals_reservation FOREIGN KEY (reservation_id) REFERENCES lab_reservations(reservation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE assets (
    asset_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lab_id BIGINT(20) UNSIGNED NOT NULL,
    asset_name VARCHAR(255) NOT NULL,
    asset_status VARCHAR(50) NOT NULL,
    asset_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_assets_lab FOREIGN KEY (lab_id) REFERENCES labs(lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
