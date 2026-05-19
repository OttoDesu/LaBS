ALTER TABLE lab_reservations
    ADD COLUMN booking_purpose ENUM('lab','class') NOT NULL DEFAULT 'lab' AFTER phone,
    ADD COLUMN class_course_code VARCHAR(8) DEFAULT NULL AFTER booking_purpose,
    ADD COLUMN class_subject_name VARCHAR(255) DEFAULT NULL AFTER class_course_code,
    ADD COLUMN class_section VARCHAR(50) DEFAULT NULL AFTER class_subject_name;
