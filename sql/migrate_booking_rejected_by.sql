ALTER TABLE lab_bookings
    ADD COLUMN rejected_by BIGINT(20) UNSIGNED DEFAULT NULL AFTER rejection_reason;
