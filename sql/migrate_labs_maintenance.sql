ALTER TABLE labs
    ADD COLUMN maintenance_status ENUM('available','maintenance') NOT NULL DEFAULT 'available' AFTER lab_capacity,
    ADD COLUMN maintenance_start_date DATE DEFAULT NULL AFTER maintenance_status,
    ADD COLUMN maintenance_end_date DATE DEFAULT NULL AFTER maintenance_start_date;
