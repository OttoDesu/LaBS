<?php
require_once __DIR__ . '/init.php';
require_login();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? 'public';
$is_admin = is_admin_user($user_type);

$time_slots = [
    '09:00-10:00',
    '10:00-11:00',
    '11:00-12:00',
    '12:00-13:00',
    '13:00-14:00',
    '14:00-15:00',
    '15:00-16:00',
    '16:00-17:00'
];

$lab_id = (int) ($_GET['lab_id'] ?? $_POST['lab_id'] ?? 0);
$booking_date = $_GET['booking_date'] ?? $_POST['booking_date'] ?? '';

// Handle both single time_slot (from GET, old format) and multiple time_slots (from POST, new format)
$selected_time_slots = [];
$time_slots_json = $_POST['time_slots'] ?? '';

if ($time_slots_json) {
    $decoded = json_decode($time_slots_json, true);
    if (is_array($decoded) && !empty($decoded)) {
        $selected_time_slots = $decoded;
    }
} else {
    $multiple_slots = trim((string) ($_GET['time_slots'] ?? ''));
    if ($multiple_slots !== '') {
        $selected_time_slots = array_map('trim', explode(',', $multiple_slots));
    } else {
        // Fallback to single time_slot for backward compatibility
        $single_slot = $_GET['time_slot'] ?? '';
        if ($single_slot) {
            $selected_time_slots = [$single_slot];
        }
    }
}

$time_slot_order = array_flip($time_slots);
$selected_time_slots = array_values(array_unique(array_filter($selected_time_slots, static function ($slot) {
    return is_string($slot) && $slot !== '';
})));
usort($selected_time_slots, static function ($left, $right) use ($time_slot_order) {
    return ($time_slot_order[$left] ?? PHP_INT_MAX) <=> ($time_slot_order[$right] ?? PHP_INT_MAX);
});

$start_time = '';
$end_time = '';
if ($selected_time_slots) {
    $first_slot = $selected_time_slots[0];
    $last_slot = $selected_time_slots[count($selected_time_slots) - 1];
    if (strpos($first_slot, '-') !== false) {
        [$start_time] = array_map('trim', explode('-', $first_slot, 2));
    }
    if (strpos($last_slot, '-') !== false) {
        [, $end_time] = array_map('trim', explode('-', $last_slot, 2));
    }
}

if ($lab_id <= 0) {
    header('Location: booking.php');
    exit;
}

$lab = null;
$stmt = $mysqli->prepare('
    SELECT l.lab_id, l.lab_name, l.lab_description,
           l.maintenance_status, l.maintenance_start_date, l.maintenance_end_date,
           c.cluster_id, c.cluster_name
    FROM labs l
    JOIN clusters c ON c.cluster_id = l.cluster_id
    WHERE l.lab_id = ?
    LIMIT 1
');
$stmt->bind_param('i', $lab_id);
$stmt->execute();
$result = $stmt->get_result();
$lab = $result->fetch_assoc();
$stmt->close();

if (!$lab) {
    header('Location: booking.php');
    exit;
}

if (is_lab_under_maintenance($lab, $booking_date)) {
    set_flash('info', 'This lab is under maintenance on the selected date and cannot be booked.');
    header('Location: availability.php?lab_id=' . (int) $lab_id);
    exit;
}

$user = [
    'name' => '',
    'email' => '',
    'ic_no' => '',
    'phone' => '',
    'student_staff_id' => ''
];
$stmt = $mysqli->prepare('SELECT name, email, ic_no, phone, student_staff_id FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $user = $row;
}
$stmt->close();

$clusters = [];
$stmt = $mysqli->prepare('SELECT cluster_id, cluster_name FROM clusters ORDER BY cluster_name ASC');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $clusters[] = $row;
}
$stmt->close();

