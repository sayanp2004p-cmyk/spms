<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

require_once 'php/db.php';
require_once 'email_send.php';
require_once 'email_template.php';

function mask_email($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    $masked_name = strlen($name) > 2 ? substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : $name . '*';
    return $masked_name . '@' . $domain;
}

// Helper: Send error notification email and set message
function sendErrorNotification(&$message, &$message_type, $action, $error_msg) {
    global $user, $pdo;
    $message = $error_msg;
    $message_type = 'danger';
    $subject = 'Profile Update Failed - Student Payment System';
    $body = generate_profile_error_notification_email($user['username'], $action, $error_msg);
    smtp_mailer($user['email'], $subject, $body);
}

// Helper: Generate and store OTP in session
function generateAndStoreOtp($change_type, $additional_data = []) {
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = ['code' => $otp, 'expires' => time() + 300];
    $_SESSION['change_type'] = $change_type;
    foreach ($additional_data as $key => $value) {
        $_SESSION[$key] = $value;
    }
    return $otp;
}

// Helper: Send OTP email and handle response
function sendOtpEmail(&$show_otp_form, &$message, &$message_type, $email, $subject, $body, $popup_msg) {
    $result = smtp_mailer($email, $subject, $body);
    // Handle both old string format and new array format for backwards compatibility
    $is_sent = (is_array($result) && isset($result['success']) && $result['success']) || ($result === 'Sent');
    
    if ($is_sent) {
        $_SESSION['popup_message'] = $popup_msg;
        $show_otp_form = true;
    } else {
        $message = 'Failed to send OTP email. Please try again.';
        $message_type = 'danger';
        unset($_SESSION['otp'], $_SESSION['change_type']);
    }
}

$active_page = 'profile';
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';
$show_otp_form = false;

$stmt = $pdo->prepare("SELECT username, email, role, created_at, email_for_sending, app_password, student_id_format, student_id_prefix, student_id_required FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login');
    exit;
}

