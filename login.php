
<?php
session_start();
include 'php/db.php';
include 'email_send.php';
include 'email_template.php';

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    header('Location: ./');
    exit;
}

function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $masked_name = strlen($name) > 2 ? substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : $name . '*';
    return $masked_name . '@' . $domain;
}

$popup_message = '';
$popup_type = 'info';
$step = $_POST['step'] ?? 'login';
$login_method = $_POST['login_method'] ?? 'password';

// Support popup messages via URL query parameters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['deleted']) && $_GET['deleted']) {
        $popup_message = 'Your account was deleted successfully. If you wish to use the system again, please register a new account.';
        $popup_type = 'success';
    } elseif (!empty($_GET['msg'])) {
        $popup_message = trim($_GET['msg']);
        $type = strtolower(trim($_GET['type'] ?? ''));
        if (in_array($type, ['success', 'danger', 'warning', 'info'], true)) {
            $popup_type = $type;
        }
    }
}

// Handle AJAX actions (OTP, password reset)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    header('Content-Type: application/json');

    if ($action === 'send_login_otp') {
        $username_or_email = trim($_POST['username_or_email'] ?? '');
        if (empty($username_or_email)) {
            echo json_encode(['success' => false, 'message' => 'Please enter username or email.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username_or_email, $username_or_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
                exit;
            }

            // Check if OTP was sent recently (30 seconds cooldown)
            if (isset($_SESSION['login_otp_sent']) && time() - $_SESSION['login_otp_sent'] < 30) {
                echo json_encode(['success' => false, 'message' => 'Please wait 30 seconds before requesting another OTP.']);
                exit;
            }

            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['login_otp'] = [
                'code' => $otp,
                'expires' => time() + 300, // 5 minutes
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];
            $_SESSION['login_otp_sent'] = time();

            $subject = 'Login OTP - Student Payment System';
            $body = generate_login_otp_email($user['username'], $user['email'], $otp);
            $result = smtp_mailer($user['email'], $subject, $body);

            if ($result === 'Sent') {
                echo json_encode(['success' => true, 'message' => 'OTP sent to ' . mask_email($user['email']) . '.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please try again.']);
            }
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'send_reset_otp') {
        $email = trim($_POST['reset_email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'No account found with this email address.']);
                exit;
            }

            // Check if OTP was sent recently (30 seconds cooldown)
            if (isset($_SESSION['reset_otp_sent']) && time() - $_SESSION['reset_otp_sent'] < 30) {
                echo json_encode(['success' => false, 'message' => 'Please wait 30 seconds before requesting another OTP.']);
                exit;
            }

            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['reset_otp'] = [
                'code' => $otp,
                'expires' => time() + 300, // 5 minutes
                'user_id' => $user['id'],
                'email' => $user['email']
            ];
            $_SESSION['reset_otp_sent'] = time();

            $subject = 'Password Reset OTP - Student Payment System';
            $body = generate_password_reset_otp_email($user['username'], $user['email'], $otp);
            $result = smtp_mailer($user['email'], $subject, $body);

            if ($result === 'Sent') {
                echo json_encode(['success' => true, 'message' => 'Password reset OTP sent to ' . mask_email($user['email']) . '.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send OTP email. Please try again.']);
            }
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $login_method = $_POST['login_method'] ?? 'password';

    if ($login_method === 'password') {
        // Password login
        if (empty($username) || empty($password)) {
            $popup_message = 'Please enter both username/email and password, or use OTP login instead.';
            $popup_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: ./');
                    exit;
                } else {
                    $popup_message = 'Invalid username or password.';
                    $popup_type = 'danger';
                }
            } catch(PDOException $e) {
                $popup_message = 'Error: ' . $e->getMessage();
                $popup_type = 'danger';
            }
        }
    } elseif ($login_method === 'otp') {
        // OTP login verification
        $entered_otp = $_POST['login_otp'] ?? '';

        if (isset($_SESSION['login_otp']) && isset($_SESSION['login_otp']['code'])) {
            $stored_otp = $_SESSION['login_otp']['code'];
            $expires = $_SESSION['login_otp']['expires'];

            if (time() > $expires) {
                $popup_message = 'OTP has expired. Please request a new one.';
                $popup_type = 'danger';
                unset($_SESSION['login_otp']);
                $show_login_otp_form = true;
            } elseif ($entered_otp === $stored_otp) {
                // Successful OTP login
                $_SESSION['user_id'] = $_SESSION['login_otp']['user_id'];
                $_SESSION['username'] = $_SESSION['login_otp']['username'];
                $_SESSION['email'] = $_SESSION['login_otp']['email'];

                // Get role from database
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION['role'] = $user['role'];

                unset($_SESSION['login_otp']);
                header('Location: ./');
                exit;
            } else {
                $popup_message = 'Invalid OTP. Please try again.';
                $popup_type = 'danger';
                $show_login_otp_form = true;
            }
        } else {
            $popup_message = 'OTP session expired. Please request a new OTP.';
            $popup_type = 'danger';
        }
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'register') {
    $username = trim($_POST['reg_username'] ?? '');
    $email = trim($_POST['reg_email'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $confirm_password = $_POST['reg_confirm_password'] ?? '';
    if (empty($username) || empty($email) || empty($password)) {
        $popup_message = 'All fields are required.';
        $popup_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $popup_message = 'Passwords do not match.';
        $popup_type = 'danger';
    } elseif (strlen($password) < 6) {
        $popup_message = 'Password must be at least 6 characters long.';
        $popup_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $popup_message = 'Please enter a valid email address.';
        $popup_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $popup_message = 'Username or email already exists.';
                $popup_type = 'danger';
            } else {
                // Generate OTP for registration verification
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['reg_otp'] = [
                    'code' => $otp,
                    'expires' => time() + 300, // 5 minutes
                    'username' => $username,
                    'email' => $email,
                    'password' => $password
                ];

                // Send OTP email
                $subject = 'Account Registration Verification - Student Payment System';
                $body = generate_registration_otp_email($username, $email, $otp);

                $result = smtp_mailer($email, $subject, $body);
                if ($result === 'Sent') {
                    $popup_message = 'OTP sent to ' . mask_email($email) . '. Please check your inbox and enter the code below.';
                    $show_reg_otp_form = true;
                } else {
                    $popup_message = 'Failed to send OTP email. Please try again.';
                    $popup_type = 'danger';
                    unset($_SESSION['reg_otp']);
                }
            }
        } catch(PDOException $e) {
            $popup_message = 'Error: ' . $e->getMessage();
            $popup_type = 'danger';
        }
    }
}

// Handle registration OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_reg_otp'])) {
    $entered_otp = $_POST['reg_otp'] ?? '';

    if (isset($_SESSION['reg_otp']) && isset($_SESSION['reg_otp']['code'])) {
        $stored_otp = $_SESSION['reg_otp']['code'];
        $expires = $_SESSION['reg_otp']['expires'];

        if (time() > $expires) {
            $popup_message = 'OTP has expired. Please try registering again.';
            $popup_type = 'danger';
            unset($_SESSION['reg_otp']);
        } elseif ($entered_otp === $stored_otp) {
            // Complete registration
            $hashed = (defined('PASSWORD_ARGON2ID') ? password_hash($_SESSION['reg_otp']['password'], PASSWORD_ARGON2ID) : password_hash($_SESSION['reg_otp']['password'], PASSWORD_DEFAULT));
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$_SESSION['reg_otp']['username'], $_SESSION['reg_otp']['email'], $hashed]);
                $popup_message = 'Registration successful! You can now login.';
                $popup_type = 'success';
                $step = 'login';
                unset($_SESSION['reg_otp']);
            } catch(PDOException $e) {
                $popup_message = 'Error completing registration: ' . $e->getMessage();
                $popup_type = 'danger';
                unset($_SESSION['reg_otp']);
            }
        } else {
            $popup_message = 'Invalid OTP. Please try again.';
            $popup_type = 'danger';
            $show_reg_otp_form = true;
        }
    } else {
        $popup_message = 'OTP session expired. Please try registering again.';
        $popup_type = 'danger';
    }
}

