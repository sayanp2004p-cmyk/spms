<?php
session_start();
require_once 'php/db.php';

// Get user logo
function getUserLogo($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT logo FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $logo = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($logo['logo']) ? $logo['logo'] : 'img/logo.png';
    } catch (Exception $e) {
        return 'img/logo.png';
    }
}

$user_logo = isset($_SESSION['user_id']) ? getUserLogo($pdo, $_SESSION['user_id']) : 'img/logo.png';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Student Payment Management System</title>
    <link rel="icon" href="<?php echo htmlspecialchars($user_logo); ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $active_page = 'dashboard';
    include 'navbar.php';
    ?>
    <?php
    // Calculate dashboard statistics
    try {
        // Helper: Get dashboard stats
        function getDashboardStats($pdo, $user_id) {
            $stats = [];
            
            // Total students
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['totalStudents'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total payments
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats['totalPayments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total amount collected
            $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND user_id = ?");
            $stmt->execute([$user_id]);
            $stats['totalCollected'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Pending payments
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE status = 'pending' AND user_id = ?");
            $stmt->execute([$user_id]);
            $stats['pendingPayments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Completion rate
            $stats['completionRate'] = $stats['totalPayments'] > 0 ? round(($stats['totalPayments'] - $stats['pendingPayments']) / $stats['totalPayments'] * 100) : 0;
            
            // Recent activity (last 30 days)
            $stmt = $pdo->prepare("SELECT COUNT(*) as recent FROM payments WHERE user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $stmt->execute([$user_id]);
            $stats['recentActivity'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
            
            return $stats;
        }
        
        // Helper: Get activity trend for last 7 days
        function getActivityTrend($pdo, $user_id) {
            $activityTrend = [];
            $stmt = $pdo->prepare("
                SELECT DATE(payment_date) as date, COUNT(*) as count 
                FROM payments 
                WHERE user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(payment_date)
                ORDER BY DATE(payment_date) ASC
            ");
            $stmt->execute([$user_id]);
            $trendData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fill all 7 days with data (0 if no payments)
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $activityTrend[$date] = 0;
            }
            
            foreach ($trendData as $data) {
                $activityTrend[$data['date']] = $data['count'];
            }
            
            $activityTrend = array_values($activityTrend);
            return ['trend' => $activityTrend, 'max' => max($activityTrend) > 0 ? max($activityTrend) : 1];
        }
        
        // Get all stats
        $stats = getDashboardStats($pdo, $_SESSION['user_id']);
        $trendData = getActivityTrend($pdo, $_SESSION['user_id']);
        
        // Extract variables for easier use
        $totalStudents = $stats['totalStudents'];
        $totalPayments = $stats['totalPayments'];
        $totalCollected = $stats['totalCollected'];
        $pendingPayments = $stats['pendingPayments'];
        $completionRate = $stats['completionRate'];
        $recentActivity = $stats['recentActivity'];
        $activityTrend = $trendData['trend'];
        $maxActivity = $trendData['max'];
        
    } catch(PDOException $e) {
        $totalStudents = $totalPayments = $totalCollected = $pendingPayments = $completionRate = $recentActivity = 0;
        $activityTrend = array_fill(0, 7, 0);
        $maxActivity = 1;
    }

    // Helper: Get system health status
    function getSystemStatus($pdo) {
        $status = [
            'database' => ['status' => false, 'message' => 'Database Connection'],
            'email' => ['status' => false, 'message' => 'Email Service'],
            'storage' => ['status' => false, 'message' => 'File Storage'],
            'security' => ['status' => false, 'message' => 'Security']
        ];
        
        // Database check
        try {
            $pdo->query("SELECT 1");
            $status['database']['status'] = true;
        } catch(PDOException $e) {
            $status['database']['status'] = false;
        }
        
        // Email service check
        $status['email']['status'] = file_exists('smtp/class.phpmailer.php');
        
        // Storage check
        $storageOk = true;
        foreach (['img/'] as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                $storageOk = false;
                break;
            }
        }
        $status['storage']['status'] = $storageOk;
        
        // Security check
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $hasValidSession = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        $status['security']['status'] = $isHttps && $hasValidSession;
        
        return $status;
    }
    
    $systemStatus = getSystemStatus($pdo);

    // Main Stats Card Data
    $statsCards = [
        ['icon' => 'fa-users', 'title' => 'Total Students', 'value' => number_format($totalStudents), 'progress' => 100, 'color' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'],
        ['icon' => 'fa-receipt', 'title' => 'Total Payments', 'value' => number_format($totalPayments), 'progress' => $completionRate, 'color' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
        ['icon' => 'fa-indian-rupee-sign', 'title' => 'Amount Collected', 'value' => '₹' . number_format($totalCollected, 0), 'progress' => $completionRate, 'color' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
        ['icon' => 'fa-clock', 'title' => 'Pending Payments', 'value' => number_format($pendingPayments), 'progress' => $totalPayments > 0 ? round(($pendingPayments / $totalPayments) * 100) : 0, 'color' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)']
    ];

    // Action Cards Data
    $actionCards = [
        ['icon' => 'fa-credit-card', 'title' => 'Record Payment', 'desc' => 'Process fee payments instantly', 'link' => 'add_payment', 'btnText' => 'Add Payment', 'color' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'],
        ['icon' => 'fa-chart-bar', 'title' => 'View Reports', 'desc' => 'Access detailed analytics', 'link' => 'reports', 'btnText' => 'View Reports', 'color' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'],
        ['icon' => 'fa-user-plus', 'title' => 'Add Student', 'desc' => 'Register new students', 'link' => 'add_student', 'btnText' => 'Add Student', 'color' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)']
    ];
    ?>
    <div class="container-fluid mt-4 main-content">
        <!-- Hero Section -->
        <div class="hero-section mb-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 20px; color: white; padding: 4rem 3rem; position: relative; overflow: hidden;">
            <div class="hero-pattern" style="position: absolute; top: 0; right: 0; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%; transform: translate(50%, -50%);"></div>
            <div class="hero-pattern" style="position: absolute; bottom: 0; left: 0; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%; transform: translate(-50%, 50%);"></div>
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h1 class="display-4 fw-bold mb-3">
                        <i class="fas fa-graduation-cap me-3 text-warning"></i>
                        Student Payment Hub
                    </h1>
                    <p class="lead mb-4 opacity-90">Transform your fee collection with intelligent automation, real-time insights, and seamless student management.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="students" class="btn btn-warning btn-lg px-4 py-3 shadow-lg">
                            <i class="fas fa-users me-2"></i>Start Managing
                        </a>
                        <a href="reports" class="btn btn-outline-light btn-lg px-4 py-3 border-2">
                            <i class="fas fa-chart-line me-2"></i>View Analytics
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="hero-illustration">
                        <i class="fas fa-university fa-7x opacity-20 mb-3"></i>
                        <div class="floating-elements">
                            <div class="floating-card" style="position: absolute; top: 20%; right: 10%; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; backdrop-filter: blur(10px);">
                                <i class="fas fa-users text-warning fa-2x"></i>
                            </div>
                            <div class="floating-card" style="position: absolute; bottom: 30%; left: 15%; background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; backdrop-filter: blur(10px);">
                                <i class="fas fa-credit-card text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Row -->
        <div class="row mb-5 g-4">
            <div class="col-12">
                <h3 class="mb-4 fw-bold text-dark text-center">
                    <i class="fas fa-bolt me-2 text-warning"></i>
                    Quick Actions
                </h3>
            </div>
            <?php foreach ($actionCards as $card): ?>
            <div class="col-md-4">
                <div class="action-card-modern h-100" style="background: <?php echo $card['color']; ?>; border-radius: 20px; color: white; padding: 2rem; text-align: center; box-shadow: 0 10px 30px rgba(240, 147, 251, 0.3); transition: transform 0.3s ease;">
                    <div class="action-icon mb-4">
                        <i class="fas <?php echo $card['icon']; ?> fa opacity-80"></i>
                    </div>
                    <h4 class="fw-bold mb-3"><?php echo $card['title']; ?></h4>
                    <p class="opacity-75 mb-4"><?php echo $card['desc']; ?></p>
                    <a href="<?php echo $card['link']; ?>" class="btn btn-light btn-lg px-4 py-2 shadow">
                        <i class="fas fa-plus me-2"></i><?php echo $card['btnText']; ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Statistics Dashboard -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="mb-4 fw-bold text-dark text-center">
                    <i class="fas fa-chart-pie me-2 text-primary"></i>
                    Dashboard Overview
                </h3>
            </div>
        </div>

        <!-- Main Stats Row -->
        <div class="row mb-4 g-4">
            <?php foreach ($statsCards as $stat): ?>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-modern card border-0 h-100" style="background: <?php echo $stat['color']; ?>; border-radius: 15px; color: white; box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);">
                    <div class="card-body p-4 text-center">
                        <div class="stat-icon mb-3">
                            <i class="fas <?php echo $stat['icon']; ?> fa opacity-75"></i>
                        </div>
                        <h2 class="fw-bold mb-2"><?php echo $stat['value']; ?></h2>
                        <p class="mb-0 opacity-75 fs-6"><?php echo $stat['title']; ?></p>
                        <div class="progress mt-3" style="height: 4px; background: rgba(255,255,255,0.2);">
                            <div class="progress-bar bg-white" style="width: <?php echo $stat['progress']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Performance Metrics -->
        <div class="row mb-5 g-4">
            <div class="col-md-6">
                <div class="metric-card-modern card border-0 h-100" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(168, 237, 234, 0.2);">
                    <div class="card-body p-4 text-center">
                        <div class="metric-icon mb-3">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                        </div>
                        <h1 class="display-4 fw-bold text-dark mb-2"><?php echo $completionRate; ?>%</h1>
                        <h5 class="text-muted mb-3">Completion Rate</h5>
                        <div class="progress-circle mx-auto mb-3" style="width: 140px; height: 140px; position: relative; display: flex; align-items: center; justify-content: center;">
                            <svg width="140" height="140" viewBox="0 0 140 140" style="position: absolute; transform: rotate(-90deg);">
                                <circle cx="70" cy="70" r="60" stroke="rgba(0,0,0,0.1)" stroke-width="8" fill="none"/>
                                <circle cx="70" cy="70" r="60" stroke="#28a745" stroke-width="8" fill="none" 
                                    stroke-dasharray="<?php echo 2 * 3.14159 * 60; ?>" 
                                    stroke-dashoffset="<?php $offset = (2 * 3.14159 * 60) * (1 - $completionRate / 100); echo number_format($offset, 2); ?>" 
                                    stroke-linecap="round"
                                    style="transition: stroke-dashoffset 1.5s cubic-bezier(0.4, 0.0, 0.2, 1);"/>
                            </svg>
                            <div style="position: relative; z-index: 10; text-align: center;">
                                <div class="fw-bold text-dark" style="font-size: 32px;"><?php echo $completionRate; ?>%</div>
                                <div class="text-muted" style="font-size: 12px;">of payments done</div>
                            </div>
                        </div>
                        <p class="text-muted small">Payment completion efficiency</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card-modern card border-0 h-100" style="background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%); border-radius: 15px; box-shadow: 0 8px 25px rgba(210, 153, 194, 0.2);">
                    <div class="card-body p-4 text-center">
                        <div class="metric-icon mb-3">
                            <i class="fas fa-calendar-alt fa-4x text-info"></i>
                        </div>
                        <h1 class="display-4 fw-bold text-dark mb-2"><?php echo $recentActivity; ?></h1>
                        <h5 class="text-muted mb-3">Recent Activity</h5>
                        <p class="text-dark mb-3">Payments in last 7 days</p>
                        <div class="activity-indicator">
                            <div class="d-flex justify-content-center align-items-flex-end gap-2 mb-3" style="height: 100px;">
                                <?php foreach ($activityTrend as $index => $count): 
                                    $barHeight = $maxActivity > 0 ? ($count / $maxActivity) * 80 : 10;
                                    $isCurrentDay = $index === count($activityTrend) - 1;
                                ?>
                                <div class="activity-bar" title="<?php echo $count; ?> payments" style="
                                    width: 10px; 
                                    height: <?php echo $barHeight; ?>px; 
                                    background: linear-gradient(180deg, <?php echo $isCurrentDay ? '#28a745' : '#667eea'; ?>, <?php echo $isCurrentDay ? '#20c997' : '#764ba2'; ?>); 
                                    border-radius: 4px 4px 0 0; 
                                    transition: all 0.3s ease;
                                    cursor: pointer;
                                    opacity: <?php echo $isCurrentDay ? '1' : '0.7'; ?>;
                                    min-height: 2px;
                                " onmouseover="this.style.opacity='1'; this.style.transform='scaleY(1.1)';" onmouseout="this.style.opacity='<?php echo $isCurrentDay ? '1' : '0.7'; ?>'; this.style.transform='scaleY(1)';"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <p class="text-muted small">Activity trend over time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="row g-4">
            <!-- Recent Payments Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg" style="border-radius: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                    <div class="card-header bg-white border-0 py-4" style="border-radius: 15px 15px 0 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 fw-bold text-dark">
                                    <i class="fas fa-history me-2 text-primary"></i>
                                    Recent Transactions
                                </h4>
                                <p class="text-muted mb-0 small">Latest payment activities</p>
                            </div>
                            <a href="payments" class="btn btn-primary btn-sm fw-semibold px-3 py-2">
                                <i class="fas fa-arrow-right me-1"></i>View All
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table">
                                    <tr>
                                        <th class="border-0 ps-4 py-3">Student ID</th>
                                        <th class="border-0 py-3">Amount</th>
                                        <th class="border-0 py-3">Date</th>
                                        <th class="border-0 py-3">Method</th>
                                        <th class="border-0 pe-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $pdo->prepare("
                                            SELECT student_id, amount, payment_date, payment_method, status
                                            FROM payments
                                            WHERE user_id = ?
                                            ORDER BY payment_date DESC
                                            LIMIT 8
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($recentPayments) > 0) {
                                            foreach ($recentPayments as $payment) {
                                                $statusConfig = match($payment['status']) {
                                                    'completed' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Completed'],
                                                    'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Pending'],
                                                    'cancelled' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Cancelled'],
                                                    default => ['class' => 'secondary', 'icon' => 'question-circle', 'text' => 'Unknown']
                                                };
                                                ?>
                                                <tr class="border-bottom border-light">
                                                    <td class="ps-4 py-3">
                                                        <span class="badge bg-light text-dark fw-semibold"><?php echo htmlspecialchars($payment['student_id']); ?></span>
                                                    </td>
                                                    <td class="py-3 fw-bold text-success">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                                    <td class="py-3 text-muted"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                                    <td class="py-3">
                                                        <i class="fas fa-credit-card me-1 text-muted"></i><?php echo htmlspecialchars($payment['payment_method']); ?>
                                                    </td>
                                                    <td class="pe-4 py-3">
                                                        <span class="badge bg-<?php echo $statusConfig['class']; ?> px-2 py-1">
                                                            <i class="fas fa-<?php echo $statusConfig['icon']; ?> me-1"></i><?php echo $statusConfig['text']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3 text-secondary"></i>
                                                    <h5>No payments recorded yet.</h5>
                                                    <p>Start by adding your first payment record.</p>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } catch(PDOException $e) {
                                        ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="alert alert-danger mb-0">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    Error loading recent payments: <?php echo htmlspecialchars($e->getMessage()); ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Stats Card -->
                <div class="card border-0 shadow-lg mb-4" style="border-radius: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-header bg-transparent border-0 py-4">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Quick Insights
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-stat-item d-flex align-items-center mb-4">
                            <div class="stat-icon-sm me-3">
                                <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4"><?php echo $recentActivity; ?> payments</div>
                                <div class="opacity-75 small">This Month</div>
                            </div>
                        </div>
                        <div class="quick-stat-item d-flex align-items-center mb-4">
                            <div class="stat-icon-sm me-3">
                                <i class="fas fa-percentage fa-2x opacity-75"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4"><?php echo $completionRate; ?>%</div>
                                <div class="opacity-75 small">Completion Rate</div>
                            </div>
                        </div>
                        <div class="quick-stat-item d-flex align-items-center mb-4">
                            <div class="stat-icon-sm me-3">
                                <i class="fas fa-chart-line fa-2x opacity-75"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-4">₹<?php echo $totalPayments > 0 ? number_format($totalCollected / $totalPayments, 0) : '0'; ?></div>
                                <div class="opacity-75 small">Avg. Payment</div>
                            </div>
                        </div>
                        <hr class="my-4 opacity-25">
                        <div class="text-center">
                            <a href="reports" class="btn btn-light btn-lg w-100 shadow px-4 py-2">
                                <i class="fas fa-chart-bar me-2"></i>Detailed Reports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- System Health Card -->
                <div class="card border-0 shadow-lg" style="border-radius: 15px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="card-header bg-transparent border-0 py-4">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-shield-alt me-2"></i>
                            System Health
                        </h5>
                        <p class="mb-0 text-muted small">Quick status overview of critical services</p>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($systemStatus as $key => $status): ?>
                            <div class="col-6">
                                <div class="card h-100 border-0" style="background: rgba(255,255,255,0.15);">
                                    <div class="card-body p-3 d-flex align-items-center gap-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: <?php echo $status['status'] ? 'rgba(40, 167, 69, 0.25)' : 'rgba(220, 53, 69, 0.25)'; ?>;">
                                            <i class="fas fa-<?php echo $status['status'] ? 'check' : 'times'; ?> text-<?php echo $status['status'] ? 'success' : 'danger'; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold small mb-1"><?php echo htmlspecialchars($status['message']); ?></div>
                                            <div class="small opacity-75"><?php echo $status['status'] ? 'OK' : 'FAIL'; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-light btn-lg w-100 shadow px-4 py-2" onclick="checkSystemStatus()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh Status
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->

    <script>
    function checkSystemStatus() {
        const refreshBtn = event.target.closest('button');
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking...';
        refreshBtn.disabled = true;

        // Reload the page to refresh system checks
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    // Add smooth scrolling and animations
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stats on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe stat cards
        document.querySelectorAll('.stat-card-modern, .metric-card-modern').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Add hover effects for action cards
        document.querySelectorAll('.action-card-modern').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animate progress circles
        document.querySelectorAll('.progress-circle svg circle:last-child').forEach(circle => {
            const length = circle.getTotalLength();
            circle.style.strokeDasharray = length;
            circle.style.strokeDashoffset = length;
            setTimeout(() => {
                circle.style.strokeDashoffset = '0';
            }, 500);
        });
    });
    </script>
</body>
</html>