<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (isset($mysqli)) {
    ensure_lab_maintenance_columns($mysqli);
    ensure_class_booking_columns($mysqli);
}