// Handle password reset OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_reset_otp'])) {
    $entered_otp = $_POST['reset_otp'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_new_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $popup_message = 'Please enter both password fields.';
        $popup_type = 'danger';
        $show_reset_password_form = true;
    } elseif ($new_password !== $confirm_password) {
        $popup_message = 'Passwords do not match.';
        $popup_type = 'danger';
        $show_reset_password_form = true;
    } elseif (strlen($new_password) < 6) {
        $popup_message = 'Password must be at least 6 characters long.';
        $popup_type = 'danger';
        $show_reset_password_form = true;
    } elseif (isset($_SESSION['reset_otp']) && isset($_SESSION['reset_otp']['code'])) {
        $stored_otp = $_SESSION['reset_otp']['code'];
        $expires = $_SESSION['reset_otp']['expires'];

        if (time() > $expires) {
            $popup_message = 'OTP has expired. Please request a new one.';
            $popup_type = 'danger';
            unset($_SESSION['reset_otp']);
        } elseif ($entered_otp === $stored_otp) {
            // Reset password
            $hashed = (defined('PASSWORD_ARGON2ID') ? password_hash($new_password, PASSWORD_ARGON2ID) : password_hash($new_password, PASSWORD_DEFAULT));
            try {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed, $_SESSION['reset_otp']['user_id']]);

                // Send success email
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['reset_otp']['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $subject = 'Password Reset Successful - Student Payment System';
                $body = generate_password_reset_success_email($user['username']);
                smtp_mailer($_SESSION['reset_otp']['email'], $subject, $body);

                $popup_message = 'Password reset successful! You can now login with your new password.';
                $popup_type = 'success';
                $step = 'login';
                unset($_SESSION['reset_otp']);
            } catch(PDOException $e) {
                $popup_message = 'Error resetting password: ' . $e->getMessage();
                $popup_type = 'danger';
                $show_reset_password_form = true;
            }
        } else {
            $popup_message = 'Invalid OTP. Please try again.';
            $popup_type = 'danger';
            $show_reset_password_form = true;
        }
    } else {
        $popup_message = 'OTP session expired. Please request a new OTP.';
        $popup_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php
        // Build absolute URL for canonical and Open Graph
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $request_uri = strtok($_SERVER['REQUEST_URI'], '?');
        $canonical = $protocol . '://' . $host . $request_uri;
        $page_title = 'Login/Register - Student Payment Management System';
        $meta_description = 'Login or register to the Student Payment Management System to manage students, payments, receipts and reports. Secure OTP and password login supported.';
        $meta_keywords = 'student payment, student management, payments, registration, login, OTP, receipts';
        $og_image = $protocol . '://' . $host . '/img/logo.png';
        ?>
        <title><?php echo htmlspecialchars($page_title); ?></title>
        <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
        <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
        <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
        <meta name="robots" content="index,follow">
        <!-- Open Graph / Social -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>">
        <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
        <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
        <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description); ?>">
        <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "Student Payment Management System",
            "url": "<?php echo htmlspecialchars($protocol . '://' . $host); ?>"
        }
        </script>
    <link rel="icon" href="img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); min-height: 100vh; }
        .auth-container { margin-top: 60px; }
        .auth-card { border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.18); overflow: hidden; background: #fff; }
        .auth-tabs { display: flex; justify-content: center; margin-bottom: 24px; }
        .auth-tab { cursor: pointer; padding: 12px 32px; font-size: 1.2rem; border: none; background: none; color: #2575fc; transition: color 0.2s; }
        .auth-tab.active { color: #fff; background: #2575fc; border-radius: 8px; }
        .auth-form { animation: fadeIn 0.6s; }
        /* Animations for login method toggles */
        .animate-slide-in { animation: slideIn 320ms cubic-bezier(.2,.9,.2,1) forwards; }
        .animate-slide-out { animation: slideOut 240ms cubic-bezier(.4,.0,.2,1) forwards; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-8px); } }
        .animate-fade-in { animation: fadeInFast 220ms ease forwards; }
        .animate-fade-out { animation: fadeOutFast 180ms ease forwards; }
        @keyframes fadeInFast { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeOutFast { from { opacity: 1; } to { opacity: 0; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .auth-logo { width: 64px; height: 64px; margin-bottom: 18px; }
        .auth-title { font-weight: 700; color: #2575fc; margin-bottom: 12px; }
        .auth-footer { margin-top: 32px; text-align: center; color: #fff; }
        .form-check-inline { margin-right: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="./">Student Payment System</a>
        </div>
    </nav>
    <div class="container auth-container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="auth-card p-4">
                    <div class="text-center">
                        <img src="img/logo.png" class="auth-logo" alt="Logo">
                        <div class="auth-title">Welcome</div>
                    </div>
                    <div class="auth-tabs mb-3">
                        <button class="auth-tab" id="loginTab">Login</button>
                        <button class="auth-tab" id="registerTab">Register</button>
                    </div>
                    <div id="loginForm" class="auth-form">
                        <form method="POST" action="">
                            <input type="hidden" name="step" value="login">
                            <input type="hidden" name="login_method" id="login_method" value="password">
                            <div class="text-center mb-4">
                                <h5>Login</h5>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username or Email *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>

                            <div id="passwordGroup" class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye" id="passwordIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div id="otpActions" style="display:none;">
                                <div class="d-grid mb-3">
                                    <button type="button" class="btn btn-success btn-lg" id="sendOtpBtn">
                                        <i class="fas fa-envelope"></i> Send OTP
                                    </button>
                                </div>
                                <div id="loginOtpInput" class="mb-3" style="display:none;">
                                    <label for="login_otp" class="form-label">OTP Code *</label>
                                    <input type="text" class="form-control text-center fs-4" id="login_otp" name="login_otp" maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="letter-spacing: 0.5rem;">
                                </div>
                            </div>

                            <div class="d-grid mb-2">
                                <button type="submit" id="loginSubmitBtn" class="btn btn-outline-primary btn-lg">Login with Password</button>
                            </div>
                            <div class="text-center">
                                <a href="#" id="toggleLoginMode">Use OTP instead</a>
                                <span class="mx-2">|</span>
                                <a href="#" id="forgotPasswordLink">Forgot password?</a>
                            </div>
                        </form>
                    </div>
                    <div id="registerForm" class="auth-form" style="display:none;">
                        <form method="POST" action="">
                            <input type="hidden" name="step" value="register">
                            <div class="mb-3">
                                <label for="reg_username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="reg_username" name="reg_username" required>
                            </div>
                            <div class="mb-3">
                                <label for="reg_email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="reg_email" name="reg_email" required>
                            </div>
                            <div class="mb-3">
                                <label for="reg_password" class="form-label">Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="reg_password" name="reg_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleRegPassword">
                                        <i class="fas fa-eye" id="regPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_confirm_password" class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="reg_confirm_password" name="reg_confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleRegConfirmPassword">
                                        <i class="fas fa-eye" id="regConfirmPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid mb-2">
                                <button type="submit" class="btn btn-success btn-lg">Register</button>
                            </div>
                            <div class="text-center">
                                <a href="#" id="backToLogin">Already have an account? Login</a>
                            </div>
                        </form>
                    </div>
                    <div id="regOtpForm" class="auth-form" style="display:none;">
                        <form method="POST" action="">
                            <div class="text-center mb-4">
                                <h5>Verify Your Email</h5>
                                <p class="text-muted">Enter the 6-digit OTP sent to your email</p>
                            </div>
                            <div class="mb-3">
                                <label for="reg_otp" class="form-label">OTP Code *</label>
                                <input type="text" class="form-control text-center fs-4" id="reg_otp" name="reg_otp" required maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="letter-spacing: 0.5rem;">
                            </div>
                            <div class="d-grid mb-2">
                                <button type="submit" name="verify_reg_otp" class="btn btn-success btn-lg">Verify & Complete Registration</button>
                            </div>
                            <div class="text-center">
                                <a href="#" id="backToRegister">Back to Registration</a>
                            </div>
                        </form>
                    </div>
                    <!-- loginOtpForm removed; OTP flow is integrated into main login form via toggle -->
                    <div id="forgotPasswordForm" class="auth-form" style="display:none;">
                        <form id="forgotPasswordFormAjax">
                            <div class="text-center mb-4">
                                <h5>Reset Password</h5>
                                <p class="text-muted">Enter your email address to receive a password reset OTP</p>
                            </div>
                            <div class="mb-3">
                                <label for="reset_email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="reset_email" name="reset_email" required>
                            </div>
                            <div class="d-grid mb-2">
                                <button type="submit" class="btn btn-warning btn-lg">Send Reset OTP</button>
                            </div>
                            <div class="text-center">
                                <a href="#" id="backToLoginFromForgot">Back to Login</a>
                            </div>
                        </form>
                    </div>
                    <div id="resetPasswordForm" class="auth-form" style="display:none;">
                        <form method="POST" action="">
                            <div class="text-center mb-4">
                                <h5>Set New Password</h5>
                                <p class="text-muted">Enter the OTP and your new password</p>
                            </div>
                            <div class="mb-3">
                                <label for="reset_otp" class="form-label">OTP Code *</label>
                                <input type="text" class="form-control text-center fs-4" id="reset_otp" name="reset_otp" required maxlength="6" pattern="[0-9]{6}" placeholder="000000" style="letter-spacing: 0.5rem;">
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                                        <i class="fas fa-eye" id="newPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_new_password" class="form-label">Confirm New Password *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmNewPassword">
                                        <i class="fas fa-eye" id="confirmNewPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid mb-2">
                                <button type="submit" name="verify_reset_otp" class="btn btn-success btn-lg">Reset Password</button>
                            </div>
                            <div class="text-center">
                                <a href="#" id="backToLoginFromReset">Back to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($popup_message)): ?>
    <script>
    (function(){
        var msg = <?php echo json_encode($popup_message); ?>;
        var type = <?php echo json_encode($popup_type); ?>;
        var overlay = document.createElement('div');
        overlay.id = 'php-popup-overlay';
        overlay.style = 'position:fixed;left:0;top:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:3000;';
        var box = document.createElement('div');
        box.style = 'background:#fff;padding:18px;border-radius:8px;max-width:90%;width:480px;box-shadow:0 10px 30px rgba(0,0,0,0.25);font-family:Arial, sans-serif;';
        var title = document.createElement('div'); title.style = 'font-weight:700;margin-bottom:8px;color:' + (type == 'success' ? '#0f5132' : '#842029'); title.textContent = (type == 'success' ? 'Success' : 'Notice');
        var p = document.createElement('div'); p.style = 'white-space:pre-wrap;'; p.innerHTML = msg;
        var actions = document.createElement('div'); actions.style = 'text-align:right;margin-top:12px;';
        var btn = document.createElement('button'); btn.textContent = 'OK'; btn.style = 'padding:8px 12px;border:0;background:#0d6efd;color:#fff;border-radius:4px;cursor:pointer;';
        btn.addEventListener('click', function(){ try{ document.body.removeChild(overlay); }catch(e){} });
        actions.appendChild(btn);
        box.appendChild(title); box.appendChild(p); box.appendChild(actions); overlay.appendChild(box); document.body.appendChild(overlay);
        setTimeout(function(){ try{ if(document.body.contains(overlay)) document.body.removeChild(overlay); }catch(e){}; }, 6000);
    })();
    </script>
    <?php endif; ?>
    <script>
    // Tab switching with animation
    const loginTab = document.getElementById('loginTab');
    const registerTab = document.getElementById('registerTab');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const regOtpForm = document.getElementById('regOtpForm');

    // Check if OTP form should be shown (set initial mode variable)
        <?php if (isset($show_reg_otp_form) && $show_reg_otp_form): ?>
        regOtpForm.style.display = 'block';
        loginForm.style.display = 'none';
        registerForm.style.display = 'none';
        <?php endif; ?>
    loginTab.addEventListener('click', function(){
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
        regOtpForm.style.display = 'none';
        var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
        forgotPasswordForm.style.display = 'none';
        resetPasswordForm.style.display = 'none';
    });
    registerTab.addEventListener('click', function(){
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
        registerForm.style.display = 'block';
        loginForm.style.display = 'none';
        regOtpForm.style.display = 'none';
        var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
        forgotPasswordForm.style.display = 'none';
        resetPasswordForm.style.display = 'none';
    });
    document.getElementById('backToLogin').addEventListener('click', function(e){
        e.preventDefault();
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
        loginForm.style.display = 'block';
        registerForm.style.display = 'none';
        regOtpForm.style.display = 'none';
        var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
        forgotPasswordForm.style.display = 'none';
        resetPasswordForm.style.display = 'none';
    });
    document.getElementById('backToRegister').addEventListener('click', function(e){
        e.preventDefault();
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
        registerForm.style.display = 'block';
        loginForm.style.display = 'none';
        regOtpForm.style.display = 'none';
        var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
        forgotPasswordForm.style.display = 'none';
        resetPasswordForm.style.display = 'none';
    });
    var backToLoginFromOtpBtn = document.getElementById('backToLoginFromOtp');
    if (backToLoginFromOtpBtn) {
        backToLoginFromOtpBtn.addEventListener('click', function(e){
            e.preventDefault();
            // switch back to password mode
            try{ setLoginMode('password'); }catch(e){}
            loginTab.classList.add('active');
            registerTab.classList.remove('active');
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            regOtpForm.style.display = 'none';
            var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
            var _forgot = document.getElementById('forgotPasswordForm'); if (_forgot) _forgot.style.display = 'none';
            var _reset = document.getElementById('resetPasswordForm'); if (_reset) _reset.style.display = 'none';
        });
    }
    var backToLoginFromForgotBtn = document.getElementById('backToLoginFromForgot');
    if (backToLoginFromForgotBtn) {
        backToLoginFromForgotBtn.addEventListener('click', function(e){
            e.preventDefault();
            loginTab.classList.add('active');
            registerTab.classList.remove('active');
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            regOtpForm.style.display = 'none';
            var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
            var _forgot = document.getElementById('forgotPasswordForm'); if (_forgot) _forgot.style.display = 'none';
            var _reset = document.getElementById('resetPasswordForm'); if (_reset) _reset.style.display = 'none';
        });
    }
    var backToLoginFromResetBtn = document.getElementById('backToLoginFromReset');
    if (backToLoginFromResetBtn) {
        backToLoginFromResetBtn.addEventListener('click', function(e){
            e.preventDefault();
            loginTab.classList.add('active');
            registerTab.classList.remove('active');
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            regOtpForm.style.display = 'none';
            var _loginOtp = document.getElementById('loginOtpForm'); if (_loginOtp) _loginOtp.style.display = 'none';
            var _forgot = document.getElementById('forgotPasswordForm'); if (_forgot) _forgot.style.display = 'none';
            var _reset = document.getElementById('resetPasswordForm'); if (_reset) _reset.style.display = 'none';
        });
    }
    
    
    // Password toggle functionality
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = document.getElementById('passwordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    document.getElementById('toggleRegPassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('reg_password');
        const icon = document.getElementById('regPasswordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    document.getElementById('toggleRegConfirmPassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('reg_confirm_password');
        const icon = document.getElementById('regConfirmPasswordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Login mode management (password first, OTP optional)
    var otpSent = false;
    function setLoginMode(mode) {
        var loginMethodInput = document.getElementById('login_method');
        var passwordGroup = document.getElementById('passwordGroup');
        var otpActions = document.getElementById('otpActions');
        var loginOtpInput = document.getElementById('loginOtpInput');
        var loginSubmitBtn = document.getElementById('loginSubmitBtn');
        if (!loginMethodInput) return;
        loginMethodInput.value = mode;
        if (mode === 'otp') {
            if (passwordGroup) passwordGroup.style.display = 'none';
            if (otpActions) otpActions.style.display = 'block';
            if (loginSubmitBtn) {
                loginSubmitBtn.className = 'btn btn-outline-success btn-lg';
                loginSubmitBtn.textContent = 'Verify & Login';
                loginSubmitBtn.disabled = !otpSent;
            }
            var toggleLink = document.getElementById('toggleLoginMode');
            if (toggleLink) toggleLink.textContent = 'Use password instead';
        } else {
            // password mode
            if (passwordGroup) passwordGroup.style.display = 'block';
            if (otpActions) otpActions.style.display = 'none';
            if (loginOtpInput) loginOtpInput.style.display = 'none';
            if (loginSubmitBtn) {
                loginSubmitBtn.className = 'btn btn-outline-primary btn-lg';
                loginSubmitBtn.textContent = 'Login with Password';
                loginSubmitBtn.disabled = false;
            }
            var toggleLink = document.getElementById('toggleLoginMode');
            if (toggleLink) toggleLink.textContent = 'Use OTP instead';
        }
    }

    // Animation helpers for toggling elements
    function animateToggle(el, show) {
        if (!el) return;
        el.classList.remove('animate-slide-in', 'animate-slide-out', 'animate-fade-in', 'animate-fade-out');
        if (show) {
            // show with slide-in
            if (getComputedStyle(el).display === 'none') el.style.display = 'block';
            void el.offsetWidth;
            el.classList.add('animate-slide-in');
        } else {
            // hide with slide-out then set display:none
            el.classList.add('animate-slide-out');
            var handler = function() {
                try { el.style.display = 'none'; } catch(e) {}
                el.classList.remove('animate-slide-out');
                el.removeEventListener('animationend', handler);
            };
            el.addEventListener('animationend', handler);
        }
    }

    // Enhanced setLoginMode with animations
    function setLoginMode(mode) {
        var loginMethodInput = document.getElementById('login_method');
        var passwordGroup = document.getElementById('passwordGroup');
        var otpActions = document.getElementById('otpActions');
        var loginOtpInput = document.getElementById('loginOtpInput');
        var loginSubmitBtn = document.getElementById('loginSubmitBtn');
        if (!loginMethodInput) return;
        loginMethodInput.value = mode;
        if (mode === 'otp') {
            animateToggle(passwordGroup, false);
            animateToggle(otpActions, true);
            if (otpSent) animateToggle(loginOtpInput, true);
            if (loginSubmitBtn) {
                loginSubmitBtn.className = 'btn btn-outline-success btn-lg';
                loginSubmitBtn.textContent = 'Verify & Login';
                loginSubmitBtn.disabled = !otpSent;
            }
            var toggleLink = document.getElementById('toggleLoginMode');
            if (toggleLink) toggleLink.textContent = 'Use password instead';
        } else {
            animateToggle(otpActions, false);
            animateToggle(loginOtpInput, false);
            animateToggle(passwordGroup, true);
            if (loginSubmitBtn) {
                loginSubmitBtn.className = 'btn btn-outline-primary btn-lg';
                loginSubmitBtn.textContent = 'Login with Password';
                loginSubmitBtn.disabled = false;
            }
            var toggleLink = document.getElementById('toggleLoginMode');
            if (toggleLink) toggleLink.textContent = 'Use OTP instead';
        }
    }

    // Toggle link
    document.getElementById('toggleLoginMode').addEventListener('click', function(e){
        e.preventDefault();
        var current = document.getElementById('login_method').value || 'password';
        var next = current === 'password' ? 'otp' : 'password';
        setLoginMode(next);
    });

    // Send OTP inline
    var sendOtpBtn = document.getElementById('sendOtpBtn');
    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', function() {
            const username = document.getElementById('username').value.trim();
            if (!username) {
                showMessage('Please enter username or email to receive OTP.', 'danger');
                return;
            }

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=send_login_otp&username_or_email=' + encodeURIComponent(username)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('✓ ' + data.message, 'success');
                    otpSent = true;
                    var loginOtpInput = document.getElementById('loginOtpInput');
                    if (loginOtpInput) loginOtpInput.style.display = 'block';
                    var loginSubmitBtn = document.getElementById('loginSubmitBtn');
                    if (loginSubmitBtn) loginSubmitBtn.disabled = false;
                } else {
                    showMessage('✗ ' + data.message, 'danger');
                }
            })
            .catch(() => {
                showMessage('✗ Error sending OTP. Please try again.', 'danger');
            });
        });
    }

    // Initialize mode based on server hint
    try { if (typeof initialLoginMode !== 'undefined' && initialLoginMode === 'otp') { setLoginMode('otp'); } } catch(e) {}

    // Forgot password functionality
    document.getElementById('forgotPasswordLink').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('forgotPasswordForm').style.display = 'block';
    });

    // Forgot password form submission
    document.getElementById('forgotPasswordFormAjax').addEventListener('submit', function(e) {
        e.preventDefault();
        const email = document.getElementById('reset_email').value;

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=send_reset_otp&reset_email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                // Switch to reset password form
                document.getElementById('forgotPasswordForm').style.display = 'none';
                document.getElementById('resetPasswordForm').style.display = 'block';
            } else {
                showMessage(data.message, 'danger');
            }
        })
        .catch(error => {
            showMessage('Error sending reset OTP. Please try again.', 'danger');
        });
    });

    // Password toggle for reset forms
    document.getElementById('toggleNewPassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('new_password');
        const icon = document.getElementById('newPasswordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    document.getElementById('toggleConfirmNewPassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('confirm_new_password');
        const icon = document.getElementById('confirmNewPasswordIcon');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Utility function to show messages
    function showMessage(message, type) {
        // Remove existing popup
        const existingPopup = document.getElementById('temp-popup-overlay');
        if (existingPopup) {
            document.body.removeChild(existingPopup);
        }

        const overlay = document.createElement('div');
        overlay.id = 'temp-popup-overlay';
        overlay.style = 'position:fixed;left:0;top:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:3000;';
        const box = document.createElement('div');
        box.style = 'background:#fff;padding:18px;border-radius:8px;max-width:90%;width:480px;box-shadow:0 10px 30px rgba(0,0,0,0.25);font-family:Arial, sans-serif;';
        const title = document.createElement('div');
        title.style = 'font-weight:700;margin-bottom:8px;color:' + (type == 'success' ? '#0f5132' : '#842029');
        title.textContent = (type == 'success' ? 'Success' : 'Notice');
        const p = document.createElement('div');
        p.style = 'white-space:pre-wrap;';
        p.innerHTML = message;
        const actions = document.createElement('div');
        actions.style = 'text-align:right;margin-top:12px;';
        const btn = document.createElement('button');
        btn.textContent = 'OK';
        btn.style = 'padding:8px 12px;border:0;background:#0d6efd;color:#fff;border-radius:4px;cursor:pointer;';
        btn.addEventListener('click', function(){
            try{ document.body.removeChild(overlay); }catch(e){}
        });
        actions.appendChild(btn);
        box.appendChild(title);
        box.appendChild(p);
        box.appendChild(actions);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        setTimeout(function(){
            try{ if(document.body.contains(overlay)) document.body.removeChild(overlay); }catch(e){};
        }, 5000);
    }
    </script>
    <!-- Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>