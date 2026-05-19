<?php
require_once __DIR__ . '/init.php';

function ensure_cluster($mysqli, $name, $description) {
    $stmt = $mysqli->prepare('SELECT cluster_id FROM clusters WHERE cluster_name = ? LIMIT 1');
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        return $row['cluster_id'];
    }

    $stmt = $mysqli->prepare('INSERT INTO clusters (cluster_name, cluster_description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
    $stmt->bind_param('ss', $name, $description);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

function ensure_supervisor($mysqli, $cluster_id, $name, $email, $room_no) {
    $stmt = $mysqli->prepare('SELECT supervisor_id FROM supervisors WHERE cluster_id = ? AND supervisor_name = ? LIMIT 1');
    $stmt->bind_param('is', $cluster_id, $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        if ($room_no !== '') {
            $stmt = $mysqli->prepare('UPDATE supervisors SET supervisor_room_no = ? WHERE supervisor_id = ?');
            $stmt->bind_param('si', $room_no, $row['supervisor_id']);
            $stmt->execute();
            $stmt->close();
        }
        return $row['supervisor_id'];
    }

    $email_value = $email !== '' ? $email : null;
    $room_value = $room_no !== '' ? $room_no : null;
    $stmt = $mysqli->prepare('
        INSERT INTO supervisors (cluster_id, supervisor_name, supervisor_email, supervisor_room_no, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ');
    $stmt->bind_param('isss', $cluster_id, $name, $email_value, $room_value);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

function ensure_lab($mysqli, $cluster_id, $name, $description, $capacity, $supervisor_id) {
    $stmt = $mysqli->prepare('SELECT lab_id FROM labs WHERE lab_name = ? AND cluster_id = ? LIMIT 1');
    $stmt->bind_param('si', $name, $cluster_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $lab_id = (int) $row['lab_id'];
        $stmt = $mysqli->prepare('UPDATE labs SET supervisor_id = ? WHERE lab_id = ? AND (supervisor_id IS NULL OR supervisor_id = 0)');
        $stmt->bind_param('ii', $supervisor_id, $lab_id);
        $stmt->execute();
        $stmt->close();
        return $lab_id;
    }

    $stmt = $mysqli->prepare('INSERT INTO labs (cluster_id, supervisor_id, lab_name, lab_description, lab_capacity, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('iissi', $cluster_id, $supervisor_id, $name, $description, $capacity);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

$cluster_map = [
    'applied-science' => ensure_cluster(
        $mysqli,
        'Kluster Sains Gunaan & Teknologi',
        'Makmal biologi, fizik, kimia makanan, matematik dan statistik.'
    ),
    'civil-chemical' => ensure_cluster(
        $mysqli,
        'Kluster Teknologi Kejuruteraan Awam & Kimia',
        'Merangkumi bengkel awam, bioproses, air & alam sekitar, geoteknik, dan instrumen kimia.'
    ),
    'electrical-multimedia' => ensure_cluster(
        $mysqli,
        'Kluster Teknologi Kejuruteraan Elektrik & Multimedia',
        'Fokus kepada asas elektrik, elektronik kuasa, multimedia, rangkaian komputer dan komunikasi.'
    ),
    'mechanical-transport' => ensure_cluster(
        $mysqli,
        'Kluster Teknologi Kejuruteraan Mekanikal & Pengangkutan',
        'Menawarkan kemudahan automotif, fabrikasi, termodinamik, tekstil, dan ujian bahan.'
    )
];

$default_description = 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.';
$default_capacity = 30;

$labs = [
    [
        'clusterId' => 'applied-science',
        'name' => 'Bengkel Teknologi Masonri (COR SUNR)',
        'location' => '2.G.1.051',
        'supervisor_name' => 'Muhammad Izzat Hafizuddin bin Mohd Shah',
        'supervisor_email' => 'izzath@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Analisis Makanan',
        'location' => '2.J.1.010',
        'supervisor_name' => 'Mohd Yusof bin Mohd Nor',
        'supervisor_email' => 'yusofn@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Biokimia Makanan',
        'location' => '2.J.1.027',
        'supervisor_name' => 'Siti Zarah binti Imam Tohit',
        'supervisor_email' => 'sitizarah@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Biologi Struktur Dan Fungsi 1',
        'location' => '2.J.2.018',
        'supervisor_name' => 'Nur Atiera binti Ramli',
        'supervisor_email' => 'nuratiera@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Biologi Struktur Dan Fungsi 2',
        'location' => '2.J.2.022',
        'supervisor_name' => 'Muhammad Izzat Hafizuddin bin Mohd Shah',
        'supervisor_email' => 'izzath@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Biologi Struktur Dan Fungsi 3',
        'location' => '2.J.2.042',
        'supervisor_name' => 'Aliff Hamzah bin Kamaruddin',
        'supervisor_email' => 'aliffh@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Bahan',
        'location' => '2.J.2.001',
        'supervisor_name' => 'Kamarul Affendi bin Hamdan',
        'supervisor_email' => 'affendi@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Elektrik Dan Magnet (Getaran Dan Gelombang)',
        'location' => '2.J.2.012',
        'supervisor_name' => 'Norsidah binti Harun',
        'supervisor_email' => 'norsidah@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Elektronik',
        'location' => '2.J.2.052',
        'supervisor_name' => 'Muhammad Ghazalli bin Ibrahim',
        'supervisor_email' => 'ghazalli@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Instrumentasi',
        'location' => '2.J.2.055',
        'supervisor_name' => 'Muhammad Ghazalli bin Ibrahim',
        'supervisor_email' => 'ghazalli@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Kesihatan',
        'location' => '2.J.1.001',
        'supervisor_name' => 'Sufian bin Abd Rahim',
        'supervisor_email' => 'sufian@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Laser',
        'location' => '2.J.2.008',
        'supervisor_name' => 'Abu Laith bin Solihan',
        'supervisor_email' => 'abulaith@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Optik',
        'location' => '2.J.2.010',
        'supervisor_name' => 'Siti Maisarah binti Rahim',
        'supervisor_email' => 'maisarah@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Sinaran',
        'location' => '2.J.1.007',
        'supervisor_name' => 'Sufian bin Abd Rahim',
        'supervisor_email' => 'sufian@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Statik Dan Mekanik',
        'location' => '2.J.2.016',
        'supervisor_name' => 'Norsidah binti Harun',
        'supervisor_email' => 'norsidah@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Fizik Teknologi Nano',
        'location' => '2.J.2.046',
        'supervisor_name' => 'Mohammad Khairul Nahar bin Kassim',
        'supervisor_email' => 'knahar@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Biologi',
        'location' => '2.J.3.047',
        'supervisor_name' => 'Fatin Nur Ain binti Kemat',
        'supervisor_email' => 'fatinnur@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Fizik 1',
        'location' => '2.J.3.050',
        'supervisor_name' => 'Fathin Jasleen binti Abd Basit',
        'supervisor_email' => 'fathin@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Fizik 2',
        'location' => '2.J.3.049',
        'supervisor_name' => 'Fathin Jasleen binti Abd Basit',
        'supervisor_email' => 'fathin@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Kimia 1',
        'location' => '2.J.3.022',
        'supervisor_name' => 'Mohd Azman bin Mohd Sadikin',
        'supervisor_email' => 'mdazman@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Kimia 2',
        'location' => '2.J.3.025',
        'supervisor_name' => 'Mohd Azman bin Mohd Sadikin',
        'supervisor_email' => 'mdazman@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Kimia 3',
        'location' => '2.J.3.041',
        'supervisor_name' => 'Norhafizam binti Mohamed Yusof',
        'supervisor_email' => 'hafizam@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Kimia 4',
        'location' => '2.J.3.044',
        'supervisor_name' => 'Zharif Aiman bin Abdul Mutalib',
        'supervisor_email' => 'zharif@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Matematik 1',
        'location' => '2.J.3.005',
        'supervisor_name' => 'Norzieyana binti Md. Arshad',
        'supervisor_email' => 'norzieyana@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Matematik 2',
        'location' => '2.J.3.002',
        'supervisor_name' => 'Mohamad Sidiq bin Mohd Basir',
        'supervisor_email' => 'sidiq@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Gunasama Statistik',
        'location' => '2.A1.1.074',
        'supervisor_name' => 'Abu Laith bin Solihan',
        'supervisor_email' => 'abulaith@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Instrumentasi Makanan',
        'location' => '2.J.1.016',
        'supervisor_name' => 'Mohd Marhafidz bin Marjori',
        'supervisor_email' => 'mhafidz@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Kejuruteraan Makanan',
        'location' => '2.J.1.074',
        'supervisor_name' => 'Mohd Yusof bin Mohd Nor',
        'supervisor_email' => 'yusofn@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Matematik 1',
        'location' => '2.J.3.001',
        'supervisor_name' => 'Mohamad Khidir bin Mohd Ibrahim',
        'supervisor_email' => 'khidir@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Matematik 2',
        'location' => '2.J.3.037',
        'supervisor_name' => 'Mohamad Khidir bin Mohd Ibrahim',
        'supervisor_email' => 'khidir@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Mikrobiologi Makanan',
        'location' => '2.J.1.020',
        'supervisor_name' => 'Nurul Fatihah binti Mohd Jailan',
        'supervisor_email' => 'fatihah@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Pemakanan',
        'location' => '2.J.1.033',
        'supervisor_name' => 'Norzieyana binti Md. Arshad',
        'supervisor_email' => 'norzieyana@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Pembangunan Produk Makanan',
        'location' => '2.J.1.055',
        'supervisor_name' => 'Muhammad Hafizul Iqbal bin Mastor',
        'supervisor_email' => 'mhafizul@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Pemprosesan Makanan',
        'location' => '2.J.1.067',
        'supervisor_name' => 'Muhammad Hafizul Iqbal bin Mastor',
        'supervisor_email' => 'mhafizul@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Sains Gunaan 1',
        'location' => '2.J.3.017',
        'supervisor_name' => 'Nur Atiera binti Ramli',
        'supervisor_email' => 'nuratiera@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Sains Gunaan 2',
        'location' => '2.J.3.011',
        'supervisor_name' => 'Muhammad Izzat Hafizuddin bin Mohd Shah',
        'supervisor_email' => 'izzath@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Statistik 1',
        'location' => '2.J.3.008',
        'supervisor_name' => 'Mohamad Sidiq bin Mohd Basir',
        'supervisor_email' => 'sidiq@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Statistik 2',
        'location' => '2.J.3.007',
        'supervisor_name' => 'Mohamad Sidiq bin Mohd Basir',
        'supervisor_email' => 'sidiq@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Teknologi Bakeri, Snek Dan Konfeksionari',
        'location' => '2.J.1.061',
        'supervisor_name' => 'Aliff Hamzah bin Kamaruddin',
        'supervisor_email' => 'aliffh@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Teknologi Sains Dan Kejuruteraan',
        'location' => '2.J.2.005',
        'supervisor_name' => 'Nurul Fatihah binti Mohd Jailan',
        'supervisor_email' => 'fatihah@uthm.edu.my'
    ],
    [
        'clusterId' => 'applied-science',
        'name' => 'Makmal Ujirasa Dan Sensori Makanan',
        'location' => '2.J.1.036',
        'supervisor_name' => 'Siti Zarah binti Imam Tohit',
        'supervisor_email' => 'sitizarah@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Fabrikasi Besi',
        'location' => '2.F.1.008',
        'supervisor_name' => 'Hanafiah bin Ismail',
        'supervisor_email' => 'hanafiah@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Kejuruteraan Komposit',
        'location' => '2.F.1.029',
        'supervisor_name' => 'Tc. Hazri bin Mokhtar',
        'supervisor_email' => 'hazri@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Kejuruteraan Perkayuan',
        'location' => '2.G.1.060',
        'supervisor_name' => 'Tc. Mudzaffar Syah bin Kamarudin',
        'supervisor_email' => 'mudzaffar@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Kejuruteraan Perpaipan',
        'location' => '2.G.1.002',
        'supervisor_name' => 'Nadia binti Kasim',
        'supervisor_email' => 'nadiakasim@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Kejuruteraan Struktur Berat',
        'location' => '2.F.1.036',
        'supervisor_name' => 'Tc. Hazri bin Mokhtar',
        'supervisor_email' => 'hazri@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Konkrit',
        'location' => '2.F.1.001',
        'supervisor_name' => 'Hanafiah bin Ismail',
        'supervisor_email' => 'hanafiah@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bengkel Teknologi Perabot',
        'location' => '2.G.1.009',
        'supervisor_name' => 'Tc. Mudzaffar Syah bin Kamarudin',
        'supervisor_email' => 'mudzaffar@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bilik Bahan Kimia',
        'location' => '2.F.1.026',
        'supervisor_name' => 'Mohamad Zayani Zakwan bin Mohd Zin',
        'supervisor_email' => 'zayani@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Bilik Sisa Kimia',
        'location' => '2.F.1.025',
        'supervisor_name' => 'Muhammad Fadhli Hakim bin Ahmad',
        'supervisor_email' => 'mdfadhli@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Analitikal',
        'location' => '2.J.1.050',
        'supervisor_name' => 'Mohamad Zulhilmi bin Paiman',
        'supervisor_email' => 'zulhilmip@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Aplikasi Komputer Kejuruteraan Awam',
        'location' => '2.G.3.005',
        'supervisor_name' => 'Tc. Nurhayani binti Ujurmudi',
        'supervisor_email' => 'nurhayani@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Bioproses Hiliran 1',
        'location' => '2.H.1.001',
        'supervisor_name' => 'Ts. Noor Aminadia binti Baharuddin',
        'supervisor_email' => 'aminadia@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Bioproses Hiliran 2',
        'location' => '2.J.2.035',
        'supervisor_name' => 'Nur Farrah Ain binti SM Bakri',
        'supervisor_email' => 'nurfarrah@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Bioproses Huluan',
        'location' => '2.H.1.022',
        'supervisor_name' => 'Aziah binti Abu Samah',
        'supervisor_email' => 'aziah@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Kejuruteraan Tindakbalas Kimia',
        'location' => '2.H.1.049',
        'supervisor_name' => 'Nurhasikin binti Tugiman',
        'supervisor_email' => 'nurhasikin@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Komputer Teknologi Kejuruteraan Kimia',
        'location' => '2.H.2.016',
        'supervisor_name' => 'Mohamad Zulhilmi bin Paiman',
        'supervisor_email' => 'zulhilmip@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Lukisan Cad Kejuruteraan Awam 1',
        'location' => '2.H.2.019',
        'supervisor_name' => 'Nur Ain binti Mohamad',
        'supervisor_email' => 'nurain@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Lukisan Cad Kejuruteraan Awam 2',
        'location' => '2.G.3.004',
        'supervisor_name' => 'Nur Ain binti Mohamad',
        'supervisor_email' => 'nurain@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Lukisan Teknologi Kejuruteraan Awam 1',
        'location' => '2.H.3.016',
        'supervisor_name' => 'Harina binti Md Amin',
        'supervisor_email' => 'harina@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Lukisan Teknologi Kejuruteraan Awam 2',
        'location' => '2.G.3.001',
        'supervisor_name' => 'Tc. Mohd Faizal Riza bin Kamian',
        'supervisor_email' => 'faizalr@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Mekanik Bendalir',
        'location' => '2.H.1.010',
        'supervisor_name' => 'Ts. Noor Aminadia binti Baharuddin',
        'supervisor_email' => 'aminadia@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Pemindahan Haba & Jisim',
        'location' => '2.H.1.054',
        'supervisor_name' => 'Nurhasikin binti Tugiman',
        'supervisor_email' => 'nurhasikin@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Proses Instrumentasi',
        'location' => '2.H.1.045',
        'supervisor_name' => 'Muhammad Faizi Bin Ibrahim',
        'supervisor_email' => 'mfaizi@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Proses Pemisahan',
        'location' => '2.H.1.042',
        'supervisor_name' => 'Muhammad Faizi Bin Ibrahim',
        'supervisor_email' => 'mfaizi@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Bahan',
        'location' => '2.H.1.016',
        'supervisor_name' => 'Mohd Redzuan bin Mohd Nor',
        'supervisor_email' => 'mredzuan@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Air Dan Air Sisa',
        'location' => '2.F.1.020',
        'supervisor_name' => 'Mohamad Zayani Zakwan bin Mohd Zin',
        'supervisor_email' => 'zayani@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Air Pollution',
        'location' => '2.G.2.016',
        'supervisor_name' => 'Siti Nadia Syuhada binti Mohd Satti',
        'supervisor_email' => 'sitinadia@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Alam Sekitar',
        'location' => '2.G.1.070',
        'supervisor_name' => 'Nik Shamimi Nazma binti Nik Mohamed Kamal',
        'supervisor_email' => 'nikshamimi@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Geomatik',
        'location' => '2.G.1.036',
        'supervisor_name' => 'Siti Khadijah binti Md Nor',
        'supervisor_email' => 'khadijahn@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Geoteknik',
        'location' => '2.H.1.037',
        'supervisor_name' => 'Faeqatul Nabila binti Zubir',
        'supervisor_email' => 'faeqatul@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Jalan Raya',
        'location' => '2.G.1.042',
        'supervisor_name' => 'Tc. Muhamad Khairul Fitri bin Sarimin',
        'supervisor_email' => 'khairulfs@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Mekanik Bendalir',
        'location' => '2.F.1.047',
        'supervisor_name' => 'Tc. Mohd Faizal Riza bin Kamian',
        'supervisor_email' => 'faizalr@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Perkhidmatan Bangunan',
        'location' => '2.G.1.018',
        'supervisor_name' => 'Nadia binti Kasim',
        'supervisor_email' => 'nadiakasim@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Struktur Ringan',
        'location' => '2.G.2.003',
        'supervisor_name' => 'Tc. Nurhayani binti Ujurmudi',
        'supervisor_email' => 'nurhayani@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Sumber Air',
        'location' => '2.F.1.042',
        'supervisor_name' => 'Harina binti Md Amin',
        'supervisor_email' => 'harina@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Teknologi Kejuruteraan Trafik',
        'location' => '2.G.2.021',
        'supervisor_name' => 'Siti Nadia Syuhada binti Mohd Satti',
        'supervisor_email' => 'sitinadia@uthm.edu.my'
    ],
    [
        'clusterId' => 'civil-chemical',
        'name' => 'Makmal Termodinamik (KTKAK)',
        'location' => '2.H.2.001',
        'supervisor_name' => 'Nur Farrah Ain binti SM Bakri',
        'supervisor_email' => 'nurfarrah@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Asas Elektrik Dan Elektronik 1',
        'location' => '2.B.3.027',
        'supervisor_name' => 'Muhamad Asyraf bin Mohammad Hamin',
        'supervisor_email' => 'asyraf@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Asas Elektrik Dan Elektronik 2',
        'location' => '2.B.3.021',
        'supervisor_name' => 'Tc. Nor Azizah binti Arif',
        'supervisor_email' => 'naziza@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Bahasa Antarabangsa',
        'location' => '2.A1.1.026',
        'supervisor_name' => 'Maizatulfiza binti Yahya',
        'supervisor_email' => 'fizayahya@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Baikpulih Komputer',
        'location' => '2.J.3.039',
        'supervisor_name' => 'Muhamad Hisamuddin bin Pasori',
        'supervisor_email' => 'hisamuddin@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Elektronik Kuasa',
        'location' => '2.B.1.001',
        'supervisor_name' => 'Saidatul Nazriyah binti Rosli',
        'supervisor_email' => 'saidatul@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Grafik & Animasi',
        'location' => '2.H.3.004',
        'supervisor_name' => 'Tc. Muhd Amin bin Saad',
        'supervisor_email' => 'muhdamin@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Gunasama Komputer',
        'location' => '2.J.3.010',
        'supervisor_name' => 'Muhamad Hisamuddin bin Pasori',
        'supervisor_email' => 'hisamuddin@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Komputer Sava',
        'location' => '2.H.3.013',
        'supervisor_name' => 'Mohd Niza bin Samsudin',
        'supervisor_email' => 'niza@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Mikropengawal',
        'location' => '2.B.2.014',
        'supervisor_name' => 'Mohd Shahnas bin Jamaludin',
        'supervisor_email' => 'shahnas@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Pengaturcaraan Internet',
        'location' => '2.J.3.038',
        'supervisor_name' => 'Muhamad Hisamuddin bin Pasori',
        'supervisor_email' => 'hisamuddin@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Pepasangan Elektrik',
        'location' => '2.B.2.024',
        'supervisor_name' => 'Izam Iskandar bin Abdullah',
        'supervisor_email' => 'izam@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Peralatan Dan Pengujian',
        'location' => '2.B.1.047',
        'supervisor_name' => 'Mohamad Syah Rizal bin Abdullah',
        'supervisor_email' => 'syahrizal@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Projek',
        'location' => '2.B.3.001',
        'supervisor_name' => 'Issam Suhari bin Iskandar',
        'supervisor_email' => 'issam@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Projek Diploma 1',
        'location' => '2.H.3.001',
        'supervisor_name' => 'Mohd Niza bin Samsudin',
        'supervisor_email' => 'niza@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Projek Diploma 2',
        'location' => '2.H.3.003',
        'supervisor_name' => 'Tc. Muhd Amin bin Saad',
        'supervisor_email' => 'muhdamin@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Rekabentuk Berbantu Komputer',
        'location' => '2.B.3.013',
        'supervisor_name' => 'Muhamad Asyraf bin Mohammad Hamin',
        'supervisor_email' => 'asyraf@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Automasi Industri (IIOT-STM)',
        'location' => '2.B.2.010',
        'supervisor_name' => 'Fadzil bin Esa',
        'supervisor_email' => 'fadzil@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Elektronik & Digit 2',
        'location' => '2.B.4.001',
        'supervisor_name' => 'Muhammad Helmi bin Khamis',
        'supervisor_email' => 'helmi@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Elektronik Dan Digit 1',
        'location' => '2.B.4.025',
        'supervisor_name' => 'Muhammad Zulhilmi bin Md Nor',
        'supervisor_email' => 'mzulhilmi@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Jalur Lebar Tanpa Wayar',
        'location' => '2.B.4.005',
        'supervisor_name' => 'Ahyat bin Mohamed Zaini',
        'supervisor_email' => 'ahyat@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Kawalan Industri',
        'location' => '2.B.2.020',
        'supervisor_name' => 'Fadzil bin Esa',
        'supervisor_email' => 'fadzil@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Kuasa Elektrik',
        'location' => '2.B.1.018',
        'supervisor_name' => 'Saidatul Nazriyah binti Rosli',
        'supervisor_email' => 'saidatul@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Mesin Dan Pemacu',
        'location' => '2.B.1.005',
        'supervisor_name' => 'Mohd Shahnas bin Jamaludin',
        'supervisor_email' => 'shahnas@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Mikrokomputer',
        'location' => '2.B.2.016',
        'supervisor_name' => 'Izam Iskandar bin Abdullah',
        'supervisor_email' => 'izam@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Multimedia Dan Penyiaran (Cybersecurity)',
        'location' => '2.B.4.010',
        'supervisor_name' => 'Muhammad Helmi bin Khamis',
        'supervisor_email' => 'helmi@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Papan Litar Tercetak',
        'location' => '2.B.1.033',
        'supervisor_name' => 'Abdul Hariz bin Ahmad',
        'supervisor_email' => 'abdulhariz@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Pemasangan Dan Pembuatan',
        'location' => '2.B.2.005',
        'supervisor_name' => 'Abdul Hariz bin Ahmad',
        'supervisor_email' => 'abdulhariz@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Pengukuran & Peralatan 1',
        'location' => '2.B.2.040',
        'supervisor_name' => 'Muhammad Zulhilmi bin Md Nor',
        'supervisor_email' => 'mzulhilmi@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Pengukuran Dan Peralatan 2 (Makmal ICOE-Rel)',
        'location' => '2.B.3.039',
        'supervisor_name' => 'Amarudeen bin Amir',
        'supervisor_email' => 'amarudeen@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Pepasangan Industri',
        'location' => '2.B.1.010',
        'supervisor_name' => 'Izam Iskandar bin Abdullah',
        'supervisor_email' => 'izam@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Peranti Industri',
        'location' => '2.B.2.001',
        'supervisor_name' => 'Ahyat bin Mohamed Zaini',
        'supervisor_email' => 'ahyat@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Rangkaian Komputer',
        'location' => '2.B.3.007',
        'supervisor_name' => 'Issam Suhari bin Iskandar',
        'supervisor_email' => 'issam@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Teknologi Sistem Komunikasi',
        'location' => '2.B.3.017',
        'supervisor_name' => 'Tc. Nor Azizah binti Arif',
        'supervisor_email' => 'naziza@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Tenaga Boleh Diperbaharui',
        'location' => '2.B.1.024',
        'supervisor_name' => 'Mohamad Syah Rizal bin Abdullah',
        'supervisor_email' => 'syahrizal@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Umum Bahasa Inggeris 1',
        'location' => '2.A1.1.024',
        'supervisor_name' => 'Maizatulfiza binti Yahya',
        'supervisor_email' => 'fizayahya@uthm.edu.my'
    ],
    [
        'clusterId' => 'electrical-multimedia',
        'name' => 'Makmal Umum Bahasa Inggeris 2',
        'location' => '2.A1.1.025',
        'supervisor_name' => 'Maizatulfiza binti Yahya',
        'supervisor_email' => 'fizayahya@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Dinamik Kenderaan',
        'location' => '2.C.1.013',
        'supervisor_name' => 'Muhammad Izzat bin Che Mangsor',
        'supervisor_email' => 'izzatm@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Kejuruteraan Teknologi Loji',
        'location' => '2.D.1.052',
        'supervisor_name' => 'Mohd Zul Haffizi bin Mohd Sihat',
        'supervisor_email' => 'zulhaffizi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Automotif',
        'location' => '2.C.1.006',
        'supervisor_name' => 'Mohamed Ihsan Sabri bin Mohamed Nazar',
        'supervisor_email' => 'ihsann@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Celupan Dan Kemasan',
        'location' => '2.D.1.047',
        'supervisor_name' => 'Ahmad Yazid bin Buang',
        'supervisor_email' => 'yazid@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Elektrik Automotif',
        'location' => '2.D.1.031',
        'supervisor_name' => 'Mohammad Khidhir bin Mohd Sharif',
        'supervisor_email' => 'khidhir@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Kejuruteraan Mekanikal Teaching Factory',
        'location' => '2.K.1.001 (2)',
        'supervisor_name' => 'Mahathir bin Mun Talib',
        'supervisor_email' => 'mahathirm@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Kejuruteraan Mekanikal Teaching Factory',
        'location' => '2.K.1.005',
        'supervisor_name' => 'Mahathir bin Mun Talib',
        'supervisor_email' => 'mahathirm@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Kejuruteraan Mekanikal Teaching Factory',
        'location' => '2.K.1.026',
        'supervisor_name' => 'Mahathir bin Mun Talib',
        'supervisor_email' => 'mahathirm@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Kejuruteraan Mekanikal Teaching Factory',
        'location' => '2.K.1.006',
        'supervisor_name' => 'Mahathir bin Mun Talib',
        'supervisor_email' => 'mahathirm@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Kimpalan',
        'location' => '2.C.1.023',
        'supervisor_name' => 'Muhd Syafiq bin Ayub',
        'supervisor_email' => 'syafiqayub@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Pembuatan Moden',
        'location' => '2.E.1.004',
        'supervisor_name' => 'Muhammad Zaidi bin Jaafar',
        'supervisor_email' => 'mzaidi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Pemesinan Asas',
        'location' => '2.C.1.030',
        'supervisor_name' => 'Muhammad Khalis bin Daut',
        'supervisor_email' => 'khalis@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Bengkel Teknologi Tuangan Logam',
        'location' => '2.E.1.002',
        'supervisor_name' => 'Faiq Khairi bin Suhaimi',
        'supervisor_email' => 'faiqkhairi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal CFD (Computer Fluid Dynamics)',
        'location' => '2.C.4.017',
        'supervisor_name' => 'Mohd Akmal Hakim bin Razak',
        'supervisor_email' => 'akmalhakim@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Getaran Dan Kebisingan',
        'location' => '2.E.1.011',
        'supervisor_name' => 'Muhammad Amiruddin bin Hassan Al Ashari',
        'supervisor_email' => 'amiruddinh@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Instrumentasi Dan Kawalan',
        'location' => '2.D.3.001',
        'supervisor_name' => 'Mohammad Khidhir bin Mohd Sharif',
        'supervisor_email' => 'khidhir@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Instrumentasi Loji',
        'location' => '2.D.3.017',
        'supervisor_name' => 'Muhammad Amiruddin bin Hassan Al Ashari',
        'supervisor_email' => 'amiruddinh@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Lukisan Kejuruteraan Mekanikal 1',
        'location' => '2.C.4.002',
        'supervisor_name' => 'Salihudin bin Abd.Razak',
        'supervisor_email' => 'salih@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Lukisan Kejuruteraan Mekanikal 2',
        'location' => '2.C.2.015',
        'supervisor_name' => 'Faiq Khairi bin Suhaimi',
        'supervisor_email' => 'faiqkhairi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Mekanik Bendalir',
        'location' => '2.D.2.017',
        'supervisor_name' => 'Muhammad Hanif bin Ismail',
        'supervisor_email' => 'hanifbi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Mekanik Mesin',
        'location' => '2.C.1.034',
        'supervisor_name' => 'Mohd Nazrin bin Ya\'akof',
        'supervisor_email' => 'mnazrin@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Mekanik Pepejal',
        'location' => '2.C.3.016',
        'supervisor_name' => 'Mohd Nazrin bin Ya\'akof',
        'supervisor_email' => 'mnazrin@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Pengujian Keselesaan Terma',
        'location' => '2.E.1.023',
        'supervisor_name' => 'Ahmad Syakir bin Mohamad Jamil',
        'supervisor_email' => 'syakir@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Pengujian Tekstil',
        'location' => '2.E.1.030',
        'supervisor_name' => 'Tc. Mohd Sahrill bin Wagiman',
        'supervisor_email' => 'msahrill@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Projek Loji',
        'location' => '2.C.1.019',
        'supervisor_name' => 'Muhammad Zaidi bin Jaafar',
        'supervisor_email' => 'mzaidi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Projek Teknologi Pembungkusan',
        'location' => '2.D.1.035',
        'supervisor_name' => 'Mohamad Firdaus bin Saat',
        'supervisor_email' => 'mfirdauss@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Sains Bahan',
        'location' => '2.C.2.001',
        'supervisor_name' => 'Muhamad Riduan bin Basri',
        'supervisor_email' => 'mriduan@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Sistem Pengujian',
        'location' => '2.D.1.029',
        'supervisor_name' => 'Norsahidah binti Abdullah',
        'supervisor_email' => 'norsahidah@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Statik Dan Dinamik',
        'location' => '2.C.2.019',
        'supervisor_name' => 'Salihudin bin Abd.Razak',
        'supervisor_email' => 'salih@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Apparel',
        'location' => '2.C.3.001',
        'supervisor_name' => 'Mohamad Shamirul Asyraf bin Mohamad Azmy',
        'supervisor_email' => 'shamirul@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Automasi Industri Dan Robotik',
        'location' => '2.D.4.005',
        'supervisor_name' => 'Mohd Akmal Hakim bin Razak',
        'supervisor_email' => 'akmalhakim@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Bahan Pembungkusan',
        'location' => '2.D.1.019',
        'supervisor_name' => 'Norsahidah binti Abdullah',
        'supervisor_email' => 'norsahidah@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Bukan Tenun',
        'location' => '2.E.1.039',
        'supervisor_name' => 'Ahmad Yazid bin Buang',
        'supervisor_email' => 'yazid@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi CAD/CAM',
        'location' => '2.C.4.001',
        'supervisor_name' => 'Kamarul Qawiem bin Md. Som',
        'supervisor_email' => 'qawiem@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Fabrikasi',
        'location' => '2.E.1.014',
        'supervisor_name' => 'Ahmad Syakir bin Mohamad Jamil',
        'supervisor_email' => 'syakir@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Industri Dan Ergonomik',
        'location' => '2.C.4.013',
        'supervisor_name' => 'Muhammad Khalis bin Daut',
        'supervisor_email' => 'khalis@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Kait',
        'location' => '2.D.3.005',
        'supervisor_name' => 'Muhammad Izzat bin Che Mangsor',
        'supervisor_email' => 'izzatm@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Komponen Pembungkusan',
        'location' => '2.D.2.001',
        'supervisor_name' => 'Mohamad Amirul Syafuan bin Sukimin',
        'supervisor_email' => 'amiruls@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Mekanikal Loji',
        'location' => '2.C.1.045',
        'supervisor_name' => 'Mohamed Ihsan Sabri bin Mohamed Nazar',
        'supervisor_email' => 'ihsann@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Mesin Pembungkusan',
        'location' => '2.D.1.025',
        'supervisor_name' => 'Mohamad Firdaus bin Saat',
        'supervisor_email' => 'mfirdauss@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Pemesinan Berbantu Komputer',
        'location' => '2.E.1.020',
        'supervisor_name' => 'Mohd Zul Haffizi bin Mohd Sihat',
        'supervisor_email' => 'zulhaffizi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Pengukuran (Metrologi)',
        'location' => '2.C.1.001',
        'supervisor_name' => 'Muhamad Riduan bin Basri',
        'supervisor_email' => 'mriduan@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Pintalan',
        'location' => '2.E.1.033',
        'supervisor_name' => 'Tc. Mohd Sahrill bin Wagiman',
        'supervisor_email' => 'msahrill@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Rekabentuk Dan Simulasi Pembungkusan',
        'location' => '2.D.2.005',
        'supervisor_name' => 'Mohamad Amirul Syafuan bin Sukimin',
        'supervisor_email' => 'amiruls@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Sistem Automotif',
        'location' => '2.D.1.007',
        'supervisor_name' => 'Muhammad Hanif bin Ismail',
        'supervisor_email' => 'hanifbi@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Teknologi Tenunan',
        'location' => '2.D.1.001',
        'supervisor_name' => 'Mohamad Shamirul Asyraf bin Mohamad Azmy',
        'supervisor_email' => 'shamirul@uthm.edu.my'
    ],
    [
        'clusterId' => 'mechanical-transport',
        'name' => 'Makmal Termodinamik (KTKMP)',
        'location' => '2.C.3.012',
        'supervisor_name' => 'Kamarul Qawiem bin Md. Som',
        'supervisor_email' => 'qawiem@uthm.edu.my'
    ],
];

$count = 0;
foreach ($labs as $lab) {
    $cluster_id = $cluster_map[$lab['clusterId']] ?? null;
    if (!$cluster_id) {
        continue;
    }
    $supervisor_id = ensure_supervisor(
        $mysqli,
        $cluster_id,
        $lab['supervisor_name'],
        $lab['supervisor_email'],
        $lab['location'] ?? ''
    );
    ensure_lab(
        $mysqli,
        $cluster_id,
        $lab['name'],
        $default_description,
        $lab['capacity'] ?? $default_capacity,
        $supervisor_id
    );
    $count++;
}

echo 'Cluster and lab seed complete. Labs processed: ' . $count;

