<?php
require_once __DIR__ . '/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$flash_info = get_flash('info');
$flash_error = get_flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        $stmt = $mysqli->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $errors[] = 'No account found with that email.';
        } else {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $code_hash = hash('sha256', $code);
            $expires_at = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            $stmt = $mysqli->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE email = ? AND used_at IS NULL');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('
                INSERT INTO password_reset_codes (user_id, email, code_hash, expires_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ');
            $stmt->bind_param('isss', $user['id'], $user['email'], $code_hash, $expires_at);
            $stmt->execute();
            $reset_id = (int) $stmt->insert_id;
            $stmt->close();

            if (!send_password_reset_code_email($user['email'], $user['name'], $code)) {
                $stmt = $mysqli->prepare('DELETE FROM password_reset_codes WHERE reset_id = ?');
                $stmt->bind_param('i', $reset_id);
                $stmt->execute();
                $stmt->close();
                $errors[] = 'Unable to send reset code email. Please check mail configuration and try again.';
            } else {
                set_flash('info', 'A verification code has been sent to your email.');
                header('Location: reset-password.php?email=' . urlencode($user['email']));
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
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="split">
        <div class="split-left">
            <div class="card">
                <h1>Forgot Password</h1>
                <p class="subtitle">Enter the email used for your LaBS account. We will send a verification code to that email.</p>

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

                <form method="POST" action="forgot-password.php" novalidate>
                    <div class="field-group">
                        <label for="email">Registered Email<span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Enter your registered email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <p class="hint">The verification code is valid for 15 minutes.</p>
                    </div>

                    <button type="submit" class="primary">Send Verification Code</button>
                </form>

                <div class="helper-text">
                    <p><a href="index.php">Back to Sign In</a></p>
                </div>
            </div>
        </div>
        <div class="split-right">
            <img src="img/labs_logo.png" alt="LaBS PPMKCP" class="hero-logo">
        </div>
    </div>
</body>
</html>
