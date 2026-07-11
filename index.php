<?php
require_once __DIR__ . '/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = '';
$user_type = 'uthm';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    if ($user_type === 'uthm') {
        if (!is_uthm_staff_email($email) && !is_uthm_student_email($email)) {
            $errors[] = 'UTHM email must be staffname@uthm.edu.my or matricno@student.uthm.edu.my.';
        }
    } elseif ($user_type === 'public' && !is_valid_public_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (!in_array($user_type, ['public', 'uthm'], true)) {
        $errors[] = 'Please select a user type.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        if ($user_type === 'uthm') {
            $stmt = $mysqli->prepare("SELECT id, name, email, password, user_type, cluster_id, is_active FROM users 
            WHERE email = ? AND user_type IN ('uthm_staff', 'uthm_student', 'super_admin', 'cluster_admin', 'lab_supervisor', 'admin') LIMIT 1");
            $stmt->bind_param('s', $email);
        } else {
            $stmt = $mysqli->prepare('SELECT id, name, email, password, user_type, cluster_id, is_active FROM users 
            WHERE email = ? AND user_type = ? LIMIT 1');
            $stmt->bind_param('ss', $email, $user_type);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user && $user_type === 'public') {
            set_flash('info', 'No public account found. Please create one.');
            header('Location: signup.php');
            exit;
        }

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ((int) ($user['is_active'] ?? 1) !== 1) {
            $errors[] = 'This account is inactive. Please contact the administrator.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['cluster_id'] = $user['cluster_id'];
            header('Location: dashboard.php');
            exit;
        }
    }
}

$flash_error = get_flash('error');
$flash_info = get_flash('info');
$flash_signup = get_flash('signup_success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Booking Sign In</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="split">
        <div class="split-left">
            <div class="card">
            <h1>Sign In</h1>

            <?php if ($flash_error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($flash_error); ?></div>
            <?php endif; ?>
            <?php if ($flash_info): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($flash_info); ?></div>
            <?php endif; ?>
            <?php if ($flash_signup): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($flash_signup); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="signin-form" method="POST" action="index.php" novalidate>
                <div class="field-group">
                    <label class="eyebrow">I am signing in as<span class="required">*</span></label>
                    <div class="role-grid">
                        <label class="role-card">
                            <input type="radio" name="user_type" value="uthm" <?php echo $user_type === 'uthm' ? 'checked' : ''; ?>>
                            <span class="role-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 4h16v16H4z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                    <path d="M8 8h8M8 12h8M8 16h5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="role-title">Warga UTHM</span>
                            <span class="role-subtitle">Staff or Student</span>
                        </label>
                        <label class="role-card">
                            <input type="radio" name="user_type" value="public" <?php echo $user_type === 'public' ? 'checked' : ''; ?>>
                            <span class="role-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                    <path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span class="role-title">Public User</span>
                            <span class="role-subtitle">Individual account</span>
                        </label>
                    </div>
                    <div class="error" id="signin-user-type-error"></div>
                </div>

                <div class="field-group">
                    <div class="error" id="signin-email-error"></div>
                    <label for="email">Email<span class="required">*</span> <span class="label-muted" id="email-note">(UTHM email only)</span></label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                    <p class="hint" id="email-hint">Students: matricno@student.uthm.edu.my | Staff: staffname@uthm.edu.my</p>
                </div>

                <div class="field-group">
                    <div class="error" id="signin-password-error"></div>
                    <label for="password">Password<span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="row-between">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Keep me logged in</span>
                    </label>
                    <a class="link" href="forgot-password.php">Forgot password?</a>
                </div>

                <button type="submit" id="signin-submit" class="primary">Sign In</button>
            </form>

            <div class="helper-text">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
                <p class="muted" id="signin-uthm-note">UTHM staff/students: Use your institutional credentials.</p>
            </div>
            <div class="notice warning" id="signin-public-note">
                <span class="notice-icon">!</span>
                <span>Public users: Please sign up to create a personal account before signing in.</span>
            </div>
        </div>
    </div>
        <div class="split-right">
            <img src="img/labs_logo.png" alt="LaBS PPMKCP" class="hero-logo">
        </div>
    </div>
    <script src="assets/validation.js"></script>
    <?php if ($flash_signup): ?>
        <script>
            alert(<?php echo json_encode($flash_signup); ?>);
        </script>
    <?php endif; ?>
</body>
</html>
