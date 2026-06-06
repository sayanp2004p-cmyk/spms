<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

require_once 'php/db.php';

$active_page = 'reports';
$user_id = $_SESSION['user_id'];

// Helper function to fetch stats
function fetchStat($pdo, $query, $params) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchAllStats($pdo, $query, $params) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper to generate KPI cards
function displayKPICard($title, $value, $label, $icon, $gradient) {
    echo "<div class='col-xl-3 col-md-6'>
        <div class='card h-100 border-0 shadow-sm stat-card' style='background: $gradient;'>
            <div class='card-body text-white p-4'>
                <div class='d-flex justify-content-between align-items-start mb-3'>
                    <div><p class='mb-1 small opacity-75'>$title</p>
                    <h2 class='mb-0 fw-bold'>$value</h2></div>
                    <i class='fas fa-$icon fa-2x opacity-50'></i>
                </div><small class='opacity-75'>$label</small>
            </div></div></div>";
}

// Helper to generate stat cards
function displayStatCard($title, $value, $label, $icon, $color) {
    echo "<div class='col-lg-3 col-md-6'>
        <div class='card h-100 border-0 shadow-sm'>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-center'>
                    <div><p class='text-muted small mb-1'>$title</p>
                    <h4 class='mb-0 text-$color fw-bold'>$value</h4></div>
                    <div class='text-$color opacity-25' style='font-size: 2.5rem;'>
                        <i class='fas fa-$icon'></i></div>
                </div></div></div></div>";
}

$stats = [];
$stats['total_students'] = fetchStat($pdo, "SELECT COUNT(*) as cnt FROM students WHERE user_id = ?", [$user_id])['cnt'] ?? 0;
$stats['total_payments'] = fetchStat($pdo, "SELECT COUNT(*) as cnt FROM payments WHERE user_id = ?", [$user_id])['cnt'] ?? 0;
$stats['total_amount'] = fetchStat($pdo, "SELECT SUM(amount) as amt FROM payments WHERE user_id = ? AND status = 'completed'", [$user_id])['amt'] ?? 0;
$stats['pending_payments'] = fetchStat($pdo, "SELECT COUNT(*) as cnt FROM payments WHERE user_id = ? AND status = 'pending'", [$user_id])['cnt'] ?? 0;

// Monthly data
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $row = fetchStat($pdo, "SELECT SUM(amount) as amt FROM payments WHERE user_id = ? AND DATE_FORMAT(payment_date, '%Y-%m') = ? AND status = 'completed'", [$user_id, $date]);
    $monthly_data[] = ['month' => date('M Y', strtotime($date)), 'amount' => (float)($row['amt'] ?? 0)];
}

// Fetch all distribution data
$payment_methods = array_map(fn($r) => ['method' => ucfirst(str_replace('_', ' ', $r['payment_method'])), 'count' => (int)$r['count']], fetchAllStats($pdo, "SELECT payment_method, COUNT(*) as count FROM payments WHERE user_id = ? GROUP BY payment_method", [$user_id]));

$payment_status = array_map(fn($r) => ['status' => ucfirst($r['status']), 'count' => (int)$r['count']], fetchAllStats($pdo, "SELECT status, COUNT(*) as count FROM payments WHERE user_id = ? GROUP BY status", [$user_id]));

$course_distribution = array_map(fn($r) => ['course' => $r['course'] ?: 'Not Specified', 'count' => (int)$r['count']], fetchAllStats($pdo, "SELECT course, COUNT(*) as count FROM students WHERE user_id = ? GROUP BY course", [$user_id]));

// Monthly admissions
$admission_data = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $row = fetchStat($pdo, "SELECT COUNT(*) as cnt FROM students WHERE user_id = ? AND DATE_FORMAT(admission_date, '%Y-%m') = ?", [$user_id, $date]);
    $admission_data[] = ['month' => date('M Y', strtotime($date)), 'count' => (int)($row['cnt'] ?? 0)];
}

// Recent data
$recent_payments = fetchAllStats($pdo, "SELECT p.amount, p.payment_date, p.status, s.first_name, s.last_name FROM payments p JOIN students s ON p.student_id = s.student_id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 5", [$user_id]);