$errors = [];
$booking_pk = get_booking_pk_column($mysqli);
$form_values = [
    'booking_purpose' => $_POST['booking_purpose'] ?? 'lab',
    'title' => $_POST['title'] ?? '',
    'activity_details' => $_POST['activity_details'] ?? '',
    'course_code' => $_POST['course_code'] ?? '',
    'class_title' => $_POST['class_title'] ?? '',
    'class_group' => $_POST['class_group'] ?? '',
    'class_notes' => $_POST['class_notes'] ?? '',
    'full_name' => $_POST['full_name'] ?? $user['name'],
    'ic_no' => $_POST['ic_no'] ?? $user['ic_no'],
    'email' => $_POST['email'] ?? $user['email'],
    'phone' => $_POST['phone'] ?? $user['phone'],
    'affiliation_type' => $_POST['affiliation_type'] ?? ($user_type === 'public' ? 'public' : 'uthm'),
    'cluster_id' => (int) ($_POST['cluster_id'] ?? $lab['cluster_id']),
    'public_agency_type' => $_POST['public_agency_type'] ?? 'private',
    'public_sector' => $_POST['public_sector'] ?? '',
    'government_info' => $_POST['government_info'] ?? '',
    'include_equipment' => isset($_POST['include_equipment']) ? 1 : 0,
    'include_chemicals' => isset($_POST['include_chemicals']) ? 1 : 0,
    'is_student' => isset($_POST['is_student']) ? 1 : 0,
    'supervisor_name' => $_POST['supervisor_name'] ?? '',
    'supervisor_matric' => $_POST['supervisor_matric'] ?? '',
    'supervisor_phone' => $_POST['supervisor_phone'] ?? '',
    'supervisor_email' => $_POST['supervisor_email'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_names = $_POST['equipment_name'] ?? [];
    $equipment_qty = $_POST['equipment_qty'] ?? [];
    $equipment_notes = $_POST['equipment_notes'] ?? [];
    $chemical_names = $_POST['chemical_name'] ?? [];
    $chemical_qty = $_POST['chemical_qty'] ?? [];
    $chemical_ppe = $_POST['chemical_ppe'] ?? [];
    $equipment_items = [];
    $chemical_items = [];

    $booking_purpose = $form_values['booking_purpose'] === 'class' ? 'class' : 'lab';
    $title = trim($form_values['title']);
    $activity_details = trim($form_values['activity_details']);
    $course_code = trim($form_values['course_code']);
    $class_title = trim($form_values['class_title']);
    $class_group = trim($form_values['class_group']);
    $class_notes = trim($form_values['class_notes']);
    $full_name = trim($form_values['full_name']);
    $ic_no = trim($form_values['ic_no']);
    $email = trim($form_values['email']);
    $phone = trim($form_values['phone']);
    $affiliation_type = $form_values['affiliation_type'] === 'public' ? 'public' : 'uthm';
    $cluster_id = $affiliation_type === 'uthm' ? (int) $form_values['cluster_id'] : null;
    $public_agency_type = $affiliation_type === 'public' ? $form_values['public_agency_type'] : null;
    $public_sector = $affiliation_type === 'public' ? trim($form_values['public_sector']) : null;
    $government_info = $affiliation_type === 'public' ? trim($form_values['government_info']) : null;
    $include_equipment = $form_values['include_equipment'];
    $include_chemicals = $form_values['include_chemicals'];
    $is_student = $form_values['is_student'];
    $supervisor_name = trim($form_values['supervisor_name']);
    $supervisor_matric = trim($form_values['supervisor_matric']);
    $supervisor_phone = trim($form_values['supervisor_phone']);
    $supervisor_email = trim($form_values['supervisor_email']);

    if ($booking_purpose === 'class') {
        if ($course_code === '') {
            $errors[] = 'Course code is required.';
        } elseif (mb_strlen($course_code) > 8) {
            $errors[] = 'Course code must not exceed 8 characters.';
        }
        if ($class_title === '') {
            $errors[] = 'Subject name is required.';
        } elseif (str_word_count($class_title) > 50) {
            $errors[] = 'Subject name must not exceed 50 words.';
        }
        if ($class_group === '') {
            $errors[] = 'Class section number is required.';
        } elseif (!preg_match('/^\d+$/', $class_group)) {
            $errors[] = 'Class section number must contain digits only.';
        }
        $title = trim($course_code . ' - ' . $class_title, ' -');
        $activity_details = "Booking purpose: Class\nCourse code: {$course_code}\nSubject name: {$class_title}\nClass section number: {$class_group}";
        if ($class_notes !== '') {
            $activity_details .= "\nNotes: {$class_notes}";
        }
    } else {
        if ($title === '') {
            $errors[] = 'Reservation title is required.';
        }
        if ($activity_details === '') {
            $errors[] = 'Activity details are required.';
        }
    }
    if ($full_name === '') {
        $errors[] = 'Name is required.';
    }
    if ($booking_purpose === 'class') {
        if ($ic_no === '') {
            $errors[] = 'Staff ID is required.';
        }
    } elseif ($ic_no === '' || !preg_match('/^\d{12}$/', $ic_no)) {
        $errors[] = 'IC number must be 12 digits.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($phone === '' || !preg_match('/^\d{9,12}$/', $phone)) {
        $errors[] = 'Phone number must be 9 to 12 digits.';
    }

    if ($booking_purpose === 'class') {
        $affiliation_type = 'uthm';
        $cluster_id = (int) $lab['cluster_id'];
        $public_agency_type = null;
        $public_sector = null;
        $government_info = null;
        $include_equipment = 0;
        $include_chemicals = 0;
        $is_student = 0;
        $supervisor_name = null;
        $supervisor_matric = null;
        $supervisor_phone = null;
        $supervisor_email = null;
    } else {
        if ($affiliation_type === 'uthm') {
            if (!$cluster_id) {
                $errors[] = 'Please select your cluster or faculty.';
            }
            $public_agency_type = null;
            $public_sector = null;
            $government_info = null;
        } else {
            if (!in_array($public_agency_type, ['private', 'government'], true)) {
                $errors[] = 'Please select a public organization type.';
            }
            if ($public_agency_type === 'private' && $public_sector === '') {
                $errors[] = 'Please select a private sector.';
            }
            if ($public_agency_type === 'government' && $government_info === '') {
                $errors[] = 'Please provide government agency details.';
            }
            $cluster_id = null;
        }
    }

    $min_date = (new DateTime('today'))->modify('+3 days')->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
        $errors[] = 'Please select a valid booking date.';
    } elseif ($booking_date < $min_date) {
        $errors[] = 'Bookings must be made at least 3 days in advance.';
    } elseif (is_lab_under_maintenance($lab, $booking_date)) {
        $errors[] = 'This lab is under maintenance on the selected date.';
    }

    if (empty($selected_time_slots)) {
        $errors[] = 'Please select at least one time slot.';
    } else {
        foreach ($selected_time_slots as $slot) {
            if (!in_array($slot, $time_slots, true)) {
                $errors[] = 'Invalid time slot: ' . htmlspecialchars($slot);
                break;
            }
        }
    }

    if ($booking_purpose === 'lab' && $is_student) {
        if ($supervisor_name === '' || $supervisor_matric === '' || $supervisor_phone === '' || !filter_var($supervisor_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Supervisor name, matric, phone, and valid email are required for students.';
        }
    } else {
        $supervisor_name = null;
        $supervisor_matric = null;
        $supervisor_phone = null;
        $supervisor_email = null;
    }

    if ($booking_purpose === 'lab' && $include_equipment) {
        foreach ($equipment_names as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $qty = isset($equipment_qty[$index]) ? (int) $equipment_qty[$index] : 1;
            $qty = $qty > 0 ? $qty : 1;
            $notes = isset($equipment_notes[$index]) ? trim((string) $equipment_notes[$index]) : '';
            $equipment_items[] = [
                'name' => $name,
                'qty' => $qty,
                'notes' => $notes === '' ? null : $notes
            ];
        }
        if (!$equipment_items) {
            $errors[] = 'Please add at least one equipment/tool entry.';
        }
    }

    if ($booking_purpose === 'lab' && $include_chemicals) {
        foreach ($chemical_names as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $qty = isset($chemical_qty[$index]) ? (int) $chemical_qty[$index] : 1;
            $qty = $qty > 0 ? $qty : 1;
            $ppe_required = isset($chemical_ppe[$index]) ? 1 : 0;
            $chemical_items[] = [
                'name' => $name,
                'qty' => $qty,
                'ppe' => $ppe_required
            ];
        }
        if (!$chemical_items) {
            $errors[] = 'Please add at least one chemical/consumable entry.';
        }
    }

    $document_path = null;
    if ($booking_purpose === 'lab' && !empty($_FILES['document']['name'])) {
        $file_error = $_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($file_error === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                $errors[] = 'Document must be a PDF file.';
            }
        } else {
            $errors[] = 'Unable to upload document.';
        }
    }

    if (!$errors) {
        $stmt = $mysqli->prepare("SELECT {$booking_pk} FROM lab_bookings WHERE lab_id = ? AND booking_date = ? AND time_slot = ? AND status = 'Approved' LIMIT 1");
        foreach ($selected_time_slots as $selected_slot) {
            $stmt->bind_param('iss', $lab_id, $booking_date, $selected_slot);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_assoc()) {
                $errors[] = 'Slot ' . $selected_slot . ' is already booked.';
                break;
            }
        }
        $stmt->close();
    }

    if (!$errors) {
        $mysqli->begin_transaction();
        try {
            if (!empty($_FILES['document']['name'])) {
                $upload_dir = __DIR__ . '/uploads/reservations';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }
                $safe_name = 'reservation_' . $user_id . '_' . time() . '.pdf';
                $target = $upload_dir . '/' . $safe_name;
                if (!move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
                    throw new Exception('Document upload failed.');
                }
                $document_path = 'uploads/reservations/' . $safe_name;
            }

            $booking_stmt = $mysqli->prepare('INSERT INTO lab_bookings (user_id, lab_id, booking_date, time_slot, status, created_at, updated_at) VALUES (?, ?, ?, ?, "Approved", NOW(), NOW())');
            $reservation_stmt = $mysqli->prepare('
                INSERT INTO lab_reservations (
                    booking_id, title, activity_details, full_name, ic_no, email, phone,
                    booking_purpose, class_course_code, class_subject_name, class_section,
                    affiliation_type, cluster_id, public_agency_type, public_sector, government_info,
                    include_equipment, include_chemicals, booking_date, start_time, end_time, is_student,
                    supervisor_name, supervisor_matric, supervisor_phone, supervisor_email, document_path,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ');
            $equipment_stmt = null;
            $chemical_stmt = null;
            if ($include_equipment && $equipment_items) {
                $equipment_stmt = $mysqli->prepare('
                    INSERT INTO reservation_equipment (reservation_id, equipment_name, quantity, notes, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
            }
            if ($include_chemicals && $chemical_items) {
                $chemical_stmt = $mysqli->prepare('
                    INSERT INTO reservation_chemicals (reservation_id, chemical_name, quantity, ppe_required, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
            }

            foreach ($selected_time_slots as $selected_slot) {
                [$slot_start_time, $slot_end_time] = array_map('trim', explode('-', $selected_slot, 2));

                $booking_stmt->bind_param('iiss', $user_id, $lab_id, $booking_date, $selected_slot);
                $booking_stmt->execute();
                $booking_id = (int) $booking_stmt->insert_id;

                $reservation_stmt->bind_param(
                    'isssssssssssisssiisssisssss',
                    $booking_id,
                    $title,
                    $activity_details,
                    $full_name,
                    $ic_no,
                    $email,
                    $phone,
                    $booking_purpose,
                    $course_code,
                    $class_title,
                    $class_group,
                    $affiliation_type,
                    $cluster_id,
                    $public_agency_type,
                    $public_sector,
                    $government_info,
                    $include_equipment,
                    $include_chemicals,
                    $booking_date,
                    $slot_start_time,
                    $slot_end_time,
                    $is_student,
                    $supervisor_name,
                    $supervisor_matric,
                    $supervisor_phone,
                    $supervisor_email,
                    $document_path
                );
                $reservation_stmt->execute();
                $reservation_id = (int) $reservation_stmt->insert_id;

                if ($equipment_stmt) {
                    foreach ($equipment_items as $item) {
                        $equipment_stmt->bind_param('isis', $reservation_id, $item['name'], $item['qty'], $item['notes']);
                        $equipment_stmt->execute();
                    }
                }

                if ($chemical_stmt) {
                    foreach ($chemical_items as $item) {
                        $chemical_stmt->bind_param('isii', $reservation_id, $item['name'], $item['qty'], $item['ppe']);
                        $chemical_stmt->execute();
                    }
                }
            }

            $booking_stmt->close();
            $reservation_stmt->close();
            if ($equipment_stmt) {
                $equipment_stmt->close();
            }
            if ($chemical_stmt) {
                $chemical_stmt->close();
            }

            $mysqli->commit();
            $success_prefix = $booking_purpose === 'class' ? 'Class booking submitted successfully' : 'Reservation submitted successfully';
            set_flash('info', $success_prefix . ' for ' . count($selected_time_slots) . ' slot' . (count($selected_time_slots) === 1 ? '' : 's') . '.');
            header('Location: dashboard.php');
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = $e->getMessage() ?: 'Unable to submit reservation.';
        }
    }
}

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$layout_path = __DIR__ . '/templates/layouts/public.php';
$is_lab_supervisor = is_lab_supervisor($user_type);
if ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
} elseif ($is_admin) {
    $layout_path = __DIR__ . '/templates/layouts/admin.php';
} elseif ($user_type === 'uthm_staff') {
    $layout_path = __DIR__ . '/templates/layouts/uthm_staff.php';
} elseif ($user_type === 'uthm_student') {
    $layout_path = __DIR__ . '/templates/layouts/uthm_student.php';
}
$layout = require $layout_path;
$active = 'booking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Reservation Form</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body data-login-url="index.php">
    <div class="app">
        <?php include __DIR__ . '/templates/layouts/sidebar.php'; ?>

        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-button" id="toggle-sidebar" aria-label="Toggle sidebar">
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                    <div class="search">
                        <span class="search-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    fill-rule="evenodd"
                                    clip-rule="evenodd"
                                    d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>
                        <input type="text" id="global-search" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-button" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                fill-rule="evenodd"
                                clip-rule="evenodd"
                                d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
                                fill="currentColor"
                            />
                        </svg>
                    </button>
                    <div class="user-chip" id="user-menu-toggle" role="button" tabindex="0">
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($user_payload['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_payload['email']); ?></div>
                        </div>
                        <span class="chevron"><svg class="chevron-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    </div>
                    <div class="user-menu" id="user-menu">
                        <div class="user-menu-header">
                            <div class="user-name"><?php echo htmlspecialchars($user_payload['name']); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($user_payload['email']); ?></div>
                        </div>
                        <a class="menu-item" href="profile.php"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="menu-icon"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z" fill="currentColor"/></svg>Edit Profile</a>
                        <button class="menu-item" type="button"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="menu-icon"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.5 12C3.5 7.30558 7.30558 3.5 12 3.5C16.6944 3.5 20.5 7.30558 20.5 12C20.5 16.6944 16.6944 20.5 12 20.5C7.30558 20.5 3.5 16.6944 3.5 12ZM12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM11.0991 7.52507C11.0991 8.02213 11.5021 8.42507 11.9991 8.42507H12.0001C12.4972 8.42507 12.9001 8.02213 12.9001 7.52507C12.9001 7.02802 12.4972 6.62507 12.0001 6.62507H11.9991C11.5021 6.62507 11.0991 7.02802 11.0991 7.52507ZM12.0001 17.3714C11.5859 17.3714 11.2501 17.0356 11.2501 16.6214V10.9449C11.2501 10.5307 11.5859 10.1949 12.0001 10.1949C12.4143 10.1949 12.7501 10.5307 12.7501 10.9449V16.6214C12.7501 17.0356 12.4143 17.3714 12.0001 17.3714Z" fill="currentColor"/></svg>Support</button>
                        <form method="POST" action="logout.php">
                            <button class="menu-item danger" type="submit"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="menu-icon"><path fill-rule="evenodd" clip-rule="evenodd" d="M15.1007 19.247C14.6865 19.247 14.3507 18.9112 14.3507 18.497L14.3507 14.245H12.8507V18.497C12.8507 19.7396 13.8581 20.747 15.1007 20.747H18.5007C19.7434 20.747 20.7507 19.7396 20.7507 18.497L20.7507 5.49609C20.7507 4.25345 19.7433 3.24609 18.5007 3.24609H15.1007C13.8581 3.24609 12.8507 4.25345 12.8507 5.49609V9.74501L14.3507 9.74501V5.49609C14.3507 5.08188 14.6865 4.74609 15.1007 4.74609L18.5007 4.74609C18.9149 4.74609 19.2507 5.08188 19.2507 5.49609L19.2507 18.497C19.2507 18.9112 18.9149 19.247 18.5007 19.247H15.1007ZM3.25073 11.9984C3.25073 12.2144 3.34204 12.4091 3.48817 12.546L8.09483 17.1556C8.38763 17.4485 8.86251 17.4487 9.15549 17.1559C9.44848 16.8631 9.44863 16.3882 9.15583 16.0952L5.81116 12.7484L16.0007 12.7484C16.4149 12.7484 16.7507 12.4127 16.7507 11.9984C16.7507 11.5842 16.4149 11.2484 16.0007 11.2484L5.81528 11.2484L9.15585 7.90554C9.44864 7.61255 9.44847 7.13767 9.15547 6.84488C8.86248 6.55209 8.3876 6.55226 8.09481 6.84525L3.52309 11.4202C3.35673 11.5577 3.25073 11.7657 3.25073 11.9984Z" fill="currentColor"/></svg>Sign Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <section class="content">
                <div class="content-header">
                    <div>
                        <h1>Lab Reservation Form</h1>
                        <p>Complete the details to submit your reservation.</p>
                        <?php if (($lab['maintenance_status'] ?? 'available') === 'maintenance'): ?>
                            <p class="muted-text">Maintenance window: <?php echo htmlspecialchars(get_lab_maintenance_period_label($lab['maintenance_start_date'] ?? null, $lab['maintenance_end_date'] ?? null)); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="breadcrumb">Home / Booking / Reservation</div>
                </div>

                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="section-stack">
                    <div class="card">
                        <div class="banner">
                            <div>
                                <p class="badge">Lab Reservation Form</p>
                                <h2><?php echo htmlspecialchars($lab['lab_name']); ?></h2>
                                <p>
                                    Booking date: <?php echo htmlspecialchars($booking_date ?: 'Not selected'); ?>
                                    <span class="muted-text">Lead time: 3 days</span>
                                </p>
                            </div>
                            <div class="banner-links">
                                <a class="btn ghost" href="availability.php?lab_id=<?php echo (int) $lab_id; ?>">Back to Lab</a>
                            </div>
                        </div>
                    </div>

                    <form class="card reservation-form" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="lab_id" value="<?php echo (int) $lab_id; ?>">
                        <input type="hidden" name="booking_date" value="<?php echo htmlspecialchars($booking_date); ?>">
                        <input type="hidden" name="time_slots" value="<?php echo htmlspecialchars(json_encode($selected_time_slots)); ?>">
                        <input type="hidden" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>">
                        <input type="hidden" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>">

                        <div class="form-section" id="booking-purpose-section">
                            <div class="section-header">
                                <div>
                                    <h3>Booking Purpose</h3>
                                    <p class="muted-text">Choose the booking purpose. The form below will switch automatically.</p>
                                </div>
                            </div>
                            <div class="purpose-toggle-row">
                                <div class="toggle-group purpose-toggle-group">
                                    <label class="toggle-option">
                                        <input type="radio" name="booking_purpose" value="lab" <?php echo $form_values['booking_purpose'] !== 'class' ? 'checked' : ''; ?>>
                                        <span>Book Lab</span>
                                    </label>
                                    <label class="toggle-option">
                                        <input type="radio" name="booking_purpose" value="class" <?php echo $form_values['booking_purpose'] === 'class' ? 'checked' : ''; ?>>
                                        <span>Book Class</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="lab-details-section">
                            <div class="section-header">
                                <div>
                                    <h3>Reservation Details</h3>
                                    <p class="muted-text">Tell us about your booking.</p>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div>
                                    <label for="title">Title *</label>
                                    <input id="title" name="title" type="text" value="<?php echo htmlspecialchars($form_values['title']); ?>" placeholder="Enter reservation title" required>
                                </div>
                                <div>
                                    <label for="activity-details">Activity details *</label>
                                    <textarea id="activity-details" name="activity_details" rows="3" placeholder="Describe your experiment or activity" required><?php echo htmlspecialchars($form_values['activity_details']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="class-details-section">
                            <div class="section-header">
                                <div>
                                    <h3>Class Details</h3>
                                    <p class="muted-text">Simple schedule details for teaching sessions.</p>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label for="course-code">Course Code *</label>
                                    <input id="course-code" name="course_code" type="text" maxlength="8" value="<?php echo htmlspecialchars($form_values['course_code']); ?>" placeholder="Enter course code">
                                </div>
                                <div>
                                    <label for="class-title">Subject Name *</label>
                                    <input id="class-title" name="class_title" type="text" value="<?php echo htmlspecialchars($form_values['class_title']); ?>" placeholder="Enter subject name">
                                </div>
                                <div>
                                    <label for="class-group">Class Section Number *</label>
                                    <input id="class-group" name="class_group" type="text" inputmode="numeric" value="<?php echo htmlspecialchars($form_values['class_group']); ?>" placeholder="Enter section number">
                                </div>
                                <div>
                                    <label for="class-notes">Notes</label>
                                    <textarea id="class-notes" name="class_notes" rows="3" placeholder="Optional notes"><?php echo htmlspecialchars($form_values['class_notes']); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="contact-section">
                            <div class="section-header">
                                <div>
                                    <h3>Contact Information</h3>
                                    <p class="muted-text">Auto-filled from your profile when available.</p>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label for="full-name">Name *</label>
                                    <input id="full-name" name="full_name" type="text" value="<?php echo htmlspecialchars($form_values['full_name']); ?>" required>
                                </div>
                                <div>
                                    <label for="ic-no" id="identity-label">IC *</label>
                                    <input
                                        id="ic-no"
                                        name="ic_no"
                                        type="text"
                                        maxlength="20"
                                        value="<?php echo htmlspecialchars($form_values['ic_no']); ?>"
                                        data-default-ic="<?php echo htmlspecialchars($user['ic_no'] ?? ''); ?>"
                                        data-default-staff-id="<?php echo htmlspecialchars($user['student_staff_id'] ?? ''); ?>"
                                        placeholder="Enter IC number"
                                        required
                                    >
                                </div>
                                <div>
                                    <label for="email">Email *</label>
                                    <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($form_values['email']); ?>" required>
                                </div>
                                <div>
                                    <label for="phone">Phone *</label>
                                    <input id="phone" name="phone" type="text" maxlength="12" value="<?php echo htmlspecialchars($form_values['phone']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="affiliation-section">
                            <div class="section-header">
                                <div>
                                    <h3>Faculty / Organization *</h3>
                                    <p class="muted-text">Choose affiliation type to see relevant options.</p>
                                </div>
                                <div class="toggle-group">
                                    <label class="toggle-option">
                                        <input type="radio" name="affiliation_type" value="uthm" <?php echo $form_values['affiliation_type'] === 'uthm' ? 'checked' : ''; ?>>
                                        <span>UTHM</span>
                                    </label>
                                    <label class="toggle-option">
                                        <input type="radio" name="affiliation_type" value="public" <?php echo $form_values['affiliation_type'] === 'public' ? 'checked' : ''; ?>>
                                        <span>Public</span>
                                    </label>
                                </div>
                            </div>

                            <div id="uthm-fields" class="form-row">
                                <div>
                                    <label for="cluster-id">Cluster / Faculty *</label>
                                    <select id="cluster-id" name="cluster_id">
                                        <option value="">Select cluster or faculty</option>
                                        <?php foreach ($clusters as $cluster): ?>
                                            <option value="<?php echo (int) $cluster['cluster_id']; ?>" <?php echo (int) $form_values['cluster_id'] === (int) $cluster['cluster_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cluster['cluster_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="public-fields" class="form-row">
                                <div>
                                    <label>Public user type *</label>
                                    <div class="toggle-group">
                                        <label class="toggle-option">
                                            <input type="radio" name="public_agency_type" value="private" <?php echo $form_values['public_agency_type'] === 'private' ? 'checked' : ''; ?>>
                                            <span>Private agency</span>
                                        </label>
                                        <label class="toggle-option">
                                            <input type="radio" name="public_agency_type" value="government" <?php echo $form_values['public_agency_type'] === 'government' ? 'checked' : ''; ?>>
                                            <span>Government agency</span>
                                        </label>
                                    </div>
                                </div>
                                <div id="public-sector-field">
                                    <label for="public-sector">Industry / Sector *</label>
                                    <select id="public-sector" name="public_sector">
                                        <option value="">Select sector</option>
                                        <option value="Applied Science" <?php echo $form_values['public_sector'] === 'Applied Science' ? 'selected' : ''; ?>>Applied Science</option>
                                        <option value="Chemical Engineering" <?php echo $form_values['public_sector'] === 'Chemical Engineering' ? 'selected' : ''; ?>>Chemical Engineering</option>
                                        <option value="Civil Engineering" <?php echo $form_values['public_sector'] === 'Civil Engineering' ? 'selected' : ''; ?>>Civil Engineering</option>
                                        <option value="Electrical Engineering" <?php echo $form_values['public_sector'] === 'Electrical Engineering' ? 'selected' : ''; ?>>Electrical Engineering</option>
                                        <option value="Environmental Technology" <?php echo $form_values['public_sector'] === 'Environmental Technology' ? 'selected' : ''; ?>>Environmental Technology</option>
                                        <option value="Food and Biotechnology" <?php echo $form_values['public_sector'] === 'Food and Biotechnology' ? 'selected' : ''; ?>>Food and Biotechnology</option>
                                        <option value="Mechanical Engineering" <?php echo $form_values['public_sector'] === 'Mechanical Engineering' ? 'selected' : ''; ?>>Mechanical Engineering</option>
                                        <option value="Telecommunication" <?php echo $form_values['public_sector'] === 'Telecommunication' ? 'selected' : ''; ?>>Telecommunication</option>
                                        <option value="Transportation" <?php echo $form_values['public_sector'] === 'Transportation' ? 'selected' : ''; ?>>Transportation</option>
                                    </select>
                                </div>
                                <div id="government-info-field">
                                    <label for="government-info">Government agency info *</label>
                                    <input id="government-info" name="government_info" type="text" value="<?php echo htmlspecialchars($form_values['government_info']); ?>" placeholder="Department or agency name">
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="lab-requirements-section">
                            <div class="section-header">
                                <div>
                                    <h3>Schedule & Requirements</h3>
                                    <p class="muted-text">Confirm the selected date and time.</p>
                                </div>
                            </div>
                            <div class="form-row checkbox-row">
                                <label class="checkbox">
                                    <input type="checkbox" name="include_equipment" <?php echo $form_values['include_equipment'] ? 'checked' : ''; ?>>
                                    <span>Include equipment / tools</span>
                                </label>
                                <label class="checkbox">
                                    <input type="checkbox" name="include_chemicals" <?php echo $form_values['include_chemicals'] ? 'checked' : ''; ?>>
                                    <span>Include chemicals / consumables</span>
                                </label>
                                <label class="checkbox">
                                    <input type="checkbox" name="is_student" <?php echo $form_values['is_student'] ? 'checked' : ''; ?>>
                                    <span>Student (check if you are a student)</span>
                                </label>
                            </div>
                            <div id="equipment-section" class="inline-card">
                                <div class="inline-card-header">
                                    <div>
                                        <h4>Equipment / tools to be used</h4>
                                        <p class="muted-text">Add the tools needed for this booking.</p>
                                    </div>
                                    <button class="btn ghost small" type="button" id="add-equipment">+ Add tool</button>
                                </div>
                                <div class="inline-card-body" id="equipment-list"></div>
                            </div>
                            <div id="chemicals-section" class="inline-card">
                                <div class="inline-card-header">
                                    <div>
                                        <h4>Chemicals / consumables</h4>
                                        <p class="muted-text">List any chemicals or consumables required.</p>
                                    </div>
                                    <button class="btn ghost small" type="button" id="add-chemical">+ Add chemical</button>
                                </div>
                                <div class="inline-card-body" id="chemicals-list"></div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label>Date</label>
                                    <input type="text" value="<?php echo htmlspecialchars($booking_date ?: 'Not selected'); ?>" readonly>
                                </div>
                                <div>
                                    <label>Selected slots *</label>
                                    <input type="text" value="<?php echo htmlspecialchars($selected_time_slots ? implode(', ', $selected_time_slots) : 'Not selected'); ?>" readonly>
                                </div>
                                <div>
                                    <label>Start time *</label>
                                    <input type="text" value="<?php echo htmlspecialchars($start_time ?: 'Not selected'); ?>" readonly>
                                </div>
                                <div>
                                    <label>End time *</label>
                                    <input type="text" value="<?php echo htmlspecialchars($end_time ?: 'Not selected'); ?>" readonly>
                                    <p class="muted-text">Date and time are locked from availability selection.</p>
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="supervisor-section">
                            <div class="section-header">
                                <div>
                                    <h3>Supervisor Information *</h3>
                                    <p class="muted-text">Required for student bookings only.</p>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label for="supervisor-name">Supervisor name *</label>
                                    <input id="supervisor-name" name="supervisor_name" type="text" value="<?php echo htmlspecialchars($form_values['supervisor_name']); ?>">
                                </div>
                                <div>
                                    <label for="supervisor-matric">Supervisor ID *</label>
                                    <input id="supervisor-matric" name="supervisor_matric" type="text" value="<?php echo htmlspecialchars($form_values['supervisor_matric']); ?>">
                                </div>
                                <div>
                                    <label for="supervisor-phone">Supervisor phone *</label>
                                    <input id="supervisor-phone" name="supervisor_phone" type="text" value="<?php echo htmlspecialchars($form_values['supervisor_phone']); ?>">
                                </div>
                                <div>
                                    <label for="supervisor-email">Supervisor email *</label>
                                    <input id="supervisor-email" name="supervisor_email" type="email" value="<?php echo htmlspecialchars($form_values['supervisor_email']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="document-section">
                            <div class="section-header">
                                <div>
                                    <h3>Supporting Document</h3>
                                    <p class="muted-text">Optional PDF document.</p>
                                </div>
                            </div>
                            <input id="document" name="document" type="file" accept="application/pdf">
                        </div>

                        <div class="form-section" id="class-schedule-section">
                            <div class="section-header">
                                <div>
                                    <h3>Class Schedule Summary</h3>
                                    <p class="muted-text">These values come from the availability selection.</p>
                                </div>
                            </div>
                            <div class="form-row">
                                <div>
                                    <label>Date</label>
                                    <input type="text" value="<?php echo htmlspecialchars($booking_date ?: 'Not selected'); ?>" readonly>
                                </div>
                                <div>
                                    <label>Selected slots *</label>
                                    <input type="text" value="<?php echo htmlspecialchars($selected_time_slots ? implode(', ', $selected_time_slots) : 'Not selected'); ?>" readonly>
                                </div>
                                <div>
                                    <label>Start time *</label>
                                    <input type="text" value="<?php echo htmlspecialchars($start_time ?: 'Not selected'); ?>" readonly>
                                </div>
                                <div>
                                    <label>End time *</label>
                                    <input type="text" value="<?php echo htmlspecialchars($end_time ?: 'Not selected'); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn ghost" type="button" onclick="window.history.back()">Cancel</button>
                            <button class="btn primary" id="reservation-submit" type="submit">Submit Reservation</button>
                        </div>
                    </form>
                </div>

                <footer class="footer">Ac Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
    </script>
    <script src="assets/app.js"></script>
    <script>
        (function () {
            var bookingPurposeRadios = document.querySelectorAll('input[name="booking_purpose"]');
            var affiliationRadios = document.querySelectorAll('input[name="affiliation_type"]');
            var publicRadios = document.querySelectorAll('input[name="public_agency_type"]');
            var labDetailsSection = document.getElementById('lab-details-section');
            var classDetailsSection = document.getElementById('class-details-section');
            var affiliationSection = document.getElementById('affiliation-section');
            var labRequirementsSection = document.getElementById('lab-requirements-section');
            var classScheduleSection = document.getElementById('class-schedule-section');
            var documentSection = document.getElementById('document-section');
            var uthmFields = document.getElementById('uthm-fields');
            var publicFields = document.getElementById('public-fields');
            var publicSector = document.getElementById('public-sector-field');
            var governmentInfo = document.getElementById('government-info-field');
            var studentToggle = document.querySelector('input[name="is_student"]');
            var supervisorSection = document.getElementById('supervisor-section');
            var supervisorFields = [
                document.getElementById('supervisor-name'),
                document.getElementById('supervisor-matric'),
                document.getElementById('supervisor-phone'),
                document.getElementById('supervisor-email')
            ];
            var identityLabel = document.getElementById('identity-label');
            var identityInput = document.getElementById('ic-no');
            var titleField = document.getElementById('title');
            var activityDetailsField = document.getElementById('activity-details');
            var courseCodeField = document.getElementById('course-code');
            var classTitleField = document.getElementById('class-title');
            var classGroupField = document.getElementById('class-group');
            var equipmentToggle = document.querySelector('input[name="include_equipment"]');
            var chemicalsToggle = document.querySelector('input[name="include_chemicals"]');
            var equipmentSection = document.getElementById('equipment-section');
            var chemicalsSection = document.getElementById('chemicals-section');
            var equipmentList = document.getElementById('equipment-list');
            var chemicalsList = document.getElementById('chemicals-list');
            var addEquipment = document.getElementById('add-equipment');
            var addChemical = document.getElementById('add-chemical');
            var submitButton = document.getElementById('reservation-submit');

            function getBookingPurpose() {
                var selected = document.querySelector('input[name="booking_purpose"]:checked');
                return selected ? selected.value : 'lab';
            }

            function toggleBookingPurpose() {
                var purpose = getBookingPurpose();
                var isClass = purpose === 'class';

                if (labDetailsSection) {
                    labDetailsSection.style.display = isClass ? 'none' : 'block';
                }
                if (classDetailsSection) {
                    classDetailsSection.style.display = isClass ? 'block' : 'none';
                }
                if (affiliationSection) {
                    affiliationSection.style.display = isClass ? 'none' : 'block';
                }
                if (labRequirementsSection) {
                    labRequirementsSection.style.display = isClass ? 'none' : 'block';
                }
                if (supervisorSection) {
                    supervisorSection.style.display = isClass ? 'none' : (studentToggle && studentToggle.checked ? 'grid' : 'none');
                }
                if (documentSection) {
                    documentSection.style.display = isClass ? 'none' : 'block';
                }
                if (classScheduleSection) {
                    classScheduleSection.style.display = isClass ? 'block' : 'none';
                }

                if (titleField) {
                    titleField.required = !isClass;
                }
                if (activityDetailsField) {
                    activityDetailsField.required = !isClass;
                }
                if (courseCodeField) {
                    courseCodeField.required = isClass;
                }
                if (classTitleField) {
                    classTitleField.required = isClass;
                }
                if (classGroupField) {
                    classGroupField.required = isClass;
                }
                if (identityLabel) {
                    identityLabel.textContent = isClass ? 'Staff ID *' : 'IC *';
                }
                if (identityInput) {
                    var defaultIc = identityInput.getAttribute('data-default-ic') || '';
                    var defaultStaffId = identityInput.getAttribute('data-default-staff-id') || '';
                    identityInput.placeholder = isClass ? 'Enter staff ID' : 'Enter IC number';
                    if (isClass) {
                        if ((identityInput.value === '' || identityInput.value === defaultIc) && defaultStaffId !== '') {
                            identityInput.value = defaultStaffId;
                        }
                    } else if (identityInput.value === defaultStaffId && defaultIc !== '') {
                        identityInput.value = defaultIc;
                    }
                }
                if (submitButton) {
                    submitButton.textContent = isClass ? 'Submit Class Booking' : 'Submit Reservation';
                }
            }

            function updateAffiliation() {
                var value = document.querySelector('input[name="affiliation_type"]:checked');
                var type = value ? value.value : 'uthm';
                if (uthmFields) {
                    uthmFields.style.display = type === 'uthm' ? 'grid' : 'none';
                }
                if (publicFields) {
                    publicFields.style.display = type === 'public' ? 'grid' : 'none';
                }
            }

            function updatePublicFields() {
                var value = document.querySelector('input[name="public_agency_type"]:checked');
                var type = value ? value.value : 'private';
                if (publicSector) {
                    publicSector.style.display = type === 'private' ? 'block' : 'none';
                }
                if (governmentInfo) {
                    governmentInfo.style.display = type === 'government' ? 'block' : 'none';
                }
            }

            function toggleSupervisor() {
                if (!studentToggle || !supervisorSection) {
                    return;
                }
                if (getBookingPurpose() === 'class') {
                    supervisorSection.style.display = 'none';
                    supervisorFields.forEach(function (field) {
                        if (field) {
                            field.required = false;
                        }
                    });
                    return;
                }
                var isStudent = studentToggle.checked;
                supervisorSection.style.display = isStudent ? 'grid' : 'none';
                supervisorFields.forEach(function (field) {
                    if (field) {
                        field.required = isStudent;
                    }
                });
            }

            function toggleInlineSection(toggle, section) {
                if (!toggle || !section) {
                    return;
                }
                section.style.display = toggle.checked ? 'grid' : 'none';
            }

            function createEquipmentRow() {
                var row = document.createElement('div');
                row.className = 'inline-row';
                row.innerHTML =
                    '<div><label>Tool name</label><input type="text" name="equipment_name[]" placeholder="e.g., Oscilloscope"></div>' +
                    '<div><label>Quantity</label><input type="number" name="equipment_qty[]" min="1" value="1"></div>' +
                    '<div><label>Notes</label><input type="text" name="equipment_notes[]" placeholder="Notes (optional)"></div>' +
                    '<button type="button" class="btn ghost small remove-row">Remove</button>';
                return row;
            }

            var chemicalIndex = 0;

            function createChemicalRow() {
                var index = chemicalIndex;
                chemicalIndex += 1;
                var row = document.createElement('div');
                row.className = 'inline-row';
                row.innerHTML =
                    '<div><label>Chemical / consumable</label><input type="text" name="chemical_name[]" placeholder="e.g., IPA"></div>' +
                    '<div><label>Quantity</label><input type="number" name="chemical_qty[]" min="1" value="1"></div>' +
                    '<label class="checkbox"><input type="checkbox" name="chemical_ppe[' + index + ']" value="1"> <span>PPE required</span></label>' +
                    '<button type="button" class="btn ghost small remove-row">Remove</button>';
                return row;
            }

            function addRow(list, rowFactory) {
                if (!list) {
                    return;
                }
                var row = rowFactory();
                row.querySelector('.remove-row').addEventListener('click', function () {
                    row.remove();
                });
                list.appendChild(row);
            }

            affiliationRadios.forEach(function (radio) {
                radio.addEventListener('change', updateAffiliation);
            });

            publicRadios.forEach(function (radio) {
                radio.addEventListener('change', updatePublicFields);
            });

            bookingPurposeRadios.forEach(function (radio) {
                radio.addEventListener('change', toggleBookingPurpose);
            });

            if (studentToggle) {
                studentToggle.addEventListener('change', toggleSupervisor);
            }

            if (equipmentToggle) {
                equipmentToggle.addEventListener('change', function () {
                    toggleInlineSection(equipmentToggle, equipmentSection);
                    if (equipmentToggle.checked && equipmentList && equipmentList.children.length === 0) {
                        addRow(equipmentList, createEquipmentRow);
                    }
                });
            }

            if (chemicalsToggle) {
                chemicalsToggle.addEventListener('change', function () {
                    toggleInlineSection(chemicalsToggle, chemicalsSection);
                    if (chemicalsToggle.checked && chemicalsList && chemicalsList.children.length === 0) {
                        addRow(chemicalsList, createChemicalRow);
                    }
                });
            }

            if (addEquipment) {
                addEquipment.addEventListener('click', function () {
                    addRow(equipmentList, createEquipmentRow);
                });
            }

            if (addChemical) {
                addChemical.addEventListener('click', function () {
                    addRow(chemicalsList, createChemicalRow);
                });
            }

            updateAffiliation();
            updatePublicFields();
            toggleBookingPurpose();
            toggleSupervisor();
            toggleInlineSection(equipmentToggle, equipmentSection);
            toggleInlineSection(chemicalsToggle, chemicalsSection);
            if (equipmentToggle && equipmentToggle.checked && equipmentList && equipmentList.children.length === 0) {
                addRow(equipmentList, createEquipmentRow);
            }
            if (chemicalsToggle && chemicalsToggle.checked && chemicalsList && chemicalsList.children.length === 0) {
                addRow(chemicalsList, createChemicalRow);
            }
        })();
    </script>
</body>
</html>
