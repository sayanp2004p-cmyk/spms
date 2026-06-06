<?php
ob_start();
session_start();

// Include database connection once
require_once 'php/db.php';

// Logo fetching function
function getUserLogo($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT logo FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['logo']) ? $row['logo'] : 'img/logo.png';
    } catch (Exception $e) {
        return 'img/logo.png';
    }
}

$user_logo = isset($_SESSION['user_id']) ? getUserLogo($pdo, $_SESSION['user_id']) : 'img/logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Add Payment - Student Payment Management System</title>
    <link rel="icon" href="<?php echo htmlspecialchars($user_logo); ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    if (!isset($_SESSION['user_id'])) {
        header('Location: login');
        exit;
    }
    $active_page = 'add_payment';
    include 'navbar.php';
    
    // Helper: Generate unique payment code
    function generatePaymentCode($pdo) {
        do {
            $code = '';
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
            $stmt = $pdo->prepare("SELECT id FROM payments WHERE payment_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetch();
        } while ($exists);
        return $code;
    }
    
    // Helper: Get student data
    function getStudent($pdo, $student_id, $user_id) {
        $stmt = $pdo->prepare("SELECT email, first_name, last_name, fee_amount, admission_date, phone, course FROM students WHERE student_id = ? AND user_id = ?");
        $stmt->execute([$student_id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Helper: Validate payment date
    function validatePaymentDate($payment_date, $admission_date) {
        if (!empty($admission_date) && strtotime($payment_date) < strtotime($admission_date)) {
            return ['valid' => false, 'msg' => 'Payment date cannot be before the student\'s admission date.'];
        }
        return ['valid' => true];
    }
    
    // Helper: Get next payment month based on last payment
    function getNextPaymentMonth($pdo, $student_id, $user_id) {
        try {
            $stmt = $pdo->prepare("SELECT payment_month FROM payments WHERE student_id = ? AND user_id = ? AND status IN ('completed', 'pending') ORDER BY payment_date DESC LIMIT 1");
            $stmt->execute([$student_id, $user_id]);
            $last_payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($last_payment && !empty($last_payment['payment_month'])) {
                // Parse last payment month (format: YYYY-MM)
                $last_month = DateTime::createFromFormat('Y-m', $last_payment['payment_month']);
                $next_month = $last_month->modify('+1 month');
                return $next_month->format('Y-m');
            } else {
                // If no previous payment, use current month
                return date('Y-m');
            }
        } catch (Exception $e) {
            return date('Y-m');
        }
    }
    
    // Helper: Send payment email
    function sendPaymentEmail($pdo, $student, $payment_data, $subject, $is_partial = false, $due_amount = 0, $user_id = null) {
        if (!empty($student['email'])) {
            include 'email_send_student.php';
            include 'email_template.php';
            $msg = generate_payment_email($student, $payment_data, $due_amount, $is_partial);
            smtp_mailer($student['email'], $subject, $msg, $user_id);
        }
    }
    ?>
    <!-- Main Content -->
    <div class="container mt-4 main-content">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <h4 class="mb-0 fw-bold text-dark"><i class="fas fa-credit-card me-2 text-success"></i><?php echo isset($_GET['repay']) ? 'Repay Payment' : (isset($_GET['pay_due']) ? 'Complete Due Payment' : 'Add Payment Record'); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $popup_message = '';
                        $popup_type = 'info';

                        if (isset($_SESSION['flash_message'])) {
                            $popup_message = $_SESSION['flash_message'];
                            $popup_type = $_SESSION['flash_type'] ?? 'info';
                            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                        }

                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $student_id = trim($_POST['student_id']);
                            $amount = floatval($_POST['amount']);
                            $payment_date = $_POST['payment_date'];
                            $payment_month = $_POST['payment_month'];
                            $payment_method = $_POST['payment_method'];
                            $status = $_POST['status'] ?? 'pending';
                            $description = trim($_POST['description']);

                            $is_pay_due = isset($_POST['is_action']) && $_POST['is_action'] == 'pay_due';
                            $is_repay = isset($_POST['is_action']) && $_POST['is_action'] == 'repay';
                            $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;

                            try {
                                $student = getStudent($pdo, $student_id, $_SESSION['user_id']);
                                if (!$student) {
                                    $popup_message = 'Error: Student not found.';
                                    $popup_type = 'danger';
                                } else {
                                    $validation = validatePaymentDate($payment_date, $student['admission_date']);
                                    if (!$validation['valid']) {
                                        $popup_message = $validation['msg'];
                                        $popup_type = 'danger';
                                    } else {
                                        $payment_code = generatePaymentCode($pdo);
                                        
                                        if ($is_pay_due && $payment_id) {
                                            // Pay due payment
                                            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
                                            $stmt->execute([$payment_id, $_SESSION['user_id']]);
                                            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($existing && $existing['status'] == 'pending' && $existing['due_amount'] > 0) {
                                                $new_amount = $existing['amount'] + $amount;
                                                $new_due = max(0, $existing['due_amount'] - $amount);
                                                $new_status = ($amount == 0) ? 'cancelled' : (($new_due <= 0) ? 'completed' : 'pending');
                                                
                                                $stmt = $pdo->prepare("UPDATE payments SET amount = ?, due_amount = ?, status = ?, payment_date = ?, payment_month = ?, payment_method = ?, description = ?, payment_code = ? WHERE id = ? AND user_id = ?");
                                                $stmt->execute([$new_amount, $new_due, $new_status, $payment_date, $payment_month, $payment_method, $description, $payment_code, $payment_id, $_SESSION['user_id']]);
                                                
                                                $payment_data = ['amount' => $new_amount, 'payment_date' => $payment_date, 'payment_month' => $payment_month, 'payment_method' => $payment_method, 'status' => $new_status, 'payment_code' => $payment_code];
                                                sendPaymentEmail($pdo, $student, $payment_data, "Payment Completion - Student Payment System", $new_due > 0, $new_due, $_SESSION['user_id']);
                                                
                                                $_SESSION['flash_message'] = 'Due payment processed successfully!';
                                                $_SESSION['flash_type'] = 'success';
                                                header('Location: add_payment');
                                                exit;
                                            } else {
                                                $popup_message = 'Error: Payment not found or already completed.';
                                                $popup_type = 'danger';
                                            }
                                        } elseif ($is_repay && $payment_id) {
                                            // Repay cancelled payment
                                            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ? AND status = 'cancelled'");
                                            $stmt->execute([$payment_id, $_SESSION['user_id']]);
                                            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($existing) {
                                                $new_status = ($amount == 0) ? 'cancelled' : 'completed';
                                                $stmt = $pdo->prepare("UPDATE payments SET amount = ?, status = ?, payment_date = ?, payment_month = ?, payment_method = ?, description = ?, payment_code = ? WHERE id = ? AND user_id = ?");
                                                $stmt->execute([$amount, $new_status, $payment_date, $payment_month, $payment_method, $description, $payment_code, $payment_id, $_SESSION['user_id']]);
                                                
                                                $payment_data = ['amount' => $amount, 'payment_date' => $payment_date, 'payment_month' => $payment_month, 'payment_method' => $payment_method, 'status' => $new_status, 'payment_code' => $payment_code];
                                                sendPaymentEmail($pdo, $student, $payment_data, "Payment Repaid - Student Payment Management System", false, 0, $_SESSION['user_id']);
                                                
                                                $_SESSION['flash_message'] = 'Payment repaid successfully!';
                                                $_SESSION['flash_type'] = 'success';
                                                header('Location: add_payment');
                                                exit;
                                            } else {
                                                $popup_message = 'Error: Payment not found or not cancelled.';
                                                $popup_type = 'danger';
                                            }
                                        } else {
                                            // New payment
                                            $fee_amount = floatval($student['fee_amount']);
                                            $due_amount = 0;
                                            $is_partial = false;
                                            
                                            if ($amount < $fee_amount) {
                                                $status = 'pending';
                                                $due_amount = $fee_amount - $amount;
                                                $is_partial = true;
                                                $description = ($description ? $description . ' ' : '') . '(Partial payment - Due: ₹' . number_format($due_amount, 2) . ')';
                                            } elseif ($amount == $fee_amount) {
                                                $status = 'completed';
                                            } else {
                                                $status = $_POST['status'] ?? 'completed';
                                            }
                                            
                                            if ($amount == 0) $status = 'cancelled';
                                            
                                            $stmt = $pdo->prepare("INSERT INTO payments (user_id, student_id, amount, payment_date, payment_month, payment_method, status, description, due_amount, payment_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                            $stmt->execute([$_SESSION['user_id'], $student_id, $amount, $payment_date, $payment_month, $payment_method, $status, $description, $due_amount, $payment_code]);
                                            
                                            $payment_data = ['amount' => $amount, 'payment_date' => $payment_date, 'payment_month' => $payment_month, 'payment_method' => $payment_method, 'status' => $status, 'payment_code' => $payment_code];
                                            sendPaymentEmail($pdo, $student, $payment_data, "Payment Confirmation - Student Payment System", $is_partial, $due_amount, $_SESSION['user_id']);
                                            
                                            $_SESSION['flash_message'] = 'Payment record added successfully! Confirmation email sent.';
                                            $_SESSION['flash_type'] = 'success';
                                            header('Location: add_payment');
                                            exit;
                                        }
                                    }
                                }
                            } catch(PDOException $e) {
                                $popup_message = 'Error: ' . $e->getMessage();
                                $popup_type = 'danger';
                            }
                        }
                        ?>
                        
                        <?php
                        $is_pay_due = isset($_GET['pay_due']) && isset($_GET['payment_id']);
                        $is_repay = isset($_GET['repay']) && isset($_GET['payment_id']);
                        $prefill_data = [];
                        if ($is_pay_due) {
                            try {
                                $stmt = $pdo->prepare("SELECT p.*, s.first_name, s.last_name, s.email, s.phone, s.course FROM payments p JOIN students s ON p.student_id = s.student_id WHERE p.id = ? AND p.user_id = ? AND s.user_id = ?");
                                $stmt->execute([$_GET['payment_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
                                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($payment && $payment['status'] == 'pending' && $payment['due_amount'] > 0) {
                                    $prefill_data = $payment;
                                } else {
                                    $popup_message = 'Invalid payment or already completed.';
                                    $popup_type = 'danger';
                                    $is_pay_due = false;
                                }
                            } catch(PDOException $e) {
                                $popup_message = 'Error: ' . $e->getMessage();
                                $popup_type = 'danger';
                                $is_pay_due = false;
                            }
                        }
                        if ($is_repay) {
                            try {
                                $stmt = $pdo->prepare("SELECT p.*, s.first_name, s.last_name, s.email, s.phone, s.course, s.fee_amount FROM payments p JOIN students s ON p.student_id = s.student_id WHERE p.id = ? AND p.user_id = ? AND s.user_id = ?");
                                $stmt->execute([$_GET['payment_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
                                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($payment && $payment['status'] == 'cancelled') {
                                    $prefill_data = $payment;
                                    $prefill_data['fee_amount'] = $payment['fee_amount'];
                                } else {
                                    $popup_message = 'Invalid payment or not cancelled.';
                                    $popup_type = 'danger';
                                    $is_repay = false;
                                }
                            } catch(PDOException $e) {
                                $popup_message = 'Error: ' . $e->getMessage();
                                $popup_type = 'danger';
                                $is_repay = false;
                            }
                        }
                        ?>

                        <?php
                        // Prepare student id list for datalist dropdown
                        $studentOptions = [];
                        try {
                            $stmt_opt = $pdo->prepare("SELECT student_id, first_name, last_name FROM students WHERE user_id = ? ORDER BY student_id ASC");
                            $stmt_opt->execute([$_SESSION['user_id']]);
                            $studentOptions = $stmt_opt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            // ignore; leave empty options
                        }
                        ?>

                        <!-- Progress Indicator -->
                        <div class="mb-4">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" id="progressBar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small class="text-muted" id="step1"><i class="fas fa-user-graduate"></i> Student</small>
                                <small class="text-muted" id="step2"><i class="fas fa-info-circle"></i> Details</small>
                                <small class="text-muted" id="step3"><i class="fas fa-credit-card"></i> Payment</small>
                                <small class="text-muted" id="step4"><i class="fas fa-save"></i> Submit</small>
                            </div>
                        </div>

                        <form method="POST" action="" id="paymentForm">
                            <?php if ($is_pay_due || $is_repay): ?>
                                <input type="hidden" name="is_action" value="<?php echo $is_pay_due ? 'pay_due' : 'repay'; ?>">
                                <input type="hidden" name="payment_id" value="<?php echo $prefill_data['id']; ?>">
                            <?php endif; ?>

                            <!-- Student Selection Section -->
                            <div class="card mb-4 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Student Selection</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="student_id" class="form-label fw-semibold"><i class="fas fa-id-card me-1"></i>Student ID *</label>
                                        <?php if ($is_pay_due || $is_repay): ?>
                                            <input type="text" class="form-control" id="student_id" name="student_id" required value="<?php echo htmlspecialchars($prefill_data['student_id']); ?>" readonly>
                                        <?php else: ?>
                                            <input list="studentIds" type="text" class="form-control" id="student_id" name="student_id" required value="" autocomplete="off" placeholder="Enter or select Student ID">
                                            <datalist id="studentIds">
                                                <?php foreach ($studentOptions as $opt): ?>
                                                    <option value="<?php echo htmlspecialchars($opt['student_id']); ?>"><?php echo htmlspecialchars($opt['first_name'] . ' ' . $opt['last_name']); ?></option>
                                                <?php endforeach; ?>
                                            </datalist>
                                        <?php endif; ?>
                                        <div id="student_id_error" class="text-danger mt-1 small" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Student Details Section -->
                            <div class="card mb-4 border-info">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Student Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="student_name" class="form-label fw-semibold"><i class="fas fa-user me-1"></i>Student Name</label>
                                            <input type="text" class="form-control" id="student_name" readonly value="<?php echo ($is_pay_due || $is_repay) ? htmlspecialchars($prefill_data['first_name'] . ' ' . $prefill_data['last_name']) : ''; ?>" placeholder="Auto-filled">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="student_email" class="form-label fw-semibold"><i class="fas fa-envelope me-1"></i>Email</label>
                                            <input type="email" class="form-control" id="student_email" readonly value="<?php echo ($is_pay_due || $is_repay) ? htmlspecialchars($prefill_data['email']) : ''; ?>" placeholder="Auto-filled">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="student_phone" class="form-label fw-semibold"><i class="fas fa-phone me-1"></i>Phone</label>
                                            <input type="text" class="form-control" id="student_phone" readonly value="<?php echo ($is_pay_due || $is_repay) ? htmlspecialchars($prefill_data['phone']) : ''; ?>" placeholder="Auto-filled">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="student_course" class="form-label fw-semibold"><i class="fas fa-book me-1"></i>Course</label>
                                            <input type="text" class="form-control" id="student_course" readonly value="<?php echo ($is_pay_due || $is_repay) ? htmlspecialchars($prefill_data['course']) : ''; ?>" placeholder="Auto-filled">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Details Section -->
                            <div class="card mb-4 border-success">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="amount" class="form-label fw-semibold"><i class="fas fa-rupee-sign me-1"></i>Amount (₹) *</label>
                                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required value="<?php echo $is_repay ? htmlspecialchars($prefill_data['fee_amount']) : ($is_pay_due ? htmlspecialchars($prefill_data['due_amount']) : ''); ?>" <?php echo $is_pay_due ? 'readonly' : ''; ?> placeholder="Enter amount">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="payment_date" class="form-label fw-semibold"><i class="fas fa-calendar me-1"></i>Payment Date *</label>
                                            <input type="date" class="form-control" id="payment_date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="payment_month" class="form-label fw-semibold"><i class="fas fa-calendar-alt me-1"></i>Payment Month *</label>
                                            <input type="month" class="form-control" id="payment_month" name="payment_month" required value="<?php echo (!empty($prefill_data['payment_month'])) ? htmlspecialchars($prefill_data['payment_month']) : date('Y-m'); ?>">
                                            <small class="form-text text-muted mt-1">Automatically set to next month after last payment</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="payment_method" class="form-label fw-semibold"><i class="fas fa-money-bill-wave me-1"></i>Payment Method</label>
                                            <select class="form-control" id="payment_method" name="payment_method">
                                                <option value="cash" <?php echo ($is_pay_due && $prefill_data['payment_method'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                                <option value="card" <?php echo ($is_pay_due && $prefill_data['payment_method'] == 'card') ? 'selected' : ''; ?>>Card</option>
                                                <option value="bank_transfer" <?php echo ($is_pay_due && $prefill_data['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                                                <option value="online" <?php echo ($is_pay_due && $prefill_data['payment_method'] == 'online') ? 'selected' : ''; ?>>Online</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label fw-semibold"><i class="fas fa-check-circle me-1"></i>Status</label>
                                            <select class="form-control" id="status" name="status" <?php echo ($is_pay_due || $is_repay) ? 'disabled' : ''; ?>>
                                                <option value="pending" <?php echo ($is_pay_due || (!$is_pay_due && !$is_repay && isset($_POST['status']) && $_POST['status'] == 'pending')) ? 'selected' : ''; ?>>Pending</option>
                                                <option value="completed" <?php echo ((!$is_pay_due && !$is_repay && isset($_POST['status']) && $_POST['status'] == 'completed')) ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo ((!$is_pay_due && !$is_repay && isset($_POST['status']) && $_POST['status'] == 'cancelled')) ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Description and Submit Section -->
                            <div class="card mb-4 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-comment me-2"></i>Description & Submit</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <label for="description" class="form-label fw-semibold"><i class="fas fa-sticky-note me-1"></i>Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Add any additional notes"><?php echo $is_pay_due ? 'Due payment completion' : ($is_repay ? 'Repayment for cancelled payment' : ''); ?></textarea>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success btn-lg fw-semibold" id="submitBtn">
                                            <i class="fas fa-save me-2"></i><?php echo $is_pay_due ? 'Complete Due Payment' : ($is_repay ? 'Repay Payment' : 'Add Payment'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <?php if (!empty($popup_message)): ?>
    <!-- Bootstrap Modal for Messages -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header <?php echo $popup_type == 'success' ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                    <h5 class="modal-title" id="messageModalLabel"><?php echo $popup_type == 'success' ? 'Success' : 'Notice'; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php echo nl2br(htmlspecialchars($popup_message)); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn <?php echo $popup_type == 'success' ? 'btn-success' : 'btn-danger'; ?>" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
        });
    </script>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('payment_date').valueAsDate = new Date();
        
        function updateProgress(){
            const sh=document.getElementById('student_id').value.trim();
            const sm=document.getElementById('student_name').value.trim();
            const se=document.getElementById('student_email').value.trim();
            const am=document.getElementById('amount').value.trim();
            const pm=document.getElementById('payment_method').value.trim();
            const st=document.getElementById('status').value.trim();
            let p=0;let s=[0,0,0,1];
            if(sh) p+=25,s[0]=1;
            if(sm&&se) p+=25,s[1]=1;
            if(am&&pm&&st) p+=25,s[2]=1;
            if(sh&&am&&pm) p+=25,s[3]=1;
            document.getElementById('progressBar').style.width=p+'%';
            document.getElementById('progressBar').setAttribute('aria-valuenow',p);
            ['step1','step2','step3','step4'].forEach((id,i)=>{
                const el=document.getElementById(id);
                el.classList.toggle('text-success',s[i]===1);
                el.classList.toggle('fw-bold',s[i]===1);
            });
        }
        
        document.getElementById('student_id').addEventListener('input',function(){
            const pos=this.selectionStart;this.value=this.value.toUpperCase();try{this.setSelectionRange(pos,pos);}catch(e){}
            const sid=this.value.trim();const ed=document.getElementById('student_id_error');
            if(sid.length>0){
                ed.style.display='none';
                fetch(`get_student.php?student_id=${encodeURIComponent(sid)}&user_id=${<?php echo $_SESSION['user_id']; ?>}`)
                    .then(r=>r.json())
                    .then(d=>{
                        if(d.success){
                            document.getElementById('student_name').value=`${d.data.first_name} ${d.data.last_name}`;
                            document.getElementById('student_email').value=d.data.email||'';
                            document.getElementById('student_phone').value=d.data.phone||'';
                            document.getElementById('student_course').value=d.data.course||'';
                            document.getElementById('amount').value=d.data.fee_amount||'';
                            document.getElementById('payment_month').value=d.data.next_payment_month||new Date().toISOString().substring(0,7);
                            document.getElementById('description').value=`Class fee payment for ${d.data.first_name} ${d.data.last_name} (${d.data.course||'N/A'})`;
                            ed.style.display='none';
                        }else{
                            document.getElementById('student_name').value='';
                            document.getElementById('student_email').value='';
                            document.getElementById('student_phone').value='';
                            document.getElementById('student_course').value='';
                            document.getElementById('amount').value='';
                            document.getElementById('payment_month').value='';
                            document.getElementById('description').value='';
                            ed.textContent='Student not found.';
                            ed.style.display='block';
                        }updateProgress();
                    })
                    .catch(e=>{ed.textContent='Error fetching student data.';ed.style.display='block';});
            }else{
                document.getElementById('student_name').value='';
                document.getElementById('student_email').value='';
                document.getElementById('student_phone').value='';
                document.getElementById('student_course').value='';
                document.getElementById('amount').value='';
                document.getElementById('description').value='';
                ed.style.display='none';
            }updateProgress();
        });
        
        document.getElementById('amount').addEventListener('input',updateProgress);
        document.getElementById('payment_method').addEventListener('change',updateProgress);
        document.getElementById('status').addEventListener('change',updateProgress);
        
        document.getElementById('paymentForm').addEventListener('keydown',function(e){if(e.key==='Enter') e.preventDefault();});
        
        var tooltipTriggerList=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));var tooltipList=tooltipTriggerList.map(function(el){return new bootstrap.Tooltip(el);});
        
        updateProgress();
    </script>
</body>
</html>
<?php
// Flush output buffer opened at top
if (ob_get_level()) {
    ob_end_flush();
}
?>