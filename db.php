<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';

$preferred_databases = [
    'lab_booking_system',
    'lab_ppmkcp_db'
];

$mysqli = null;
$DB_NAME = '';
mysqli_report(MYSQLI_REPORT_OFF);
{
    $best_connection = null;
    $best_database = '';
    $best_score = -1;
    $required_tables = [
        'lab_bookings',
        'lab_reservations',
        'labs',
        'clusters'
    ];

    foreach ($preferred_databases as $candidate_db) {
        $connection = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $candidate_db);
        if (!$connection) {
            continue;
        }

        @mysqli_set_charset($connection, 'utf8mb4');

        $tables_ok = true;
        foreach ($required_tables as $table_name) {
            $check_result = @mysqli_query($connection, "SHOW TABLES LIKE '" . mysqli_real_escape_string($connection, $table_name) . "'");
            if (!$check_result || mysqli_num_rows($check_result) === 0) {
                $tables_ok = false;
                if ($check_result) {
                    mysqli_free_result($check_result);
                }
                break;
            }
            mysqli_free_result($check_result);
        }

        if (!$tables_ok) {
            mysqli_close($connection);
            continue;
        }

        $score = 0;
        $count_result = @mysqli_query($connection, 'SELECT COUNT(*) AS total FROM lab_bookings');
        if ($count_result) {
            $count_row = mysqli_fetch_assoc($count_result);
            $score = (int) ($count_row['total'] ?? 0);
            mysqli_free_result($count_result);
        }

        if ($score > $best_score) {
            if ($best_connection) {
                mysqli_close($best_connection);
            }
            $best_connection = $connection;
            $best_database = $candidate_db;
            $best_score = $score;
        } else {
            mysqli_close($connection);
        }
    }

    if ($best_connection) {
        $mysqli = $best_connection;
        $DB_NAME = $best_database;
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$mysqli) {
    die('Database connection failed.');
}

mysqli_set_charset($mysqli, 'utf8mb4');