// Handle email configuration (for student emails)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email_config'])) {
    $email_for_sending = trim($_POST['email_for_sending'] ?? '');
    $app_password = trim($_POST['app_password'] ?? '');

    if (empty($email_for_sending) || empty($app_password)) {
        $message = 'Email and app password are required.';
        $message_type = 'danger';
    } elseif (!filter_var($email_for_sending, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } else {
        $otp = generateAndStoreOtp('email_config', ['new_email_for_sending' => $email_for_sending, 'new_app_password' => $app_password]);
        $subject = 'Email Configuration OTP - Student Payment System';
        $body = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{font-family:Arial;background:#f8f9fa;margin:0;padding:0}.c{max-width:600px;margin:20px auto;background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}.hdr{background:linear-gradient(135deg,#007bff,#0056b3);color:#fff;padding:25px 20px;text-align:center}.hdr h1{margin:0;font-size:24px}.con{padding:30px 20px}.msg{background:#e7f3ff;color:#084c7d;padding:15px;border-radius:8px;border:1px solid #b8daff;margin:20px 0}.otp{font-size:28px;font-weight:bold;color:#007bff;text-align:center;margin:20px 0;letter-spacing:3px}.ftr{margin-top:30px;padding-top:20px;border-top:1px solid #dee2e6;text-align:center;color:#6c757d;font-size:14px}</style></head><body><div class='c'><div class='hdr'><h1>🔐 Email Configuration</h1></div><div class='con'><p>Dear ".htmlspecialchars($user['username']).",</p><div class='msg'>Verify your new email configuration with OTP:</div><div class='otp'>".htmlspecialchars($otp)."</div><p>Valid for 5 minutes. If not requested, ignore.</p></div><div class='ftr'><p>© ".date('Y')." Student Payment System</p></div></div></body></html>";
        sendOtpEmail($show_otp_form, $message, $message_type, $user['email'], $subject, $body, 'OTP sent to ' . mask_email($user['email']) . '. Please check your inbox and verify your email configuration.');
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp'] ?? '';

    if (isset($_SESSION['otp']) && isset($_SESSION['change_type'])) {
        $stored_otp = $_SESSION['otp']['code'];
        $expires = $_SESSION['otp']['expires'];

        if (time() > $expires) {
            $message = 'OTP has expired. Please try again.';
            $message_type = 'danger';
            unset($_SESSION['otp'], $_SESSION['change_type'], $_SESSION['new_value']);
        } elseif ($entered_otp === $stored_otp) {
            // Handle actions based on change_type
            $change_type = $_SESSION['change_type'];
            if ($change_type === 'password') {
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $success_msg = 'Password updated successfully.';
                $ok = $update_stmt->execute([$_SESSION['new_value'], $user_id]);
                if ($ok) {
                    $message = $success_msg;
                    $message_type = 'success';
                    // Send success email
                    $subject = 'Password Changed Successfully - Student Payment System';
                    $body = generate_password_change_confirmation_email($user['username']);
                    smtp_mailer($user['email'], $subject, $body);
                } else {
                    $message = 'Failed to update password.';
                    $message_type = 'danger';
                    // Send error email
                    $subject = 'Profile Update Failed - Student Payment System';
                    $body = generate_profile_error_notification_email($user['username'], 'Password Change', 'Failed to update password in database');
                    smtp_mailer($user['email'], $subject, $body);
                }
                // cleanup
                unset($_SESSION['otp'], $_SESSION['change_type'], $_SESSION['new_value']);
            } elseif ($change_type === 'delete_account') {
                // Perform full account deletion: payments, students, user
                try {
                    $pdo->beginTransaction();
                    $del1 = $pdo->prepare("DELETE FROM payments WHERE user_id = ?");
                    $del1->execute([$user_id]);
                    $del2 = $pdo->prepare("DELETE FROM students WHERE user_id = ?");
                    $del2->execute([$user_id]);
                    $del3 = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $del3->execute([$user_id]);
                    $pdo->commit();

                    // Send account deletion success email before destroying session
                    $subject = 'Account Deleted Successfully - Student Payment System';
                    $body = generate_account_deletion_success_email($user['username']);
                    smtp_mailer($user['email'], $subject, $body);

                    // set a flag for post-delete message, then destroy session and redirect to login
                    session_unset();
                    session_destroy();
                    header('Location: login?deleted=1');
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $message = 'Failed to delete account: ' . $e->getMessage();
                    $message_type = 'danger';
                    // Send error email
                    $subject = 'Account Deletion Failed - Student Payment System';
                    $body = generate_profile_error_notification_email($user['username'], 'Account Deletion', $e->getMessage());
                    smtp_mailer($user['email'], $subject, $body);
                    unset($_SESSION['otp'], $_SESSION['change_type'], $_SESSION['new_value']);
                }
            } elseif ($change_type === 'email_config') {
                // Save email configuration
                try {
                    $email_for_sending = $_SESSION['new_email_for_sending'] ?? '';
                    $app_password = $_SESSION['new_app_password'] ?? '';
                    
                    $update_stmt = $pdo->prepare("UPDATE users SET email_for_sending = ?, app_password = ? WHERE id = ?");
                    if ($update_stmt->execute([$email_for_sending, $app_password, $user_id])) {
                        $message = 'Email configuration saved successfully!';
                        $message_type = 'success';
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT username, email, role, created_at, email_for_sending, app_password, student_id_format, student_id_prefix, student_id_required FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                    } else {
                        $message = 'Failed to save email configuration.';
                        $message_type = 'danger';
                    }
                } catch (PDOException $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'danger';
                }
                unset($_SESSION['otp'], $_SESSION['change_type'], $_SESSION['new_email_for_sending'], $_SESSION['new_app_password']);
            } else {
                // Generic update (username, email)
                $update_field = $change_type;
                $update_stmt = $pdo->prepare("UPDATE users SET $update_field = ? WHERE id = ?");
                $success_msg = ucfirst($update_field) . ' updated successfully.';
                if ($update_stmt->execute([$_SESSION['new_value'], $user_id])) {
                    $message = $success_msg;
                    $message_type = 'success';
                    // Refetch user data
                    $stmt = $pdo->prepare("SELECT username, email, role, created_at, email_for_sending, app_password, student_id_format, student_id_prefix, student_id_required FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    // Send appropriate success email
                    if ($change_type === 'username') {
                        $subject = 'Username Changed Successfully - Student Payment System';
                        $body = generate_username_change_success_email($user['username'], $_SESSION['new_value']);
                    } else if ($change_type === 'email') {
                        $subject = 'Email Changed Successfully - Student Payment System';
                        $body = generate_email_change_success_email($user['username']);
                    }
                    if (isset($subject) && isset($body)) {
                        // Use old email if we're changing email, otherwise use new email
                        $email_to_use = ($change_type === 'email') ? $_SESSION['new_value'] : $user['email'];
                        smtp_mailer($email_to_use, $subject, $body);
                    }
                } else {
                    $message = 'Failed to update ' . $update_field . '.';
                    $message_type = 'danger';
                    // Send error email
                    $subject = 'Profile Update Failed - Student Payment System';
                    $body = generate_profile_error_notification_email($user['username'], ucfirst($update_field) . ' Change', 'Failed to update ' . $update_field);
                    smtp_mailer($user['email'], $subject, $body);
                }
                unset($_SESSION['otp'], $_SESSION['change_type'], $_SESSION['new_value']);
            }
        } else {
            $message = 'Invalid OTP. Please try again.';
            $message_type = 'danger';
        }
    } else {
        $message = 'OTP session expired. Please try again.';
        $message_type = 'danger';
    }
}

// Handle account deletion request (send OTP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_delete_account'])) {
    $otp = generateAndStoreOtp('delete_account');
    $subject = 'Account Deletion OTP - Student Payment System';
    $body = generate_account_deletion_email($user['username'], $otp);
    sendOtpEmail($show_otp_form, $message, $message_type, $user['email'], $subject, $body, 'OTP sent to ' . mask_email($user['email']) . '. Please check your inbox and enter the code below to confirm account deletion.');
}

// Handle student ID format save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student_id_format'])) {
    $student_id_format = trim($_POST['student_id_format'] ?? '');
    $student_id_prefix = trim($_POST['student_id_prefix'] ?? '');
    $student_id_required = isset($_POST['student_id_required']) ? 1 : 0;

    if (strlen($student_id_format) > 255) {
        $message = 'Student ID format is too long (max 255 chars).';
        $message_type = 'danger';
    } elseif (strpos($student_id_format, '{PREFIX}') !== false && empty($student_id_prefix)) {
        $message = 'Prefix is required when using {PREFIX} in the format.';
        $message_type = 'danger';
    } else {
        try {
            if (strlen($student_id_prefix) > 50) {
                throw new Exception('Prefix is too long (max 50 chars).');
            }
            $update = $pdo->prepare("UPDATE users SET student_id_format = ?, student_id_prefix = ?, student_id_required = ? WHERE id = ?");
            if ($update->execute([$student_id_format ?: null, $student_id_prefix ?: null, $student_id_required, $user_id])) {
                $message = 'Student ID settings saved successfully.';
                $message_type = 'success';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT username, email, role, created_at, email_for_sending, app_password, student_id_format, student_id_prefix, student_id_required FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $message = 'Failed to save Student ID format.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Handle username change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_username'])) {
    $new_username = trim($_POST['new_username'] ?? '');

    if (empty($new_username)) {
        sendErrorNotification($message, $message_type, 'Username Change', 'Username cannot be empty');
    } elseif ($new_username === $user['username']) {
        $message = 'New username is the same as current.';
        $message_type = 'warning';
    } elseif (strlen($new_username) < 3) {
        sendErrorNotification($message, $message_type, 'Username Change', 'Username must be at least 3 characters long');
    } else {
        // Check if username exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->execute([$new_username, $user_id]);
        if ($check_stmt->fetch()) {
            sendErrorNotification($message, $message_type, 'Username Change', 'Username already taken');
        } else {
            $otp = generateAndStoreOtp('username', ['new_value' => $new_username]);
            $subject = 'Username Change OTP - Student Payment System';
            $body = generate_username_change_email($user['username'], $new_username, $otp);
            sendOtpEmail($show_otp_form, $message, $message_type, $user['email'], $subject, $body, 'OTP sent to ' . mask_email($user['email']) . '. Please check your inbox and enter the code below.');
        }
    }
}

// Handle email change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = trim($_POST['new_email'] ?? '');

    if (empty($new_email)) {
        sendErrorNotification($message, $message_type, 'Email Change', 'Email cannot be empty');
    } elseif ($new_email === $user['email']) {
        $message = 'New email is the same as current.';
        $message_type = 'warning';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        sendErrorNotification($message, $message_type, 'Email Change', 'Please enter a valid email address');
    } else {
        // Check if email exists
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$new_email, $user_id]);
        if ($check_stmt->fetch()) {
            sendErrorNotification($message, $message_type, 'Email Change', 'Email already registered');
        } else {
            $otp = generateAndStoreOtp('email', ['new_value' => $new_email]);
            $subject = 'Email Change OTP - Student Payment System';
            $body = generate_email_change_email($user['username'], $new_email, $otp);
            sendOtpEmail($show_otp_form, $message, $message_type, $user['email'], $subject, $body, 'OTP sent to ' . mask_email($user['email']) . '. Please check your inbox and enter the code below.');
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        sendErrorNotification($message, $message_type, 'Password Change', 'All fields are required');
    } elseif ($new_password !== $confirm_password) {
        sendErrorNotification($message, $message_type, 'Password Change', 'New passwords do not match');
    } elseif (strlen($new_password) < 6) {
        sendErrorNotification($message, $message_type, 'Password Change', 'New password must be at least 6 characters long');
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        if ($user_data && password_verify($current_password, $user_data['password'])) {
            $_SESSION['change_type'] = 'password';
            $_SESSION['new_value'] = (defined('PASSWORD_ARGON2ID') ? password_hash($new_password, PASSWORD_ARGON2ID) : password_hash($new_password, PASSWORD_DEFAULT));
            $_SESSION['otp'] = ['code' => str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT), 'expires' => time() + 300];
            $subject = 'Password Change OTP - Student Payment System';
            $body = generate_password_otp_email($user['username'], $_SESSION['otp']['code'], false);
            sendOtpEmail($show_otp_form, $message, $message_type, $user['email'], $subject, $body, 'OTP sent to ' . mask_email($user['email']) . '. Please check your inbox and enter the code below.');
        } else {
            sendErrorNotification($message, $message_type, 'Password Change', 'Current password is incorrect');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Profile - Student Payment Management System</title>
    <link rel="icon" href="img/logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container mt-5 main-content">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="profile-header mb-4 p-4 rounded shadow-sm text-center" style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); color: #fff;">
                    <div class="profile-avatar mx-auto mb-3" style="width: 100px; height: 100px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(0,0,0,0.15);">
                        <i class="fas fa-user fa-4x text-primary"></i>
                    </div>
                    <h2 class="mb-1" style="font-weight: 700; letter-spacing: 1px;"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="mb-2" style="font-size: 1.1rem; opacity: 0.85;"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2" style="font-size: 1rem; border-radius: 20px;"><i class="fas fa-crown me-1"></i><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                    <p class="mt-2 mb-0" style="font-size: 0.95rem; opacity: 0.8;"><i class="fas fa-calendar-alt me-2"></i>Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
                <div class="card shadow-sm border-0 mb-5">
                    <div class="card-header bg-light border-0">
                        <h5 class="mb-0" style="font-weight: 600;"><i class="fas fa-user-shield me-2 text-primary"></i>Profile Management <span class="text-muted" style="font-size: 0.9rem;">(Email OTP Verified)</span></h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_otp_form): ?>
                            <div class="text-center">
                                <h5>Verify OTP</h5>
                                <p>Please enter the 6-digit OTP sent to your email.</p>
                                <form method="POST" class="d-inline-block text-start">
                                    <div class="mb-3 position-relative">
                                        <label for="otp" class="form-label">OTP Code</label>
                                        <input type="text" class="form-control text-center" id="otp" name="otp" required maxlength="6" pattern="[0-9]{6}" placeholder="000000">
                                        <button type="button" id="resendOtpBtn" class="btn btn-link text-primary position-absolute end-0 top-0" style="display:none;" onclick="resendOtp()">
                                            <i class="fa fa-sync-alt"></i> Resend OTP
                                        </button>
                                        <span id="otpTimer" class="text-muted small ms-2"></span>
                                    </div>
                                    <?php
                                        $otp_label = 'Verify & Proceed';
                                        if (isset($_SESSION['change_type'])) {
                                            switch ($_SESSION['change_type']) {
                                                case 'delete_account': $otp_label = 'Verify & Delete Account'; break;
                                                case 'username': $otp_label = 'Verify & Change Username'; break;
                                                case 'email': $otp_label = 'Verify & Change Email'; break;
                                                case 'email_config': $otp_label = 'Verify & Save Configuration'; break;
                                                case 'password': $otp_label = 'Verify & Change Password'; break;
                                                default: $otp_label = 'Verify & Proceed';
                                            }
                                        }
                                    ?>
                                    <button type="submit" name="verify_otp" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i><?php echo htmlspecialchars($otp_label); ?>
                                    </button>
                                    <button type="button" class="btn btn-secondary ms-2" onclick="window.location.href='index'">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);">
                                    <div class="card-header bg-transparent border-0">
                                        <h6 class="mb-0" style="color: #2575fc; font-weight: 600;"><i class="fas fa-user-edit me-2"></i>Change Username</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="new_username" class="form-label">New Username</label>
                                                <input type="text" class="form-control form-control-lg" id="new_username" name="new_username" required minlength="3" value="<?php echo htmlspecialchars($user['username']); ?>">
                                                <div class="form-text">Choose a unique username (3+ characters)</div>
                                            </div>
                                            <button type="submit" name="change_username" class="btn btn-primary w-100">
                                                <i class="fas fa-user-edit me-1"></i>Update Username
                                            </button>
                                        </form>
                                        <hr>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="student_id_format" class="form-label">Student ID Format</label>
                                                <div class="mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{YYYY}')">{YYYY}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{YY}')">{YY}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{MM}')">{MM}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{DD}')">{DD}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{SEQ}')">{SEQ}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{FIRST}')">{FIRST}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{LAST}')">{LAST}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{F}')">{F}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{L}')">{L}</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="insertKeyword('{PREFIX}')">{PREFIX}</button>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="student_id_prefix" class="form-label">Custom Prefix <span id="prefixRequiredBadge" class="text-danger" style="display:none;">*</span></label>
                                                    <input type="text" class="form-control form-control-lg" id="student_id_prefix" name="student_id_prefix" value="<?php echo htmlspecialchars($user['student_id_prefix'] ?? ''); ?>" placeholder="e.g. jsdlt or yc">
                                                    <div class="form-text">Use {PREFIX} in the format field to insert this prefix. If you use {PREFIX}, this field becomes required.</div>
                                                </div>
                                                <input type="text" class="form-control form-control-lg" id="student_id_format" name="student_id_format" value="<?php echo htmlspecialchars($user['student_id_format'] ?? ''); ?>" placeholder="e.g. COURSE-{F}{YY}{SEQ}">
                                                <div class="form-text">Use keywords: {YYYY} = full year, {YY} = two-digit year, {MM} = month, {DD} = day, {SEQ} = sequential number, {FIRST} = first name, {LAST} = last name, {F} = first initial, {L} = last initial, {PREFIX} = custom prefix. Click a keyword to insert at cursor. You can also type custom placeholders.</div>
                                            </div>
                                            <div class="form-check mb-3">
                                                <input class="form-check-input" type="checkbox" id="student_id_required" name="student_id_required" <?php echo !empty($user['student_id_required']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="student_id_required">Require student ID on registration</label>
                                            </div>
                                            <button type="submit" name="save_student_id_format" class="btn btn-primary w-100">
                                                <i class="fas fa-id-badge me-1"></i>Save Student ID Format
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #fceabb 0%, #f8b500 100%);">
                                    <div class="card-header bg-transparent border-0">
                                        <h6 class="mb-0" style="color: #b07d00; font-weight: 600;"><i class="fas fa-envelope me-2"></i>Change Email</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="new_email" class="form-label">New Email Address</label>
                                                <input type="email" class="form-control form-control-lg" id="new_email" name="new_email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                                                <div class="form-text">We'll send a verification code to confirm</div>
                                            </div>
                                            <button type="submit" name="change_email" class="btn btn-warning w-100">
                                                <i class="fas fa-envelope me-1"></i>Update Email
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                    <div class="card-header bg-transparent border-0">
                                        <h6 class="mb-0" style="color: #0066cc; font-weight: 600;"><i class="fas fa-mail-bulk me-2"></i>Email Configuration</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted mb-3">Configure email & app password to send emails to students</p>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="email_for_sending" class="form-label">Gmail Address</label>
                                                <input type="email" class="form-control form-control-lg" id="email_for_sending" name="email_for_sending" required value="<?php echo htmlspecialchars($user['email_for_sending'] ?? ''); ?>" placeholder="your-email@gmail.com">
                                                <div class="form-text">Use your Gmail address for sending student emails</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="app_password" class="form-label">App Password</label>
                                                <input type="password" class="form-control form-control-lg" id="app_password" name="app_password" required value="<?php echo htmlspecialchars($user['app_password'] ?? ''); ?>" placeholder="Your app password">
                                                <div class="form-text">Generate app password from Google Account settings</div>
                                            </div>
                                            <button type="submit" name="save_email_config" class="btn btn-info w-100">
                                                <i class="fas fa-save me-1"></i>Save Email Configuration
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #ff5858 0%, #f09819 100%);">
                                    <div class="card-header bg-transparent border-0">
                                        <h6 class="mb-0" style="color: #d7263d; font-weight: 600;"><i class="fas fa-user-times me-2"></i>Delete Account</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-2">Permanently delete your account and all related data. This action cannot be undone.</p>
                                        <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                            <i class="fas fa-user-times me-1"></i>Delete Account
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f8ffae 0%, #43c6ac 100%);">
                                    <div class="card-header bg-transparent border-0">
                                        <h6 class="mb-0" style="color: #43c6ac; font-weight: 600;"><i class="fas fa-key me-2"></i>Change Password</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="row g-3">
                                                <div class="col-md-4 mb-3 position-relative">
                                                    <label for="current_password" class="form-label">Current Password</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control form-control-lg" id="current_password" name="current_password" required>
                                                        <button type="button" class="btn btn-outline-secondary show-hide-btn" onclick="togglePassword('current_password')" tabindex="-1">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3 position-relative">
                                                    <label for="new_password" class="form-label">New Password</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control form-control-lg" id="new_password" name="new_password" required minlength="6">
                                                        <button type="button" class="btn btn-outline-secondary show-hide-btn" onclick="togglePassword('new_password')" tabindex="-1">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3 position-relative">
                                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required minlength="6">
                                                        <button type="button" class="btn btn-outline-secondary show-hide-btn" onclick="togglePassword('confirm_password')" tabindex="-1">
                                                            <i class="fa fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="change_password" class="btn btn-success w-100 mt-2 gradient-btn">
                                                <i class="fas fa-key me-1"></i>Update Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Delete Account Confirmation Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Confirm Account Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to permanently delete your account? This will remove your account and all related data (students, payments, etc.).</p>
                    <p class="text-danger small mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display:inline;">
                        <button type="submit" name="request_delete_account" class="btn btn-danger">Send OTP & Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/validation.js"></script>
    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // OTP resend timer logic
        let otpSentTime = <?php echo isset($_SESSION['otp']) ? $_SESSION['otp']['expires'] - 300 : 'null'; ?>;
        let otpValidUntil = <?php echo isset($_SESSION['otp']) ? $_SESSION['otp']['expires'] : 'null'; ?>;
        let timerInterval;
        function startOtpTimer() {
            const timerSpan = document.getElementById('otpTimer');
            const resendBtn = document.getElementById('resendOtpBtn');
            if (!otpSentTime || !otpValidUntil) return;
            let now = Math.floor(Date.now() / 1000);
            let resendAvailable = otpSentTime + 30;
            let validUntil = otpValidUntil;
            timerInterval = setInterval(() => {
                now = Math.floor(Date.now() / 1000);
                let resendSec = resendAvailable - now;
                let validSec = validUntil - now;
                if (validSec <= 0) {
                    timerSpan.textContent = 'OTP expired.';
                    resendBtn.style.display = 'inline-block';
                    clearInterval(timerInterval);
                } else {
                    timerSpan.textContent = 'Resend in ' + (resendSec > 0 ? resendSec + 's' : 'now') + ', valid for ' + validSec + 's';
                    resendBtn.style.display = resendSec <= 0 ? 'inline-block' : 'none';
                }
            }, 1000);
        }
        if (document.getElementById('otpTimer')) startOtpTimer();

        function resendOtp() {
            // You need to implement AJAX call to resend OTP here
            alert('Resend OTP functionality coming soon!');
        }

        // Insert keyword into the Student ID format input at the cursor position
        function insertKeyword(token) {
            var input = document.getElementById('student_id_format');
            if (!input) return;
            var start = (typeof input.selectionStart === 'number') ? input.selectionStart : input.value.length;
            var end = (typeof input.selectionEnd === 'number') ? input.selectionEnd : input.value.length;
            var val = input.value;
            input.value = val.substring(0, start) + token + val.substring(end);
            input.focus();
            var pos = start + token.length;
            try { input.setSelectionRange(pos, pos); } catch (e) {}
            validatePrefixRequirement();
        }

        // Validate if {PREFIX} is used in format, then prefix input is required
        function validatePrefixRequirement() {
            var formatInput = document.getElementById('student_id_format');
            var prefixInput = document.getElementById('student_id_prefix');
            var prefixBadge = document.getElementById('prefixRequiredBadge');
            if (!formatInput || !prefixInput) return;
            
            var formatValue = formatInput.value;
            var usePrefix = formatValue.indexOf('{PREFIX}') !== -1;
            
            prefixInput.required = usePrefix;
            if (prefixBadge) {
                prefixBadge.style.display = usePrefix ? 'inline' : 'none';
            }
        }

        // Monitor format input for changes
        document.addEventListener('DOMContentLoaded', function() {
            var formatInput = document.getElementById('student_id_format');
            if (formatInput) {
                formatInput.addEventListener('input', validatePrefixRequirement);
                validatePrefixRequirement();
            }
        });
    </script>
    <?php if (isset($_SESSION['popup_message'])): ?>
    <script>
        alert('<?php echo addslashes($_SESSION['popup_message']); ?>');
    </script>
    <?php unset($_SESSION['popup_message']); ?>
    <?php endif; ?>
</body>
</html>