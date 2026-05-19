<?php
require_once __DIR__ . '/init.php';
require_login();

$user_type = $_SESSION['user_type'] ?? 'public';
require_management();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$admin_cluster_id = get_admin_cluster_id();
$is_super_admin = is_super_admin($user_type);
$is_lab_supervisor = is_lab_supervisor($user_type);
$can_edit_assets = $is_super_admin || $is_lab_supervisor;
$lab_scope_ids = $is_lab_supervisor ? get_lab_supervisor_lab_ids($mysqli, $user_id) : [];
$lab_id = isset($_GET['lab']) ? (int) $_GET['lab'] : 0;
$flash_info = get_flash('info');
$flash_error = get_flash('error');
$today_date = date('Y-m-d');
$today_js = htmlspecialchars($today_date, ENT_QUOTES);

function ensure_asset_image_column(mysqli $mysqli) {
    $column_exists = false;
    $column_stmt = $mysqli->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE()
          AND table_name = 'assets'
          AND column_name = 'asset_image_path'
        LIMIT 1
    ");
    if ($column_stmt) {
        $column_stmt->execute();
        $column_result = $column_stmt->get_result();
        $column_exists = (bool) $column_result->fetch_assoc();
        $column_stmt->close();
    }

    if (!$column_exists) {
        $mysqli->query("ALTER TABLE assets ADD COLUMN asset_image_path VARCHAR(255) DEFAULT NULL AFTER asset_count");
    }
}

function upload_asset_image($field_name, &$error_message = null) {
    if (empty($_FILES[$field_name]) || !is_array($_FILES[$field_name])) {
        return null;
    }

    $file = $_FILES[$field_name];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $error_message = 'Asset image upload failed.';
        return false;
    }

    $original_name = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($extension, $allowed_extensions, true)) {
        $error_message = 'Asset image must be JPG, PNG, WEBP, or GIF.';
        return false;
    }

    $upload_directory = __DIR__ . '/uploads/assets';
    if (!is_dir($upload_directory) && !mkdir($upload_directory, 0775, true) && !is_dir($upload_directory)) {
        $error_message = 'Unable to create asset image directory.';
        return false;
    }

    $target_name = 'asset_' . uniqid('', true) . '.' . $extension;
    $target_path = $upload_directory . '/' . $target_name;
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        $error_message = 'Unable to save asset image.';
        return false;
    }

    return 'uploads/assets/' . $target_name;
}

function get_allowed_asset_statuses() {
    return ['Available', 'Unavailable', 'Disposed'];
}

ensure_asset_image_column($mysqli);

if ($lab_id <= 0) {
    set_flash('error', 'Please select a lab.');
    header('Location: assets-management.php');
    exit;
}

