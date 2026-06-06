<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['payment_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$payment_id = $_GET['payment_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get payment details with student information
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            s.first_name,
            s.last_name,
            s.email,
            s.phone,
            s.course,
            s.class_type,
            s.fee_amount,
            s.admission_date,
            u.email_for_sending as institute_email
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.student_id AND s.user_id = p.user_id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$payment_id, $user_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo '<div class="alert alert-warning">Payment not found</div>';
        exit;
    }

    // Get user/institute information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format receipt
    ?>
    <div class="receipt-container" style="max-width: 600px; margin: 0 auto; font-family: 'Courier New', monospace;">
        <!-- Header -->
        <div class="receipt-header text-center mb-4" style="border-bottom: 2px solid #333; padding-bottom: 20px;">
            <h3 class="mb-2" style="color: #333; font-weight: bold;">
                <i class="fas fa-university me-2"></i><?php echo htmlspecialchars($user['username'] ?? 'Institute'); ?>
            </h3>
            <p class="mb-1" style="font-size: 14px; color: #666;">
                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email_for_sending'] ?? $user['email']); ?>
            </p>
            <h4 class="mt-3" style="color: #007bff;">PAYMENT RECEIPT</h4>
        </div>

        <!-- Receipt Details -->
        <div class="receipt-details mb-4">
            <div class="row mb-3">
                <div class="col-6">
                    <strong>Receipt No:</strong> #<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Date:</strong> <?php echo date('d/m/Y', strtotime($payment['created_at'])); ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-6">
                    <strong>Payment Date:</strong> <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                </div>
                <div class="col-6 text-end">
                    <strong>Time:</strong> <?php echo date('H:i:s', strtotime($payment['created_at'])); ?>
                </div>
            </div>
        </div>

        <!-- Student Information -->
        <div class="student-info mb-4" style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background: #f9f9f9;">
            <h5 class="mb-3" style="color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px;">
                <i class="fas fa-user me-2"></i>Student Information
            </h5>
            <div class="row">
                <div class="col-6">
                    <p class="mb-1"><strong>Student ID:</strong> <?php echo htmlspecialchars($payment['student_id']); ?></p>
                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($payment['email']); ?></p>
                </div>
                <div class="col-6">
                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($payment['phone']); ?></p>
                    <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars($payment['course']); ?></p>
                    <?php if (!empty($payment['class_type'])): ?>
                        <p class="mb-1"><strong>Class Type:</strong> <?php echo htmlspecialchars($payment['class_type']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-6">
                    <p class="mb-0"><strong>Admission Date:</strong> <?php echo date('d/m/Y', strtotime($payment['admission_date'])); ?></p>
                </div>
                <div class="col-6">
                    <p class="mb-0"><strong>Monthly Fee:</strong> ₹<?php echo number_format($payment['fee_amount'], 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="payment-info mb-4" style="border: 1px solid #007bff; padding: 15px; border-radius: 5px; background: #f0f8ff;">
            <h5 class="mb-3" style="color: #007bff; border-bottom: 1px solid #007bff; padding-bottom: 5px;">
                <i class="fas fa-credit-card me-2"></i>Payment Details
            </h5>
            <div class="row">
                <div class="col-6">
                    <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                    <p class="mb-1"><strong>Status:</strong>
                        <span class="badge bg-<?php
                            echo match($payment['status']) {
                                'completed' => 'success',
                                'pending' => 'warning',
                                'cancelled' => 'danger',
                                default => 'secondary'
                            };
                        ?>">
                            <?php echo ucfirst($payment['status']); ?>
                        </span>
                    </p>
                    <?php if (!empty($payment['payment_month'])): ?>
                        <p class="mb-1"><strong>Payment Month:</strong> <?php echo date('F Y', strtotime($payment['payment_month'] . '-01')); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <div style="font-size: 18px; font-weight: bold; color: #007bff;">
                        <strong>Amount Paid:</strong><br>
                        ₹<?php echo number_format($payment['amount'], 2); ?>
                    </div>
                    <?php if ($payment['due_amount'] > 0): ?>
                        <div class="mt-2" style="color: #dc3545;">
                            <strong>Due Amount:</strong><br>
                            ₹<?php echo number_format($payment['due_amount'], 2); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($payment['description'])): ?>
                <div class="mt-3">
                    <strong>Description:</strong><br>
                    <em><?php echo htmlspecialchars($payment['description']); ?></em>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="receipt-footer text-center" style="border-top: 1px solid #333; padding-top: 20px; color: #666;">
            <p class="mb-1"><strong>Thank you for your payment!</strong></p>
            <p class="mb-0 small">Generated on <?php echo date('d/m/Y H:i:s'); ?> | Receipt ID: <?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></p>
            <?php if (!empty($payment['payment_code'])): ?>
                <p class="mb-0 small">Payment Code: <?php echo htmlspecialchars($payment['payment_code']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
    .receipt-container {
        font-size: 14px;
        line-height: 1.4;
    }
    .receipt-container .row {
        margin-bottom: 8px;
    }
    .receipt-container .badge {
        font-size: 12px;
    }
    @page {
        size: 80mm auto;
        margin: 5mm;
    }
    @media print {
        body {
            margin: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .receipt-container {
            font-size: 12px;
        }
        .receipt-container .btn,
        .modal-footer {
            display: none !important;
        }
    }
    </style>
    <?php

} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>