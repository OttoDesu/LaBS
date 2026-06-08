<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (isset($mysqli)) {
    ensure_lab_maintenance_columns($mysqli);
    ensure_class_booking_columns($mysqli);
    ensure_password_reset_table($mysqli);
    ensure_user_contact_columns($mysqli);
    ensure_user_notifications_table($mysqli);
    ensure_booking_holds_table($mysqli);
    cleanup_expired_booking_holds($mysqli);
    ensure_lab_supervisor_history($mysqli);
}
