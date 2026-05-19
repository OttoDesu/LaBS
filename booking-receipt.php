<?php
require_once __DIR__ . '/init.php';
require_login();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_type = $_SESSION['user_type'] ?? 'public';
$is_management = is_management_user($user_type);
$booking_pk = get_booking_pk_column($mysqli);

$booking_id = (int) ($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$params = [];
$types = '';
$where = 'lb.' . $booking_pk . ' = ?';
$params[] = $booking_id;
$types .= 'i';
if (!$is_management) {
    $where .= ' AND lb.user_id = ?';
    $params[] = $user_id;
    $types .= 'i';
}

$stmt = $mysqli->prepare('
    SELECT lb.' . $booking_pk . ' AS booking_id, lb.user_id, lb.booking_date, lb.time_slot, lb.status,
           lb.created_at, lb.updated_at, l.lab_name, c.cluster_name,
           lr.reservation_id, lr.title, lr.activity_details, lr.full_name, lr.ic_no, lr.email, lr.phone,
           lr.affiliation_type, lr.public_agency_type, lr.public_sector, lr.government_info,
           lr.include_equipment, lr.include_chemicals, lr.is_student,
           lr.supervisor_name, lr.supervisor_matric, lr.supervisor_phone, lr.supervisor_email,
           lr.start_time, lr.end_time
    FROM lab_bookings lb
    JOIN labs l ON l.lab_id = lb.lab_id
    JOIN clusters c ON c.cluster_id = l.cluster_id
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.' . $booking_pk . '
    WHERE ' . $where . '
    LIMIT 1
');
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    header('Location: dashboard.php');
    exit;
}

$equipment = [];
$chemicals = [];
if (!empty($booking['reservation_id'])) {
    $reservation_id = (int) $booking['reservation_id'];

    $stmt = $mysqli->prepare('
        SELECT equipment_name, quantity, notes
        FROM reservation_equipment
        WHERE reservation_id = ?
        ORDER BY equipment_id ASC
    ');
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
    }
    $stmt->close();

    $stmt = $mysqli->prepare('
        SELECT chemical_name, quantity, ppe_required
        FROM reservation_chemicals
        WHERE reservation_id = ?
        ORDER BY chemical_id ASC
    ');
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $chemicals[] = $row;
    }
    $stmt->close();
}

function format_datetime($value) {
    if (!$value) {
        return '-';
    }
    return date('Y-m-d H:i', strtotime($value));
}

$calendar_payload = build_booking_calendar_payload($booking);
$google_calendar_url = $calendar_payload ? build_google_calendar_url($calendar_payload) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt</title>
    <style>
        :root {
            --text: #0f172a;
            --muted: #475569;
            --border: #e2e8f0;
            --panel: #ffffff;
            --accent: #1d4ed8;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text);
            background: #f8fafc;
        }
        .page {
            max-width: 920px;
            margin: 32px auto;
            background: var(--panel);
            border: 1px solid var(--border);
            padding: 28px 32px 32px;
            border-radius: 14px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .header small {
            color: var(--muted);
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            border: 1px solid var(--border);
            background: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: var(--text);
            font-size: 13px;
        }
        .btn.primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }
        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
        }
        .card h3 {
            margin: 0 0 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--muted);
        }
        .field {
            margin-bottom: 8px;
            font-size: 13px;
        }
        .field span {
            color: var(--muted);
            display: inline-block;
            min-width: 110px;
        }
        .section-title {
            margin: 18px 0 10px;
            font-size: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th, td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid var(--border);
        }
        th {
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.6px;
        }
        .muted {
            color: var(--muted);
        }
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #e2e8f0;
        }
        .status.Approved {
            background: #dcfce7;
            color: #166534;
        }
        .status.Rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .status.Cancelled {
            background: #e2e8f0;
            color: #475569;
        }
        @media print {
            body {
                background: #fff;
            }
            .page {
                border: none;
                margin: 0;
                border-radius: 0;
                padding: 0;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <h1>Booking Receipt</h1>
                <small>Booking ID #<?php echo (int) $booking['booking_id']; ?></small>
            </div>
            <div class="actions">
                <a class="btn" href="dashboard.php">Back</a>
                <?php if ($google_calendar_url !== ''): ?>
                    <a class="btn" href="<?php echo htmlspecialchars($google_calendar_url); ?>" target="_blank" rel="noopener">Add to Google Calendar</a>
                    <a class="btn" href="export-calendar.php?booking_id=<?php echo (int) $booking['booking_id']; ?>">Download .ics</a>
                <?php endif; ?>
                <button class="btn primary" type="button" onclick="window.print()">Print</button>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3>Booking</h3>
                <div class="field"><span>Lab</span><?php echo htmlspecialchars($booking['lab_name']); ?></div>
                <div class="field"><span>Cluster</span><?php echo htmlspecialchars($booking['cluster_name']); ?></div>
                <div class="field"><span>Lab Date</span><?php echo htmlspecialchars($booking['booking_date']); ?></div>
                <div class="field"><span>Time Slot</span><?php echo htmlspecialchars($booking['time_slot']); ?></div>
                <div class="field"><span>Status</span><span class="status <?php echo htmlspecialchars($booking['status']); ?>"><?php echo htmlspecialchars(get_booking_status_label($booking['status'] ?? '')); ?></span></div>
            </div>
            <div class="card">
                <h3>Timeline</h3>
                <div class="field"><span>Created At</span><?php echo htmlspecialchars(format_datetime($booking['created_at'])); ?></div>
                <div class="field"><span>Updated At</span><?php echo htmlspecialchars(format_datetime($booking['updated_at'])); ?></div>
            </div>
            <div class="card">
                <h3>Applicant</h3>
                <div class="field"><span>Name</span><?php echo htmlspecialchars($booking['full_name'] ?? '-'); ?></div>
                <div class="field"><span>IC</span><?php echo htmlspecialchars($booking['ic_no'] ?? '-'); ?></div>
                <div class="field"><span>Email</span><?php echo htmlspecialchars($booking['email'] ?? '-'); ?></div>
                <div class="field"><span>Phone</span><?php echo htmlspecialchars($booking['phone'] ?? '-'); ?></div>
            </div>
        </div>

        <div class="card">
            <h3>Booking Form</h3>
            <div class="field"><span>Title</span><?php echo htmlspecialchars($booking['title'] ?? '-'); ?></div>
            <div class="field"><span>Activity</span><?php echo htmlspecialchars($booking['activity_details'] ?? '-'); ?></div>
            <div class="field"><span>Affiliation</span><?php echo htmlspecialchars($booking['affiliation_type'] ?? '-'); ?></div>
            <?php if (($booking['affiliation_type'] ?? '') === 'public'): ?>
                <div class="field"><span>Agency Type</span><?php echo htmlspecialchars($booking['public_agency_type'] ?? '-'); ?></div>
                <div class="field"><span>Sector</span><?php echo htmlspecialchars($booking['public_sector'] ?? '-'); ?></div>
                <div class="field"><span>Government</span><?php echo htmlspecialchars($booking['government_info'] ?? '-'); ?></div>
            <?php endif; ?>
            <div class="field"><span>Start Time</span><?php echo htmlspecialchars($booking['start_time'] ?? '-'); ?></div>
            <div class="field"><span>End Time</span><?php echo htmlspecialchars($booking['end_time'] ?? '-'); ?></div>
        </div>

        <?php if (!empty($booking['is_student'])): ?>
            <div class="card">
                <h3>Supervisor</h3>
                <div class="field"><span>Name</span><?php echo htmlspecialchars($booking['supervisor_name'] ?? '-'); ?></div>
                <div class="field"><span>Matric</span><?php echo htmlspecialchars($booking['supervisor_matric'] ?? '-'); ?></div>
                <div class="field"><span>Phone</span><?php echo htmlspecialchars($booking['supervisor_phone'] ?? '-'); ?></div>
                <div class="field"><span>Email</span><?php echo htmlspecialchars($booking['supervisor_email'] ?? '-'); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($equipment): ?>
            <h3 class="section-title">Equipment</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipment as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                            <td><?php echo (int) $item['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($item['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($chemicals): ?>
            <h3 class="section-title">Chemicals</h3>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>PPE Required</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chemicals as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['chemical_name']); ?></td>
                            <td><?php echo (int) $item['quantity']; ?></td>
                            <td><?php echo !empty($item['ppe_required']) ? 'Yes' : 'No'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (!$equipment && !$chemicals): ?>
            <p class="muted">No equipment or chemicals requested.</p>
        <?php endif; ?>
    </div>
</body>
</html>
