<?php
include 'php/db.php';

header('Content-Type: application/json');

// Helper function to get next payment month
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

if (isset($_GET['student_id']) && isset($_GET['user_id'])) {
    $student_id = trim($_GET['student_id']);
    $user_id = intval($_GET['user_id']);

    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, course, fee_amount FROM students WHERE student_id = ? AND user_id = ?");
        $stmt->execute([$student_id, $user_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $student['next_payment_month'] = getNextPaymentMonth($pdo, $student_id, $user_id);
            echo json_encode([
                'success' => true,
                'data' => $student
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found'
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Student ID and User ID required'
    ]);
}
?>