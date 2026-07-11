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

$where = 'lb.' . $booking_pk . ' = ?';
$params = [$booking_id];
$types = 'i';
if (!$is_management) {
    $where .= ' AND lb.user_id = ?';
    $params[] = $user_id;
    $types .= 'i';
}

$stmt = $mysqli->prepare('
    SELECT lb.' . $booking_pk . ' AS booking_id, lb.user_id, lb.booking_date, lb.time_slot, lb.status,
           lb.created_at, lb.updated_at, l.lab_name, c.cluster_name,
           u.name AS user_name, u.email AS user_email, u.student_staff_id, u.department,
           lr.reservation_id, lr.title, lr.activity_details, lr.full_name, lr.ic_no, lr.email, lr.phone,
           lr.affiliation_type, lr.public_agency_type, lr.public_sector, lr.government_info,
           lr.include_equipment, lr.include_chemicals, lr.is_student,
           lr.supervisor_name, lr.supervisor_matric, lr.supervisor_phone, lr.supervisor_email,
           lr.start_time, lr.end_time, lr.document_path
    FROM lab_bookings lb
    JOIN labs l ON l.lab_id = lb.lab_id
    JOIN clusters c ON c.cluster_id = l.cluster_id
    JOIN users u ON u.id = lb.user_id
    LEFT JOIN lab_reservations lr ON lr.booking_id = lb.' . $booking_pk . '
    WHERE ' . $where . '
    LIMIT 1
');
$stmt->bind_param($types, ...$params);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    header('Location: dashboard.php');
    exit;
}

$equipment = [];
$chemicals = [];
if (!empty($booking['reservation_id'])) {
    $reservation_id = (int) $booking['reservation_id'];

    $stmt = $mysqli->prepare('SELECT equipment_name, quantity, notes FROM reservation_equipment WHERE reservation_id = ? ORDER BY equipment_id ASC');
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
    }
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT chemical_name, quantity, ppe_required FROM reservation_chemicals WHERE reservation_id = ? ORDER BY chemical_id ASC');
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $chemicals[] = $row;
    }
    $stmt->close();
}

function receipt_value($value): string {
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $value : '-';
}

function receipt_time($value): string {
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? substr($value, 0, 5) : '-';
}

function receipt_day_name($date): string {
    $timestamp = strtotime((string) $date);
    if (!$timestamp) {
        return '-';
    }
    $days = ['Sunday' => 'Ahad', 'Monday' => 'Isnin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Khamis', 'Friday' => 'Jumaat', 'Saturday' => 'Sabtu'];
    return $days[date('l', $timestamp)] ?? date('l', $timestamp);
}