if (!$is_super_admin) {
    if ($is_lab_supervisor) {
        if (!in_array($lab_id, $lab_scope_ids, true)) {
            set_flash('error', 'You do not have permission to access that lab.');
            header('Location: assets-management.php');
            exit;
        }
    } else {
        $access_stmt = $mysqli->prepare('SELECT lab_id FROM labs WHERE lab_id = ? AND cluster_id = ?');
        $access_stmt->bind_param('ii', $lab_id, $admin_cluster_id);
        $access_stmt->execute();
        $access_result = $access_stmt->get_result();
        $allowed = (bool) $access_result->fetch_assoc();
        $access_stmt->close();
        if (!$allowed) {
            set_flash('error', 'You do not have permission to access that lab.');
            header('Location: assets-management.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit_assets) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_asset') {
        $posted_lab_id = (int) ($_POST['lab_id'] ?? 0);
        $asset_name = trim((string) ($_POST['asset_name'] ?? ''));
        $asset_status = trim((string) ($_POST['asset_status'] ?? ''));
        $asset_count = max(0, (int) ($_POST['asset_count'] ?? 0));
        $upload_error = null;
        $asset_image_path = upload_asset_image('asset_image', $upload_error);

        if ($posted_lab_id !== $lab_id) {
            set_flash('error', 'Invalid lab selected.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if ($asset_name === '') {
            set_flash('error', 'Asset name is required.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if (!in_array($asset_status, get_allowed_asset_statuses(), true)) {
            set_flash('error', 'Asset status is invalid.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if ($asset_image_path === false) {
            set_flash('error', $upload_error ?: 'Asset image upload failed.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        $insert_stmt = $mysqli->prepare('
            INSERT INTO assets (lab_id, asset_name, asset_status, asset_count, asset_image_path, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $insert_stmt->bind_param('issis', $lab_id, $asset_name, $asset_status, $asset_count, $asset_image_path);
        $insert_stmt->execute();
        $insert_stmt->close();

        set_flash('info', 'Asset added successfully.');
        header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
        exit;
    }

    if ($action === 'update_asset_details') {
        $asset_id = (int) ($_POST['asset_id'] ?? 0);
        $posted_lab_id = (int) ($_POST['lab_id'] ?? 0);
        $asset_name = trim((string) ($_POST['asset_name'] ?? ''));
        $asset_status = trim((string) ($_POST['asset_status'] ?? ''));
        $asset_count = max(0, (int) ($_POST['asset_count'] ?? 0));
        $current_image_path = trim((string) ($_POST['current_asset_image_path'] ?? ''));
        $clear_asset_image = (int) ($_POST['clear_asset_image'] ?? 0) === 1;
        $upload_error = null;
        $new_asset_image_path = upload_asset_image('asset_image', $upload_error);

        if ($asset_id <= 0 || $posted_lab_id !== $lab_id) {
            set_flash('error', 'Invalid asset selected.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if ($asset_name === '') {
            set_flash('error', 'Asset name is required.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if (!in_array($asset_status, get_allowed_asset_statuses(), true)) {
            set_flash('error', 'Asset status is invalid.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if ($new_asset_image_path === false) {
            set_flash('error', $upload_error ?: 'Asset image upload failed.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        $final_image_path = $new_asset_image_path ?: ($clear_asset_image ? null : ($current_image_path !== '' ? $current_image_path : null));
        $update_stmt = $mysqli->prepare('
            UPDATE assets
            SET asset_name = ?, asset_status = ?, asset_count = ?, asset_image_path = ?, updated_at = NOW()
            WHERE asset_id = ? AND lab_id = ?
        ');
        $update_stmt->bind_param('ssisii', $asset_name, $asset_status, $asset_count, $final_image_path, $asset_id, $lab_id);
        $update_stmt->execute();
        $update_stmt->close();

        if (($new_asset_image_path || $clear_asset_image) && $current_image_path !== '' && $current_image_path !== $new_asset_image_path) {
            $old_file = __DIR__ . '/' . ltrim($current_image_path, '/');
            if (is_file($old_file)) {
                @unlink($old_file);
            }
        }

        set_flash('info', 'Asset details updated successfully.');
        header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
        exit;
    }

    if ($action === 'disable_asset') {
        $asset_id = (int) ($_POST['asset_id'] ?? 0);
        $posted_lab_id = (int) ($_POST['lab_id'] ?? 0);

        if ($asset_id <= 0 || $posted_lab_id !== $lab_id) {
            set_flash('error', 'Invalid asset selected.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        $disable_stmt = $mysqli->prepare('
            UPDATE assets
            SET asset_status = "Unavailable", updated_at = NOW()
            WHERE asset_id = ? AND lab_id = ?
        ');
        $disable_stmt->bind_param('ii', $asset_id, $lab_id);
        $disable_stmt->execute();
        $disable_stmt->close();

        set_flash('info', 'Asset marked as unavailable.');
        header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
        exit;
    }

    if ($action === 'update_lab_details') {
        $posted_lab_id = (int) ($_POST['lab_id'] ?? 0);
        $lab_name = trim((string) ($_POST['lab_name'] ?? ''));
        $lab_capacity = max(0, (int) ($_POST['lab_capacity'] ?? 0));
        $lab_description = trim((string) ($_POST['lab_description'] ?? ''));
        $maintenance_status = trim((string) ($_POST['maintenance_status'] ?? 'available'));
        $maintenance_start_date = trim((string) ($_POST['maintenance_start_date'] ?? ''));
        $maintenance_end_date = trim((string) ($_POST['maintenance_end_date'] ?? ''));

        if ($posted_lab_id !== $lab_id) {
            set_flash('error', 'Invalid lab selected.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if ($lab_name === '') {
            set_flash('error', 'Lab name is required.');
            header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
            exit;
        }

        if (!in_array($maintenance_status, ['available', 'maintenance'], true)) {
            $maintenance_status = 'available';
        }

        if ($maintenance_status !== 'maintenance') {
            $maintenance_start_date = null;
            $maintenance_end_date = null;
        } else {
            if ($maintenance_start_date === '' || $maintenance_end_date === '') {
                set_flash('error', 'Maintenance start and end date are required.');
                header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
                exit;
            }
            if ($maintenance_start_date < $today_date || $maintenance_end_date < $today_date) {
                set_flash('error', 'Maintenance dates cannot be in the past.');
                header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
                exit;
            }
            if ($maintenance_start_date > $maintenance_end_date) {
                set_flash('error', 'Maintenance end date must be after start date.');
                header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
                exit;
            }
        }

        $update_stmt = $mysqli->prepare('
            UPDATE labs
            SET lab_name = ?, lab_capacity = ?, lab_description = ?, maintenance_status = ?, maintenance_start_date = ?, maintenance_end_date = ?
            WHERE lab_id = ?
        ');
        $update_stmt->bind_param(
            'sissssi',
            $lab_name,
            $lab_capacity,
            $lab_description,
            $maintenance_status,
            $maintenance_start_date,
            $maintenance_end_date,
            $lab_id
        );
        $update_stmt->execute();
        $update_stmt->close();

        set_flash('info', 'Lab details updated successfully.');
        header('Location: assets-management-lab.php?lab=' . (int) $lab_id);
        exit;
    }
}

$lab_stmt = $mysqli->prepare('
    SELECT
        l.lab_id,
        l.lab_name,
        l.lab_description,
        l.lab_capacity,
        l.maintenance_status,
        l.maintenance_start_date,
        l.maintenance_end_date,
        c.cluster_id,
        c.cluster_name,
        c.cluster_description,
        COALESCE(s.supervisor_id, 0) AS supervisor_id,
        COALESCE(s.supervisor_name, ?) AS supervisor_name,
        COALESCE(s.supervisor_email, ?) AS supervisor_email,
        COALESCE(s.supervisor_room_no, "-") AS supervisor_room_no
    FROM labs l
    JOIN clusters c ON c.cluster_id = l.cluster_id
    LEFT JOIN supervisors s ON s.supervisor_id = l.supervisor_id
    WHERE l.lab_id = ?
    LIMIT 1
');
$fallback_name = $_SESSION['user_name'] ?? 'Unassigned';
$fallback_email = $_SESSION['user_email'] ?? '-';
$lab_stmt->bind_param('ssi', $fallback_name, $fallback_email, $lab_id);
$lab_stmt->execute();
$lab_result = $lab_stmt->get_result();
$lab = $lab_result->fetch_assoc();
$lab_stmt->close();

if (!$lab) {
    set_flash('error', 'Lab not found.');
    header('Location: assets-management.php');
    exit;
}

$assets = [];
$assets_stmt = $mysqli->prepare('
    SELECT asset_id, asset_name, asset_status, asset_count, asset_image_path
    FROM assets
    WHERE lab_id = ?
    ORDER BY asset_name ASC
');
$assets_stmt->bind_param('i', $lab_id);
$assets_stmt->execute();
$assets_result = $assets_stmt->get_result();
while ($row = $assets_result->fetch_assoc()) {
    $assets[] = $row;
}
$assets_stmt->close();

$user_payload = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'userType' => $user_type
];
$layout_path = __DIR__ . '/templates/layouts/admin.php';
if ($is_lab_supervisor) {
    $layout_path = __DIR__ . '/templates/layouts/lab_supervisor.php';
}
$layout = require $layout_path;
$active = 'asset-management';
$app_css_version = @filemtime(__DIR__ . '/assets/app.css') ?: time();
$app_js_version = @filemtime(__DIR__ . '/assets/app.js') ?: time();
$status_options = get_allowed_asset_statuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Assets</title>
    <link rel="stylesheet" href="assets/app.css?v=<?php echo (int) $app_css_version; ?>">
</head>
<body data-login-url="index.php">
    <div class="app">
        <?php include __DIR__ . '/templates/layouts/sidebar.php'; ?>

        <div class="main">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="icon-button" id="toggle-sidebar" aria-label="Toggle sidebar">
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z" fill="currentColor"/>
                        </svg>
                    </button>
                    <div class="search">
                        <span class="search-icon">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z" fill="currentColor"/>
                            </svg>
                        </span>
                        <input type="text" id="global-search" placeholder="Search...">
                    </div>
                </div>
                <div class="topbar-right">
                    <button class="icon-button" aria-label="Notifications">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z" fill="currentColor"/>
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
                        <a class="menu-item" href="profile.php">Edit Profile</a>
                        <form method="POST" action="logout.php">
                            <button class="menu-item danger" type="submit">Sign Out</button>
                        </form>
                    </div>
                </div>
            </header>

            <section class="content">
                <?php if ($flash_error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($flash_error); ?></div>
                <?php endif; ?>
                <?php if ($flash_info): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($flash_info); ?></div>
                <?php endif; ?>

                <div class="content-header">
                    <div>
                        <h1>Lab Assets</h1>
                        <p><?php echo htmlspecialchars($lab['lab_name']); ?></p>
                    </div>
                    <div class="breadcrumb">Home / Asset Management / Lab</div>
                </div>

                <div class="section-stack">
                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2><?php echo htmlspecialchars($lab['cluster_name']); ?></h2>
                                <p><?php echo htmlspecialchars($lab['cluster_description']); ?></p>
                            </div>
                            <div class="banner-links">
                                <a class="btn ghost" href="assets-management.php">Back to assets</a>
                            </div>
                        </div>
                    </div>

                    <div class="cluster-grid">
                        <div class="cluster-card">
                            <div>
                                <h3><?php echo htmlspecialchars($lab['supervisor_name']); ?></h3>
                                <p><?php echo htmlspecialchars($lab['supervisor_email']); ?></p>
                                <p class="muted-text">Room: <?php echo htmlspecialchars($lab['supervisor_room_no'] ?: '-'); ?></p>
                                <p class="muted-text">Lab: <?php echo htmlspecialchars($lab['lab_name']); ?></p>
                            </div>
                            <div class="cluster-meta"><?php echo count($assets); ?> assets</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="banner">
                            <div>
                                <h2><?php echo htmlspecialchars($lab['lab_name']); ?></h2>
                                <p>Asset information for this assigned lab.</p>
                            </div>
                            <?php if ($can_edit_assets): ?>
                                <div class="banner-links">
                                    <button
                                        class="btn primary"
                                        type="button"
                                        data-modal="add-asset-modal"
                                    >
                                        Add Asset
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Asset Image</th>
                                        <th>Asset Name</th>
                                        <th>Asset Status</th>
                                        <th>Asset Count</th>
                                        <?php if ($can_edit_assets): ?>
                                            <th>Action</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assets as $index => $asset): ?>
                                        <tr>
                                            <td><?php echo (int) ($index + 1); ?></td>
                                            <td>
                                                <div class="asset-thumb">
                                                    <?php if (!empty($asset['asset_image_path']) && is_file(__DIR__ . '/' . ltrim($asset['asset_image_path'], '/'))): ?>
                                                        <img src="<?php echo htmlspecialchars($asset['asset_image_path']); ?>" alt="<?php echo htmlspecialchars($asset['asset_name']); ?>">
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars(strtoupper(substr($asset['asset_name'], 0, 2))); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                            <td><span class="asset-status status-<?php echo htmlspecialchars(strtolower($asset['asset_status'])); ?>"><?php echo htmlspecialchars($asset['asset_status']); ?></span></td>
                                            <td><?php echo (int) $asset['asset_count']; ?></td>
                                            <?php if ($can_edit_assets): ?>
                                                <td>
                                                    <button
                                                        class="btn ghost small edit-asset-details"
                                                        type="button"
                                                        data-asset-id="<?php echo (int) $asset['asset_id']; ?>"
                                                        data-lab-id="<?php echo (int) $lab['lab_id']; ?>"
                                                        data-asset-name="<?php echo htmlspecialchars($asset['asset_name']); ?>"
                                                        data-asset-status="<?php echo htmlspecialchars($asset['asset_status']); ?>"
                                                        data-asset-count="<?php echo htmlspecialchars((string) $asset['asset_count']); ?>"
                                                        data-asset-image-path="<?php echo htmlspecialchars($asset['asset_image_path'] ?? ''); ?>"
                                                    >
                                                        Edit
                                                    </button>
                                                    <form method="POST" class="inline-action-form" onsubmit="return confirm('Mark this asset as unavailable?');">
                                                        <input type="hidden" name="action" value="disable_asset">
                                                        <input type="hidden" name="asset_id" value="<?php echo (int) $asset['asset_id']; ?>">
                                                        <input type="hidden" name="lab_id" value="<?php echo (int) $lab['lab_id']; ?>">
                                                        <button class="btn danger small" type="submit">Disable</button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$assets): ?>
                                        <tr>
                                            <td colspan="<?php echo $can_edit_assets ? '6' : '5'; ?>">No assets found for this lab.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <footer class="footer">Ac Copyright 2025 LaBS PPMKCP. All Rights Reserved.</footer>
            </section>
        </div>
    </div>

    <?php if ($can_edit_assets): ?>
        <div class="modal" id="add-asset-modal">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_asset">
                    <input type="hidden" name="lab_id" value="<?php echo (int) $lab_id; ?>">
                    <div class="modal-header">
                        <h2>Add Asset</h2>
                        <button class="icon-button" type="button" data-close="add-asset-modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <label>Asset Image Preview</label>
                            <div class="asset-image-preview is-empty" id="add-asset-image-preview">
                                <span>No image selected.</span>
                            </div>
                        </div>
                        <div>
                            <label for="asset-image">Asset Image</label>
                            <input id="asset-image" name="asset_image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif">
                        </div>
                        <div>
                            <label for="asset-name">Asset Name</label>
                            <input id="asset-name" name="asset_name" type="text" required>
                        </div>
                        <div>
                            <label for="asset-status">Status</label>
                            <select id="asset-status" name="asset_status" required>
                                <option value="">Select status</option>
                                <?php foreach ($status_options as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars($status_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="asset-count">Count</label>
                            <input id="asset-count" name="asset_count" type="number" min="0" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn ghost" type="button" data-close="add-asset-modal">Cancel</button>
                        <button class="btn primary" type="submit">Save Asset</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="edit-asset-details-modal">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_asset_details">
                    <input type="hidden" name="asset_id" id="edit-asset-id">
                    <input type="hidden" name="lab_id" id="edit-asset-lab-id">
                    <input type="hidden" name="current_asset_image_path" id="edit-current-asset-image-path">
                    <input type="hidden" name="clear_asset_image" id="edit-clear-asset-image" value="0">
                    <div class="modal-header">
                        <h2>Edit Asset Details</h2>
                        <button class="icon-button" type="button" data-close="edit-asset-details-modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4L12 12M12 4L4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <label>Current Asset Image</label>
                            <div class="asset-image-preview is-empty" id="edit-asset-image-preview">
                                <span>No image uploaded.</span>
                            </div>
                        </div>
                        <div class="asset-image-actions">
                            <button class="btn ghost small" type="button" id="clear-asset-image-btn">Clear Image</button>
                        </div>
                        <div>
                            <label for="edit-asset-image">Asset Image</label>
                            <input id="edit-asset-image" name="asset_image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif">
                        </div>
                        <div>
                            <label for="edit-asset-name">Asset Name</label>
                            <input id="edit-asset-name" name="asset_name" type="text" required>
                        </div>
                        <div>
                            <label for="edit-asset-status">Asset Status</label>
                            <select id="edit-asset-status" name="asset_status" required>
                                <?php foreach ($status_options as $status_option): ?>
                                    <option value="<?php echo htmlspecialchars($status_option); ?>"><?php echo htmlspecialchars($status_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="edit-asset-count">Bill of Assets</label>
                            <input id="edit-asset-count" name="asset_count" type="number" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn ghost" type="button" data-close="edit-asset-details-modal">Cancel</button>
                        <button class="btn primary" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        window.LABS_USER = <?php echo json_encode($user_payload); ?>;
        window.LABS_LOGIN_URL = 'index.php';
    </script>
    <script src="assets/app.js?v=<?php echo (int) $app_js_version; ?>"></script>
    <?php if ($can_edit_assets): ?>
        <script>
            (function () {
                var addAssetModal = document.getElementById('add-asset-modal');
                var editAssetModal = document.getElementById('edit-asset-details-modal');
                var addAssetImageInput = document.getElementById('asset-image');
                var addAssetImagePreview = document.getElementById('add-asset-image-preview');
                var editAssetImageInput = document.getElementById('edit-asset-image');
                var editAssetImagePreview = document.getElementById('edit-asset-image-preview');
                var editCurrentAssetImagePath = document.getElementById('edit-current-asset-image-path');
                var clearAssetImageInput = document.getElementById('edit-clear-asset-image');
                var clearAssetImageBtn = document.getElementById('clear-asset-image-btn');

                if (!editAssetModal) {
                    return;
                }

                function setPreview(previewNode, imagePath, emptyLabel) {
                    if (!previewNode) {
                        return;
                    }

                    if (!imagePath) {
                        previewNode.classList.add('is-empty');
                        previewNode.innerHTML = '<span>' + emptyLabel + '</span>';
                        return;
                    }

                    previewNode.classList.remove('is-empty');
                    previewNode.innerHTML = '<img src="' + imagePath + '" alt="Asset image preview">';
                }

                function readPreviewFromFile(inputNode, previewNode, fallbackPath, fallbackText) {
                    if (!inputNode || !previewNode) {
                        return;
                    }

                    if (inputNode.files && inputNode.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function (event) {
                            setPreview(previewNode, event.target.result, fallbackText);
                        };
                        reader.readAsDataURL(inputNode.files[0]);
                        return;
                    }

                    setPreview(previewNode, fallbackPath, fallbackText);
                }

                document.querySelectorAll('[data-modal="add-asset-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (addAssetModal) {
                            if (addAssetImageInput) {
                                addAssetImageInput.value = '';
                            }
                            setPreview(addAssetImagePreview, '', 'No image selected.');
                            addAssetModal.classList.add('active');
                        }
                    });
                });

                document.querySelectorAll('.edit-asset-details').forEach(function (button) {
                    button.addEventListener('click', function () {
                        document.getElementById('edit-asset-id').value = button.getAttribute('data-asset-id') || '';
                        document.getElementById('edit-asset-lab-id').value = button.getAttribute('data-lab-id') || '';
                        document.getElementById('edit-asset-name').value = button.getAttribute('data-asset-name') || '';
                        document.getElementById('edit-asset-status').value = button.getAttribute('data-asset-status') || 'Available';
                        document.getElementById('edit-asset-count').value = button.getAttribute('data-asset-count') || 0;
                        editCurrentAssetImagePath.value = button.getAttribute('data-asset-image-path') || '';
                        if (clearAssetImageInput) {
                            clearAssetImageInput.value = '0';
                        }
                        if (editAssetImageInput) {
                            editAssetImageInput.value = '';
                        }
                        setPreview(editAssetImagePreview, editCurrentAssetImagePath.value, 'No image uploaded.');
                        editAssetModal.classList.add('active');
                    });
                });

                if (addAssetImageInput) {
                    addAssetImageInput.addEventListener('change', function () {
                        readPreviewFromFile(addAssetImageInput, addAssetImagePreview, '', 'No image selected.');
                    });
                }

                if (editAssetImageInput) {
                    editAssetImageInput.addEventListener('change', function () {
                        if (clearAssetImageInput) {
                            clearAssetImageInput.value = '0';
                        }
                        readPreviewFromFile(editAssetImageInput, editAssetImagePreview, editCurrentAssetImagePath.value, 'No image uploaded.');
                    });
                }

                if (clearAssetImageBtn) {
                    clearAssetImageBtn.addEventListener('click', function () {
                        if (editAssetImageInput) {
                            editAssetImageInput.value = '';
                        }
                        if (clearAssetImageInput) {
                            clearAssetImageInput.value = '1';
                        }
                        setPreview(editAssetImagePreview, '', 'Image will be cleared on save.');
                    });
                }

                document.querySelectorAll('[data-close="add-asset-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (addAssetModal) {
                            addAssetModal.classList.remove('active');
                        }
                    });
                });

                document.querySelectorAll('[data-close="edit-asset-details-modal"]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        editAssetModal.classList.remove('active');
                    });
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
