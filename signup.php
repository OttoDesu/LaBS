<?php
require_once __DIR__ . '/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$name = '';
$ic_no = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $ic_no = trim($_POST['ic_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }
    if (!preg_match('/^\d{12}$/', $ic_no)) {
        $errors[] = 'IC number must be exactly 12 digits.';
    }
    if (!is_valid_public_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters, include one uppercase letter, and one number.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('INSERT INTO users (name, ic_no, email, password, user_type, created_at, updated_at) VALUES (?, ?, ?, ?, "public", NOW(), NOW())');
            $stmt->bind_param('ssss', $name, $ic_no, $email, $hashed);
            if ($stmt->execute()) {
                $stmt->close();
                set_flash('signup_success', 'Account created successfully. Please sign in to continue.');
                header('Location: index.php');
                exit;
            }
            $stmt->close();
            $errors[] = 'Unable to create account. Please try again.';
        }
    }
}

$flash_info = get_flash('info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Sign Up</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="split">
        <div class="split-left">
            <div class="card">
            <h1>Sign Up</h1>
            <p class="subtitle">Create your personal account!</p>

            <div class="note">
                <span class="note-icon">
                    <svg
                        class="w-5 h-5 text-blue-500 dark:text-blue-400 mr-2 mt-0.5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </span>
                <span>Note: This sign up is for public users only. UTHM staff/students should contact their administrator for account setup.</span>
            </div>

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

            <form id="signup-form" method="POST" action="signup.php" novalidate>
                <div class="field-group">
                    <div class="error" id="signup-name-error"></div>
                    <label for="name">Full Name<span class="required">*</span></label>
                    <input type="text" id="name" name="name" placeholder="John Doe" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>

                <div class="field-group">
                    <div class="error" id="signup-ic-error"></div>
                    <label for="ic_no">IC Number<span class="required">*</span></label>
                    <input type="text" id="ic_no" name="ic_no" placeholder="Example: 990101011234" value="<?php echo htmlspecialchars($ic_no); ?>" maxlength="12" required>
                    <p class="hint">Enter 12-digit IC number without dashes or spaces</p>
                </div>

                <div class="field-group">
                    <div class="error" id="signup-email-error"></div>
                    <label for="email">Email Address<span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="JohnDoe@gmail.com" value="<?php echo htmlspecialchars($email); ?>" required>
                    <p class="hint" id="signup-email-hint">Complete your name and IC to unlock email input</p>
                </div>

                <div class="field-group">
                    <div class="error" id="signup-password-error"></div>
                    <label for="password">Password<span class="required">*</span></label>
                    <div class="input-with-icon">
                        <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                        <button type="button" class="toggle-visibility" data-target="password" aria-label="Show password"></button>
                    </div>
                    <p class="hint" id="signup-password-hint">Enter a valid email to continue to password setup.</p>
                    <ul class="rule-list">
                        <li id="rule-length">At least 8 characters</li>
                        <li id="rule-upper">One uppercase letter</li>
                        <li id="rule-number">One number</li>
                    </ul>
                </div>

                <div class="field-group">
                    <div class="error" id="signup-confirm-error"></div>
                    <label for="confirm_password">Confirm Password<span class="required">*</span></label>
                    <div class="input-with-icon">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-visibility" data-target="confirm_password" aria-label="Show password"></button>
                    </div>
                    <p class="hint" id="signup-confirm-hint">Complete the password requirements to unlock confirmation.</p>
                </div>

                <button type="submit" id="signup-submit" class="primary" disabled>Create Account</button>
            </form>

            <div class="helper-text">
                <p>Already have an account? <a href="index.php">Sign In</a></p>
                <p class="muted">UTHM staff/students: Please contact your department administrator for account access.</p>
            </div>
        </div>
        </div>
        <div class="split-right">
            <img src="img/labs_logo.png" alt="LaBS PPMKCP" class="hero-logo">
        </div>
    </div>
    <script src="assets/validation.js"></script>
</body>
</html>
