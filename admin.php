<?php
require_once __DIR__ . '/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!is_uthm_staff_email($email)) {
        $errors[] = 'Admin email must be in the format name@uthm.edu.my.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        $stmt = $mysqli->prepare("SELECT id, name, email, password, user_type, cluster_id FROM users WHERE email = ? AND user_type IN ('super_admin', 'cluster_admin', 'lab_supervisor', 'admin') LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid admin email or password.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="split">
        <div class="split-left">
            <div class="card">
                <h1>Admin Sign In</h1>
                <p class="subtitle">Enter your admin email and password to sign in.</p>

                <?php if ($errors): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form id="admin-login-form" method="POST" action="admin.php" novalidate>
                    <div class="field-group">
                        <div class="error" id="admin-email-error"></div>
                        <label for="email">Admin Email<span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="xxx@uthm.edu.my" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="field-group">
                        <div class="error" id="admin-password-error"></div>
                        <label for="password">Password<span class="required">*</span></label>
                        <div class="input-with-icon">
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-visibility" data-target="password" aria-label="Show password"></button>
                        </div>
                    </div>

                    <div class="row-between">
                        <label class="checkbox">
                            <input type="checkbox" checked disabled>
                            <span>Admin access</span>
                        </label>
                        <a class="link" href="#">Forgot password?</a>
                    </div>

                    <button type="submit" id="admin-submit" class="primary">Sign In as Admin</button>
                </form>

                <div class="helper-text">
                    <p>For general users, please use the <a href="index.php">standard sign in</a>.</p>
                </div>
            </div>
        </div>
        <div class="split-right">
            <img src="img/labs_logo.png" alt="LaBS PPMKCP" class="hero-logo">
        </div>
    </div>
    <script src="assets/admin-login.js"></script>
</body>
</html>
