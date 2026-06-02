<?php
require_once __DIR__ . '/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$code = trim((string) ($_POST['code'] ?? ''));
$new_password = '';
$confirm_password = '';
$flash_info = get_flash('info');
$flash_error = get_flash('error');
$active_reset_expires_at = null;

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $mysqli->prepare('
        SELECT expires_at
        FROM password_reset_codes
        WHERE email = ?
          AND used_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $active_reset = $result->fetch_assoc();
    $stmt->close();

    if ($active_reset && !empty($active_reset['expires_at'])) {
        $expires_at_object = DateTime::createFromFormat('Y-m-d H:i:s', $active_reset['expires_at']);
        if ($expires_at_object instanceof DateTime) {
            $active_reset_expires_at = $expires_at_object->format('c');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Verification code must be 6 digits.';
    }
    if ($new_password === '') {
        $errors[] = 'New password is required.';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($confirm_password === '') {
        $errors[] = 'Please confirm the new password.';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $stmt = $mysqli->prepare('
            SELECT pr.reset_id, pr.user_id, pr.code_hash, pr.expires_at
            FROM password_reset_codes pr
            WHERE pr.email = ?
              AND pr.used_at IS NULL
            ORDER BY pr.created_at DESC
            LIMIT 1
        ');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $reset_row = $result->fetch_assoc();
        $stmt->close();

        if (!$reset_row) {
            $errors[] = 'Reset code is invalid or has expired.';
        } else {
            $expires_at = DateTime::createFromFormat('Y-m-d H:i:s', $reset_row['expires_at']);
            $now = new DateTime('now');
            if (!$expires_at || $expires_at < $now) {
                $errors[] = 'Reset code is invalid or has expired.';
            } elseif (!hash_equals($reset_row['code_hash'], hash('sha256', $code))) {
                $errors[] = 'Verification code is incorrect.';
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = $mysqli->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
                $stmt->bind_param('si', $password_hash, $reset_row['user_id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $mysqli->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->close();

                set_flash('info', 'Password reset successful. Please sign in with your new password.');
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="split">
        <div class="split-left">
            <div class="card">
                <h1>Reset Password</h1>
                <p class="subtitle">Enter the verification code sent to your email and choose a new password.</p>

                <?php if ($flash_error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($flash_error); ?></div>
                <?php endif; ?>
                <?php if ($flash_info): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($flash_info); ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="reset-password.php" novalidate>
                    <div class="field-group">
                        <label for="email">Registered Email<span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Enter your registered email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <?php if ($active_reset_expires_at): ?>
                        <div class="note" id="reset-expiry-note" data-expires-at="<?php echo htmlspecialchars($active_reset_expires_at); ?>">
                            <span class="note-icon">i</span>
                            <div>
                                <strong>Code expires in 15 minutes.</strong>
                                <div class="hint" id="reset-expiry-countdown">Checking remaining time...</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="field-group">
                        <label for="code">Verification Code<span class="required">*</span></label>
                        <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" placeholder="Enter 6-digit code" value="<?php echo htmlspecialchars($code); ?>" required>
                    </div>

                    <div class="field-group">
                        <label for="new-password">New Password<span class="required">*</span></label>
                        <input type="password" id="new-password" name="new_password" placeholder="Enter new password" required>
                    </div>

                    <div class="field-group">
                        <label for="confirm-password">Confirm New Password<span class="required">*</span></label>
                        <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>

                    <button type="submit" class="primary">Reset Password</button>
                </form>

                <div class="helper-text">
                    <p><a href="forgot-password.php<?php echo $email !== '' ? '?email=' . urlencode($email) : ''; ?>">Resend code</a></p>
                    <p><a href="index.php">Back to Sign In</a></p>
                </div>
            </div>
        </div>
        <div class="split-right">
            <img src="img/labs_logo.png" alt="LaBS PPMKCP" class="hero-logo">
        </div>
    </div>
    <script>
        (function () {
            var expiryNote = document.getElementById('reset-expiry-note');
            var countdown = document.getElementById('reset-expiry-countdown');
            if (!expiryNote || !countdown) {
                return;
            }

            var expiresAt = expiryNote.getAttribute('data-expires-at');
            if (!expiresAt) {
                return;
            }

            function updateCountdown() {
                var expiresTime = new Date(expiresAt).getTime();
                var nowTime = Date.now();
                var diff = expiresTime - nowTime;

                if (diff <= 0) {
                    countdown.textContent = 'This verification code has expired. Please resend a new code.';
                    expiryNote.classList.add('notice-warning');
                    return;
                }

                var totalSeconds = Math.floor(diff / 1000);
                var minutes = Math.floor(totalSeconds / 60);
                var seconds = totalSeconds % 60;
                countdown.textContent = 'Time remaining: ' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            }

            updateCountdown();
            window.setInterval(updateCountdown, 1000);
        })();
    </script>
</body>
</html>