$recent_students = fetchAllStats($pdo, "SELECT first_name, last_name, admission_date, course FROM students WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$user_id]);

$students_with_pending = fetchAllStats($pdo, "SELECT DISTINCT s.student_id, s.first_name, s.last_name, s.course, SUM(p.due_amount) as total_due FROM students s LEFT JOIN payments p ON s.student_id = p.student_id AND p.user_id = ? WHERE s.user_id = ? AND p.due_amount > 0 GROUP BY s.student_id, s.first_name, s.last_name, s.course", [$user_id, $user_id]);

$total_outstanding = fetchStat($pdo, "SELECT SUM(due_amount) as amt FROM payments WHERE user_id = ?", [$user_id])['amt'] ?? 0;

$students_no_payments = fetchStat($pdo, "SELECT COUNT(*) as cnt FROM students s WHERE s.user_id = ? AND s.student_id NOT IN (SELECT DISTINCT student_id FROM payments WHERE user_id = ?)", [$user_id, $user_id])['cnt'] ?? 0;

// Payment collection rate by course
$collection_rate = [];
$stmt = $pdo->prepare("
    SELECT 
        s.course,
        COUNT(DISTINCT s.student_id) as total_students,
        COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.student_id END) as paid_students
    FROM students s
    LEFT JOIN payments p ON s.student_id = p.student_id AND p.user_id = ?
    WHERE s.user_id = ?
    GROUP BY s.course
");
$stmt->execute([$user_id, $user_id]);
while ($row = $stmt->fetch()) {
    $rate = ($row['total_students'] > 0) ? round(($row['paid_students'] / $row['total_students']) * 100, 1) : 0;
    $collection_rate[] = [
        'course' => $row['course'] ?: 'Not Specified',
        'total' => (int)$row['total_students'],
        'paid' => (int)$row['paid_students'],
        'rate' => (float)$rate
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Reports - Student Payment Management System</title>
    <link rel="icon" href="img/logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">    <style>
        .stat-card {
            transition: all 0.3s ease;
            border-radius: 12px !important;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
        }
        
        .main-content {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .card {
            border-radius: 10px !important;
            border: none !important;
        }
        
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
    </style></head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4 main-content">
        <div class="row">
            <div class="col-12">
                <div class="mb-4">
                    <h1 class="h3 fw-bold mb-2"><i class="fas fa-chart-bar me-2 text-primary"></i>Reports & Analytics</h1>
                    <p class="text-muted">Dashboard overview of your payment management system</p>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs" id="reportsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
                            <i class="fas fa-tachometer-alt me-1"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                            <i class="fas fa-credit-card me-1"></i>Payments
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="false">
                            <i class="fas fa-user-graduate me-1"></i>Students
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content mt-4" id="reportsTabContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                        <!-- Main KPI Cards -->
                        <div class="row mb-4 g-3">
                            <?php
                            $kpis = [
                                ['TOTAL STUDENTS', $stats['total_students'], 'Enrolled', 'users', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
                                ['TOTAL PAYMENTS', $stats['total_payments'], 'Recorded', 'receipt', 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
                                ['AMOUNT COLLECTED', '₹' . number_format($stats['total_amount'], 0), 'Completed', 'coins', 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
                                ['PENDING PAYMENTS', $stats['pending_payments'], 'Awaiting', 'hourglass-half', 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)']
                            ];
                            foreach ($kpis as $kpi) {
                                echo "<div class='col-xl-3 col-md-6'><div class='card h-100 border-0 shadow-sm stat-card' style='background: {$kpi[4]};'><div class='card-body text-white p-4'><div class='d-flex justify-content-between align-items-start mb-3'><div><p class='mb-1 small opacity-75'>{$kpi[0]}</p><h2 class='mb-0 fw-bold'>{$kpi[1]}</h2></div><i class='fas fa-{$kpi[3]} fa-2x opacity-50'></i></div><small class='opacity-75'><i class='fas fa-check me-1'></i>{$kpi[2]}</small></div></div></div>";
                            }
                            ?>
                        </div>

                        <!-- Secondary Stats -->
                        <div class="row mb-4 g-3">
                            <?php
                            $collRate = ($stats['total_students'] > 0) ? round((($stats['total_students'] - $students_no_payments) / $stats['total_students']) * 100, 1) : 0;
                            $cards = [
                                ['Outstanding Amount', '₹' . number_format($total_outstanding, 2), 'danger', 'exclamation-triangle'],
                                ['Collection Rate', $collRate . '%', 'success', 'percentage'],
                                ['No Payments Yet', $students_no_payments, 'warning', 'user-tag'],
                                ['Avg Payment', '₹' . (($stats['total_payments'] > 0) ? number_format($stats['total_amount'] / $stats['total_payments'], 0) : 0), 'info', 'chart-pie']
                            ];
                            foreach ($cards as $c) {
                                echo "<div class='col-lg-3 col-md-6'><div class='card h-100 border-0 shadow-sm'><div class='card-body'><div class='d-flex justify-content-between align-items-center'><div><p class='text-muted small mb-1'>{$c[0]}</p><h4 class='mb-0 text-{$c[2]} fw-bold'>{$c[1]}</h4></div><div class='text-{$c[2]} opacity-25' style='font-size: 2.5rem;'><i class='fas fa-{$c[3]}'></i></div></div></div></div></div>";
                            }
                            ?>
                        </div>

                        <!-- Charts Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white border-bottom py-3">
                                        <h5 class="mb-0 fw-semibold"><i class="fas fa-chart-line me-2 text-primary"></i>Monthly Payment Trends</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <canvas id="monthlyChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Tab -->
                    <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-line me-2"></i>Monthly Payment Trends</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="monthlyChart2" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie me-2"></i>Payment Methods</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="methodsChart" width="200" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Payment Status Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="statusChart" width="300" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-history me-2"></i>Recent Payments</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($recent_payments)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No payments found.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th><i class="fas fa-user me-1"></i>Student</th>
                                                            <th><i class="fas fa-rupee-sign me-1"></i>Amount</th>
                                                            <th><i class="fas fa-calendar me-1"></i>Date</th>
                                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_payments as $payment): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                                                <td class="fw-semibold">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?php 
                                                                        echo $payment['status'] === 'completed' ? 'success' : 
                                                                             ($payment['status'] === 'pending' ? 'warning' : 'secondary'); 
                                                                    ?>">
                                                                        <i class="fas fa-<?php echo $payment['status'] === 'completed' ? 'check' : ($payment['status'] === 'pending' ? 'clock' : 'times'); ?> me-1"></i>
                                                                        <?php echo ucfirst($payment['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Students Tab -->
                    <div class="tab-pane fade" id="students" role="tabpanel" aria-labelledby="students-tab">
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <div class="card text-center h-100 shadow-sm border-info">
                                    <div class="card-body">
                                        <i class="fas fa-user-graduate fa-3x text-info mb-3"></i>
                                        <h3 class="card-title text-info"><?php echo $stats['total_students']; ?></h3>
                                        <p class="card-text fw-semibold">Total Students</p>
                                        <small class="text-muted">Enrolled</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card text-center h-100 shadow-sm border-warning">
                                    <div class="card-body">
                                        <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                                        <h3 class="card-title text-warning"><?php echo $students_no_payments; ?></h3>
                                        <p class="card-text fw-semibold">No Payments Yet</p>
                                        <small class="text-muted">Never paid</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card text-center h-100 shadow-sm border-danger">
                                    <div class="card-body">
                                        <i class="fas fa-coin fa-3x text-danger mb-3"></i>
                                        <h3 class="card-title text-danger">₹<?php echo number_format($total_outstanding, 2); ?></h3>
                                        <p class="card-text fw-semibold">Outstanding</p>
                                        <small class="text-muted">Due amount</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card text-center h-100 shadow-sm border-success">
                                    <div class="card-body">
                                        <i class="fas fa-percentage fa-3x text-success mb-3"></i>
                                        <h3 class="card-title text-success"><?php echo ($stats['total_students'] > 0) ? round((($stats['total_students'] - $students_no_payments) / $stats['total_students']) * 100, 1) : 0; ?>%</h3>
                                        <p class="card-text fw-semibold">Collection Rate</p>
                                        <small class="text-muted">Have paid</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-line me-2"></i>Monthly Student Admissions</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="admissionChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-pie me-2"></i>Course Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="courseChart" width="200" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-chart-bar me-2"></i>Payment Collection Rate by Course</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="collectionRateChart" width="300" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-list me-2"></i>Course Collection Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($collection_rate)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No course data found.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Course</th>
                                                            <th class="text-center">Total</th>
                                                            <th class="text-center">Paid</th>
                                                            <th class="text-center">Rate</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($collection_rate as $course): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($course['course']); ?></td>
                                                                <td class="text-center"><?php echo $course['total']; ?></td>
                                                                <td class="text-center"><strong><?php echo $course['paid']; ?></strong></td>
                                                                <td class="text-center">
                                                                    <span class="badge bg-<?php echo $course['rate'] >= 75 ? 'success' : ($course['rate'] >= 50 ? 'warning' : 'danger'); ?>">
                                                                        <?php echo $course['rate']; ?>%
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Students with Pending Payments</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($students_with_pending)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                                <p class="text-success fw-semibold">All students are up to date!</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th><i class="fas fa-id-card me-1"></i>Student ID</th>
                                                            <th><i class="fas fa-user me-1"></i>Name</th>
                                                            <th><i class="fas fa-book me-1"></i>Course</th>
                                                            <th class="text-right"><i class="fas fa-rupee-sign me-1"></i>Outstanding</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($students_with_pending as $student): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($student['course'] ?: 'Not Specified'); ?></td>
                                                                <td class="text-right">
                                                                    <span class="badge bg-danger">₹<?php echo number_format($student['total_due'], 2); ?></span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-user-plus me-2"></i>Recent Student Additions</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($recent_students)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No students found.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th><i class="fas fa-user me-1"></i>Name</th>
                                                            <th><i class="fas fa-book me-1"></i>Course</th>
                                                            <th><i class="fas fa-calendar-check me-1"></i>Admission Date</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($recent_students as $student): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($student['course'] ?: 'Not Specified'); ?></td>
                                                                <td><?php echo $student['admission_date'] ? date('M d, Y', strtotime($student['admission_date'])) : 'Not Set'; ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="js/validation.js"></script>

    <script>
        // Function to create monthly chart
        function createMonthlyChart(canvasId) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
                    datasets: [{
                        label: 'Payment Amount (₹)',
                        data: <?php echo json_encode(array_column($monthly_data, 'amount')); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize charts
        let monthlyChart, monthlyChart2, methodsChart, statusChart, admissionChart, courseChart;

        // Create charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            monthlyChart = createMonthlyChart('monthlyChart');
            monthlyChart2 = createMonthlyChart('monthlyChart2');

            const methodsCtx = document.getElementById('methodsChart').getContext('2d');
            methodsChart = new Chart(methodsCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($payment_methods, 'method')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($payment_methods, 'count')); ?>,
                        backgroundColor: [
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)',
                            'rgb(153, 102, 255)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });

            const statusCtx = document.getElementById('statusChart').getContext('2d');
            
            // Prepare status data with meaningful colors
            const statusLabels = <?php echo json_encode(array_column($payment_status, 'status')); ?>;
            const statusCounts = <?php echo json_encode(array_column($payment_status, 'count')); ?>;
            
            // Map status to colors (Completed=Green, Pending=Orange, Cancelled=Red)
            const statusColorMap = {
                'Completed': 'rgb(75, 192, 75)',      // Green
                'Pending': 'rgb(255, 159, 64)',        // Orange
                'Cancelled': 'rgb(255, 99, 132)'       // Red
            };
            
            const statusColors = statusLabels.map(status => statusColorMap[status] || 'rgb(200, 200, 200)');
            
            statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: statusColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            const admissionCtx = document.getElementById('admissionChart').getContext('2d');
            admissionChart = new Chart(admissionCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($admission_data, 'month')); ?>,
                    datasets: [{
                        label: 'New Students',
                        data: <?php echo json_encode(array_column($admission_data, 'count')); ?>,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            const courseCtx = document.getElementById('courseChart').getContext('2d');
            courseChart = new Chart(courseCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($course_distribution, 'course')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($course_distribution, 'count')); ?>,
                        backgroundColor: [
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)',
                            'rgb(153, 102, 255)',
                            'rgb(255, 159, 64)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });

            // Collection Rate Chart
            const collectionCtx = document.getElementById('collectionRateChart').getContext('2d');
            new Chart(collectionCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($collection_rate, 'course')); ?>,
                    datasets: [{
                        label: 'Collection Rate (%)',
                        data: <?php echo json_encode(array_column($collection_rate, 'rate')); ?>,
                        backgroundColor: [
                            'rgb(75, 192, 75)',
                            'rgb(255, 159, 64)',
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(153, 102, 255)',
                            'rgb(255, 205, 86)'
                        ],
                        borderRadius: 5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.x.toFixed(1) + '%';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>