$applicant_name = receipt_value($booking['full_name'] ?? $booking['user_name'] ?? '');
$applicant_email = receipt_value($booking['email'] ?? $booking['user_email'] ?? '');
$applicant_id = receipt_value($booking['student_staff_id'] ?? $booking['ic_no'] ?? '');
$faculty = receipt_value($booking['department'] ?? $booking['cluster_name'] ?? '');
$activity = receipt_value($booking['activity_details'] ?? $booking['title'] ?? '');
$start_time = receipt_time($booking['start_time'] ?? '');
$end_time = receipt_time($booking['end_time'] ?? '');
$status_label = get_booking_status_label($booking['status'] ?? '');
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
        :root{--text:#111827;--muted:#5f6b7a;--border:#d9dee8;--soft:#f5f7fb;--accent:#2563eb;--danger:#dc3545;--success:#159947}
        *{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;color:var(--text);background:#f1f5f9}
        .page-shell{max-width:1040px;margin:28px auto;padding:0 18px}.receipt-panel{background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;box-shadow:0 12px 30px rgba(15,23,42,.08)}
        .receipt-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border)}.receipt-toolbar h1{margin:0;font-size:18px}
        .receipt-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}.btn{border:1px solid var(--border);background:#fff;padding:9px 13px;border-radius:8px;font-weight:700;cursor:pointer;text-decoration:none;color:var(--text);font-size:13px}.btn.primary{background:var(--accent);color:#fff;border-color:var(--accent)}.btn.danger{background:var(--danger);color:#fff;border-color:var(--danger)}
        .view-pane{display:none;padding:16px}.view-pane.is-active{display:block}.status-line{display:flex;align-items:center;gap:8px;padding:10px 0 18px;font-size:14px;font-weight:700}.status-pill{display:inline-flex;padding:3px 9px;border-radius:999px;font-size:11px;color:#fff;background:#64748b}.status-pill.Approved{background:var(--success)}.status-pill.Rejected{background:#dc2626}
        .receipt-section{margin-top:16px}.receipt-section h2{margin:0 0 8px;font-size:15px}.data-table{width:100%;border-collapse:collapse;font-size:13px;table-layout:fixed}.data-table th,.data-table td{border:1px solid #e5e7eb;padding:9px 10px;text-align:left;vertical-align:top;overflow-wrap:anywhere}.data-table th{background:#fff;font-weight:700}.data-table .empty{text-align:center;color:#4b5563;background:#f3f4f6}.document-link{display:inline-flex;padding:7px 10px;border-radius:6px;color:#fff;background:var(--danger);text-decoration:none;font-weight:700;font-size:12px}
        .official-sheet{max-width:820px;margin:0 auto;background:#fff;color:#000;font-family:Arial,Helvetica,sans-serif}.official-top{display:grid;grid-template-columns:220px 1fr;border:2px solid #111;min-height:72px}.official-logo{display:flex;align-items:center;justify-content:center;border-right:2px solid #111;padding:8px}.official-logo img{max-width:180px;max-height:54px;object-fit:contain}.official-agency{display:grid;place-items:center;text-align:center;font-size:11px;font-weight:700;line-height:1.5}
        .official-title{margin-top:12px;border:2px solid #111;text-align:center;padding:16px 10px;font-size:14px;font-weight:800;line-height:1.5}.notice-box{margin-top:10px;border:1px solid #111;font-size:10px}.notice-box strong{display:block;padding:5px 6px;border-bottom:1px solid #111}.notice-box div{padding:5px 8px;border-bottom:1px solid #111}.notice-box div:last-child{border-bottom:0}
        .official-section{margin-top:14px}.official-section-title{background:#000;color:#fff;padding:7px 8px;font-size:11px;font-weight:800}.official-table{width:100%;border-collapse:collapse;font-size:10px}.official-table th,.official-table td{border:1px solid #111;padding:7px 8px;text-align:left;vertical-align:top}.official-table th{font-weight:800;background:#f5f5f5}.official-signature-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:18px;font-size:10px}.signature-box{border:1px solid #111;min-height:88px;padding:8px}.signature-line{margin-top:38px;border-top:1px solid #111;padding-top:5px}
        @media(max-width:760px){.receipt-toolbar{align-items:flex-start;flex-direction:column}.receipt-actions{justify-content:flex-start}.official-top{grid-template-columns:1fr}.official-logo{border-right:0;border-bottom:2px solid #111}}
        @media print{body{background:#fff}.page-shell{max-width:none;margin:0;padding:0}.receipt-panel{border:0;border-radius:0;box-shadow:none}.receipt-toolbar,.app-view{display:none!important}.pdf-view{display:block!important;padding:0}.official-sheet{max-width:none}@page{size:A4;margin:14mm}}
    </style>
</head>
<body>
<main class="page-shell">
    <section class="receipt-panel">
        <div class="receipt-toolbar">
            <h1>Maklumat Tempahan</h1>
            <div class="receipt-actions">
                <button class="btn" type="button" data-view="app">View Form</button>
                <button class="btn primary" type="button" data-view="pdf">View PDF Form</button>
                <?php if ($google_calendar_url !== ''): ?><a class="btn" href="<?php echo htmlspecialchars($google_calendar_url); ?>" target="_blank" rel="noopener">Add to Google Calendar</a><?php endif; ?>
                <button class="btn danger" type="button" id="print-pdf">Print / Save PDF</button>
            </div>
        </div>

        <div class="view-pane app-view is-active" id="app-view">
            <div class="status-line"><span>Status Tempahan:</span><span class="status-pill <?php echo htmlspecialchars($booking['status']); ?>"><?php echo htmlspecialchars($status_label); ?></span></div>
            <div class="receipt-section">
                <h2>Maklumat Pemohon</h2>
                <table class="data-table"><thead><tr><th>Nama Pemohon</th><th>No. Telefon</th><th>Email</th><th>Fakulti</th></tr></thead><tbody><tr><td><?php echo htmlspecialchars($applicant_name); ?></td><td><?php echo htmlspecialchars(receipt_value($booking['phone'] ?? '')); ?></td><td><?php echo htmlspecialchars($applicant_email); ?></td><td><?php echo htmlspecialchars($faculty); ?></td></tr></tbody></table>
            </div>
            <div class="receipt-section">
                <table class="data-table"><thead><tr><th>Tarikh</th><th>Hari</th><th>Masa Mula</th><th>Masa Tamat</th><th>Deskripsi</th><th>Lampiran</th></tr></thead><tbody><tr><td><?php echo htmlspecialchars($booking['booking_date']); ?></td><td><?php echo htmlspecialchars(receipt_day_name($booking['booking_date'])); ?></td><td><?php echo htmlspecialchars($start_time); ?></td><td><?php echo htmlspecialchars($end_time); ?></td><td><?php echo htmlspecialchars($activity); ?></td><td><?php if (!empty($booking['document_path'])): ?><a class="document-link" href="<?php echo htmlspecialchars($booking['document_path']); ?>" target="_blank" rel="noopener">View PDF</a><?php else: ?>-<?php endif; ?></td></tr></tbody></table>
            </div>
            <div class="receipt-section">
                <h2>Alatan</h2>
                <table class="data-table"><thead><tr><th>Nama Alat</th><th>Jumlah Alat</th><th>Deskripsi</th></tr></thead><tbody><?php if ($equipment): ?><?php foreach ($equipment as $item): ?><tr><td><?php echo htmlspecialchars($item['equipment_name']); ?></td><td><?php echo (int) $item['quantity']; ?></td><td><?php echo htmlspecialchars(receipt_value($item['notes'] ?? '')); ?></td></tr><?php endforeach; ?><?php else: ?><tr><td class="empty" colspan="3">Tiada Data Alatan</td></tr><?php endif; ?></tbody></table>
            </div>
            <div class="receipt-section">
                <h2>Bahan Kimia</h2>
                <table class="data-table"><thead><tr><th>Nama Bahan Kimia</th><th>Jumlah Bahan Kimia</th><th>PPE</th></tr></thead><tbody><?php if ($chemicals): ?><?php foreach ($chemicals as $item): ?><tr><td><?php echo htmlspecialchars($item['chemical_name']); ?></td><td><?php echo (int) $item['quantity']; ?></td><td><?php echo !empty($item['ppe_required']) ? 'Yes' : 'No'; ?></td></tr><?php endforeach; ?><?php else: ?><tr><td class="empty" colspan="3">Tiada Data Bahan Kimia</td></tr><?php endif; ?></tbody></table>
            </div>
            <div class="receipt-section">
                <h2>Maklumat Supervisor</h2>
                <table class="data-table"><thead><tr><th>Nama Supervisor</th><th>No. Matrik</th><th>No. Telefon</th><th>Email</th></tr></thead><tbody><?php if (!empty($booking['is_student'])): ?><tr><td><?php echo htmlspecialchars(receipt_value($booking['supervisor_name'] ?? '')); ?></td><td><?php echo htmlspecialchars(receipt_value($booking['supervisor_matric'] ?? '')); ?></td><td><?php echo htmlspecialchars(receipt_value($booking['supervisor_phone'] ?? '')); ?></td><td><?php echo htmlspecialchars(receipt_value($booking['supervisor_email'] ?? '')); ?></td></tr><?php else: ?><tr><td class="empty" colspan="4">Tiada Data Supervisor</td></tr><?php endif; ?></tbody></table>
            </div>
        </div>

        <div class="view-pane pdf-view" id="pdf-view">
            <div class="official-sheet">
                <div class="official-top"><div class="official-logo"><img src="img/labs_logo.png" alt="UTHM"></div><div class="official-agency"><div>PEJABAT PENGURUSAN MAKMAL<br>KAMPUS CAWANGAN PAGOH<br>(PPMKCP)</div></div></div>
                <div class="official-title">BORANG PERMOHONAN PENGGUNAAN MAKMAL</div>
                <div class="notice-box"><strong>PERHATIAN:</strong><div>a) Permohonan perlu diisi dalam format digital (pdf) dan dihantar TIGA(3) hari sebelum tarikh penggunaan kepada Penyelia Makmal.</div><div>b) Penyelia makmal perlu dimaklumkan setiap kali apabila hendak menggunakan makmal.</div><div>c) Sebarang kelulusan perlu diberikan kepada Penyelia Makmal.</div><div>d) Pengguna hendaklah mematuhi peraturan keselamatan makmal dan etika berpakaian sepanjang tempoh penggunaan.</div><div>e) SOP makmal dan arahan pengurusan makmal WAJIB dipatuhi.</div></div>
                <div class="official-section"><div class="official-section-title">1. MAKLUMAT PEMOHON</div><table class="official-table"><thead><tr><th style="width:40px">BIL</th><th>Nama</th><th>Fakulti/PPj/Organisasi</th><th>No. Matrik/KP</th><th>No. Telefon</th></tr></thead><tbody><tr><td>1</td><td><?php echo htmlspecialchars($applicant_name); ?></td><td><?php echo htmlspecialchars($faculty); ?></td><td><?php echo htmlspecialchars($applicant_id); ?></td><td><?php echo htmlspecialchars(receipt_value($booking['phone'] ?? '')); ?></td></tr></tbody></table></div>
                <div class="official-section"><div class="official-section-title">2. MAKLUMAT MAKMAL</div><table class="official-table"><tbody><tr><th>Nama Makmal</th><td><?php echo htmlspecialchars($booking['lab_name']); ?></td></tr><tr><th>Kluster</th><td><?php echo htmlspecialchars($booking['cluster_name']); ?></td></tr></tbody></table></div>
                <div class="official-section"><div class="official-section-title">3. MAKLUMAT TEMPAHAN</div><table class="official-table"><thead><tr><th>Tarikh</th><th>Hari</th><th>Masa Mula</th><th>Masa Tamat</th><th>Tujuan / Deskripsi</th></tr></thead><tbody><tr><td><?php echo htmlspecialchars(format_display_date($booking['booking_date'])); ?></td><td><?php echo htmlspecialchars(receipt_day_name($booking['booking_date'])); ?></td><td><?php echo htmlspecialchars($start_time); ?></td><td><?php echo htmlspecialchars($end_time); ?></td><td><?php echo htmlspecialchars($activity); ?></td></tr></tbody></table></div>
                <div class="official-section"><div class="official-section-title">4. ALATAN / BAHAN KIMIA</div><table class="official-table"><thead><tr><th>Jenis</th><th>Nama Item</th><th>Kuantiti</th><th>Catatan / PPE</th></tr></thead><tbody><?php if ($equipment || $chemicals): ?><?php foreach ($equipment as $item): ?><tr><td>Alatan</td><td><?php echo htmlspecialchars($item['equipment_name']); ?></td><td><?php echo (int) $item['quantity']; ?></td><td><?php echo htmlspecialchars(receipt_value($item['notes'] ?? '')); ?></td></tr><?php endforeach; ?><?php foreach ($chemicals as $item): ?><tr><td>Bahan Kimia</td><td><?php echo htmlspecialchars($item['chemical_name']); ?></td><td><?php echo (int) $item['quantity']; ?></td><td><?php echo !empty($item['ppe_required']) ? 'PPE required' : 'PPE not required'; ?></td></tr><?php endforeach; ?><?php else: ?><tr><td colspan="4">Tiada alatan atau bahan kimia dimohon.</td></tr><?php endif; ?></tbody></table></div>
                <div class="official-section"><div class="official-section-title">5. MAKLUMAT SUPERVISOR</div><table class="official-table"><thead><tr><th>Nama Supervisor</th><th>No. Matrik</th><th>No. Telefon</th><th>Email</th></tr></thead><tbody><tr><td><?php echo htmlspecialchars(!empty($booking['is_student']) ? receipt_value($booking['supervisor_name'] ?? '') : '-'); ?></td><td><?php echo htmlspecialchars(!empty($booking['is_student']) ? receipt_value($booking['supervisor_matric'] ?? '') : '-'); ?></td><td><?php echo htmlspecialchars(!empty($booking['is_student']) ? receipt_value($booking['supervisor_phone'] ?? '') : '-'); ?></td><td><?php echo htmlspecialchars(!empty($booking['is_student']) ? receipt_value($booking['supervisor_email'] ?? '') : '-'); ?></td></tr></tbody></table></div>
                <div class="official-signature-grid"><div class="signature-box">Pemohon<div class="signature-line">Tandatangan / Tarikh</div></div><div class="signature-box">Pengesahan Penyelia Makmal<div class="signature-line">Tandatangan / Cop / Tarikh</div></div></div>
            </div>
        </div>
    </section>
</main>
<script>
(function(){var appView=document.getElementById('app-view');var pdfView=document.getElementById('pdf-view');function setView(view){var showPdf=view==='pdf';appView.classList.toggle('is-active',!showPdf);pdfView.classList.toggle('is-active',showPdf)}document.querySelectorAll('[data-view]').forEach(function(button){button.addEventListener('click',function(){setView(button.getAttribute('data-view'))})});var printButton=document.getElementById('print-pdf');if(printButton){printButton.addEventListener('click',function(){setView('pdf');window.print()})}})();
</script>
</body>
</html>
