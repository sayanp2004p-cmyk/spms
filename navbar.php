<?php
// Reusable navigation bar (no dropdowns).
// Includes a logo, main navigation links, and logout/login.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active_page = isset($active_page) ? $active_page : '';
$activeClass = function ($page) use ($active_page) {
    return $active_page === $page ? 'active' : '';
};

$loggedIn = isset($_SESSION['user_id']);

// Determine logo path (fallback to default)
$logoPath = 'img/logo.png';
if ($loggedIn) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/php/db.php';
    }
    try {
        $stmt = $pdo->prepare("SELECT logo FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['logo'])) {
            $logoPath = $row['logo'];
        }
    } catch (Exception $e) {
        // ignore and use default logo
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="index">
            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="navbar-logo">
            <span class="navbar-title">SPMS</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon">
                <span class="bar bar1"></span>
                <span class="bar bar2"></span>
                <span class="bar bar3"></span>
            </span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($loggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('dashboard'); ?>" href="index"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('students'); ?>" href="students"><i class="fas fa-users"></i> Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('add_student'); ?>" href="add_student"><i class="fas fa-user-plus"></i> Add Student</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('payments'); ?>" href="payments"><i class="fas fa-money-bill-wave"></i> Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('add_payment'); ?>" href="add_payment"><i class="fas fa-plus"></i> Add Payment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="download_form"><i class="fas fa-download"></i> Download Form</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('reports'); ?>" href="reports"><i class="fas fa-chart-line"></i> Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('courses'); ?>" href="course_upload"><i class="fas fa-graduation-cap"></i> Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeClass('profile'); ?>" href="profile"><i class="fas fa-user"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login"><i class="fas fa-sign-in-alt"></i> Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div id="navbarOverlay" class="navbar-overlay" aria-hidden="true"></div>
