<?php
session_start();
require_once 'php/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

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

$user_logo = getUserLogo($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Payments - Student Payment Management System</title>
    <link rel="icon" href="<?php echo htmlspecialchars($user_logo); ?>" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    $active_page = 'payments';
    include 'navbar.php';
    ?>
    <!-- Main Content -->
    <div class="container mt-5 main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-receipt me-2"></i>Payment Records</h2>
            <a href="add_payment" class="btn btn-success">Add New Payment</a>
        </div>

        <?php
        $filter_student = isset($_GET['student_id']) ? $_GET['student_id'] : '';
        $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
        $filter_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
        $filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
        
        // Pagination
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Count active filters
        $active_filters = 0;
        if (!empty($filter_student)) $active_filters++;
        if (!empty($filter_status)) $active_filters++;
        if (!empty($filter_method)) $active_filters++;
        if (!empty($filter_date_from)) $active_filters++;
        if (!empty($filter_date_to)) $active_filters++;
        ?>

        <!-- Filters -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#filtersCollapse" role="button">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-filter me-2"></i>Filters
                        <?php if ($active_filters > 0): ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $active_filters; ?> Active</span>
                        <?php endif; ?>
                    </h5>
                    <i class="fas fa-chevron-down" id="filterChevron" style="transition: transform 0.3s ease;"></i>
                </div>
            </div>
            <div id="filtersCollapse" class="collapse <?php echo $active_filters > 0 ? 'show' : ''; ?>">
                <div class="card-body">
                    <!-- Active Filters Summary -->
                    <?php if ($active_filters > 0): ?>
                    <div class="alert alert-info d-flex justify-content-between align-items-center mb-3" role="alert">
                        <div>
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Active Filters:</strong>
                            <?php 
                                $filters_applied = [];
                                if (!empty($filter_student)) $filters_applied[] = "Student ID: " . htmlspecialchars($filter_student);
                                if (!empty($filter_status)) $filters_applied[] = "Status: " . ucfirst($filter_status);
                                if (!empty($filter_method)) $filters_applied[] = "Method: " . str_replace('_', ' ', htmlspecialchars($filter_method));
                                if (!empty($filter_date_from)) $filters_applied[] = "From: " . htmlspecialchars($filter_date_from);
                                if (!empty($filter_date_to)) $filters_applied[] = "To: " . htmlspecialchars($filter_date_to);
                                echo implode(", ", $filters_applied);
                            ?>
                        </div>
                        <a href="payments" class="btn btn-sm btn-outline-info ms-2">Clear All</a>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-3"><i class="fas fa-info-circle me-1"></i>Use the filters below to search for specific payments. Leave fields empty to show all records.</p>
                    <form method="GET">
                        <div class="row mb-3 g-3">
                            <div class="col-md-3">
                                <label for="student_id" class="form-label fw-600">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Filter by Student ID" value="<?php echo htmlspecialchars($filter_student); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label fw-600">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>✓ Completed</option>
                                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                    <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>✕ Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="payment_method" class="form-label fw-600">Payment Method</label>
                                <select class="form-control" id="payment_method" name="payment_method">
                                    <option value="">All Methods</option>
                                    <option value="cash" <?php echo $filter_method == 'cash' ? 'selected' : ''; ?>>💵 Cash</option>
                                    <option value="card" <?php echo $filter_method == 'card' ? 'selected' : ''; ?>>💳 Card</option>
                                    <option value="bank_transfer" <?php echo $filter_method == 'bank_transfer' ? 'selected' : ''; ?>>🏦 Bank Transfer</option>
                                    <option value="online" <?php echo $filter_method == 'online' ? 'selected' : ''; ?>>🌐 Online</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="payments" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-1"></i>Clear
                                </a>
                            </div>
                        </div>
                        <div class="row g-3" id="dateFilters">
                            <div class="col-md-6">
                                <label for="date_from" class="form-label fw-600">
                                    <i class="fas fa-calendar me-1"></i>Date From
                                </label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="date_to" class="form-label fw-600">
                                    <i class="fas fa-calendar me-1"></i>Date To
                                </label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Collapse Icon Animation Script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const collapseElement = document.getElementById('filtersCollapse');
            const chevron = document.getElementById('filterChevron');
            const header = chevron.closest('.card-header');
            
            if (collapseElement && chevron) {
                collapseElement.addEventListener('show.bs.collapse', function() {
                    chevron.style.transform = 'rotate(180deg)';
                });
                collapseElement.addEventListener('hide.bs.collapse', function() {
                    chevron.style.transform = 'rotate(0deg)';
                });
                
                // Make header clickable
                header.addEventListener('click', function(e) {
                    if (e.target.closest('.btn')) return; // Don't trigger if clicking button
                    new bootstrap.Collapse(collapseElement).toggle();
                });
            }
        });
        </script>

        <!-- Payment Records -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Payment Records</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Helper: Get status and method icons
                    function getStatusClass($status) {
                        return match($status) {
                            'completed' => 'success',
                            'pending' => 'warning',
                            'cancelled' => 'danger',
                            default => 'secondary'
                        };
                    }
                    
                    function getMethodIcon($method) {
                        return match($method) {
                            'cash' => 'fas fa-money-bill-wave',
                            'card' => 'fas fa-credit-card',
                            'bank_transfer' => 'fas fa-university',
                            'online' => 'fas fa-globe',
                            default => 'fas fa-question-circle'
                        };
                    }
                    
                    // Helper: Render single payment card
                    function renderPaymentCard($payment) {
                        $statusClass = getStatusClass($payment['status']);
                        $methodIcon = getMethodIcon($payment['payment_method']);
                        $statusBadge = "<span class='badge bg-{$statusClass}'>{$payment['status']}</span>";
                        ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light">
                                    <h6 class="card-title mb-0"><i class="<?php echo $methodIcon; ?> me-2"></i>Payment #<?php echo $payment['id']; ?></h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><strong>Student ID:</strong> <?php echo htmlspecialchars($payment['student_id']); ?></p>
                                    <p class="mb-2"><strong>Amount:</strong> ₹<?php echo number_format($payment['amount'], 2); ?></p>
                                    <p class="mb-2"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></p>
                                    <p class="mb-2"><strong>Method:</strong> <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span></p>
                                    <p class="mb-2"><strong>Status:</strong> <?php echo $statusBadge; ?></p>
                                    <?php if (!empty($payment['description'])): ?>
                                        <p class="mb-2"><strong>Description:</strong> <?php echo htmlspecialchars($payment['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($payment['payment_month'])): ?>
                                        <p class="mb-0"><strong>Month:</strong> <?php echo date('F Y', strtotime($payment['payment_month'] . '-01')); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#receiptModal" data-payment-id="<?php echo $payment['id']; ?>">
                                            <i class="fas fa-receipt me-1"></i>Receipt
                                        </button>
                                        <?php if ($payment['status'] == 'pending' && $payment['due_amount'] > 0): ?>
                                            <a href="add_payment?pay_due=1&payment_id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-plus-circle me-1"></i>Pay Due (₹<?php echo number_format($payment['due_amount'], 2); ?>)
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($payment['status'] == 'cancelled'): ?>
                                            <a href="add_payment?repay=1&payment_id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-redo me-1"></i>Repay
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    
                    // Helper: Render empty state message
                    function renderEmptyState($icon, $title, $message, $type = 'info') {
                        $iconColor = $type === 'error' ? 'text-danger' : 'text-muted';
                        $borderClass = $type === 'error' ? 'border-danger' : '';
                        ?>
                        <div class="col-12">
                            <div class="card text-center <?php echo $borderClass; ?>">
                                <div class="card-body">
                                    <i class="<?php echo $icon; ?> fa-3x <?php echo $iconColor; ?> mb-3"></i>
                                    <h5 class="card-title <?php echo $type === 'error' ? 'text-danger' : ''; ?>"><?php echo $title; ?></h5>
                                    <p class="card-text text-muted"><?php echo $message; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    
                    // Helper: Build filtered payments query
                    function getFilteredPayments($pdo, $user_id, $filters, $limit = null, $offset = null) {
                        $query = "SELECT * FROM payments WHERE user_id = ?";
                        $params = [$user_id];

                        if (!empty($filters['student_id'])) {
                            $query .= " AND student_id = ?";
                            $params[] = $filters['student_id'];
                        }
                        if (!empty($filters['status'])) {
                            $query .= " AND status = ?";
                            $params[] = $filters['status'];
                        }
                        if (!empty($filters['payment_method'])) {
                            $query .= " AND payment_method = ?";
                            $params[] = $filters['payment_method'];
                        }
                        if (!empty($filters['date_from'])) {
                            $query .= " AND payment_date >= ?";
                            $params[] = $filters['date_from'];
                        }
                        if (!empty($filters['date_to'])) {
                            $query .= " AND payment_date <= ?";
                            $params[] = $filters['date_to'];
                        }

                        $query .= " ORDER BY id DESC";
                        
                        if ($limit !== null && $offset !== null) {
                            $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
                        }
                        
                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        return $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    // Helper: Count filtered payments
                    function countFilteredPayments($pdo, $user_id, $filters) {
                        $query = "SELECT COUNT(*) as total FROM payments WHERE user_id = ?";
                        $params = [$user_id];

                        if (!empty($filters['student_id'])) {
                            $query .= " AND student_id = ?";
                            $params[] = $filters['student_id'];
                        }
                        if (!empty($filters['status'])) {
                            $query .= " AND status = ?";
                            $params[] = $filters['status'];
                        }
                        if (!empty($filters['payment_method'])) {
                            $query .= " AND payment_method = ?";
                            $params[] = $filters['payment_method'];
                        }
                        if (!empty($filters['date_from'])) {
                            $query .= " AND payment_date >= ?";
                            $params[] = $filters['date_from'];
                        }
                        if (!empty($filters['date_to'])) {
                            $query .= " AND payment_date <= ?";
                            $params[] = $filters['date_to'];
                        }

                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        return $stmt->fetch()['total'];
                    }
                    
                    try {
                        $filters = [
                            'student_id' => $filter_student,
                            'status' => $filter_status,
                            'payment_method' => $filter_method,
                            'date_from' => $filter_date_from,
                            'date_to' => $filter_date_to
                        ];
                        
                        // Get total count
                        $total_payments = countFilteredPayments($pdo, $_SESSION['user_id'], $filters);
                        $total_pages = ceil($total_payments / $per_page);
                        
                        // Adjust page if it exceeds total pages
                        if ($page > $total_pages && $total_pages > 0) {
                            $page = $total_pages;
                            $offset = ($page - 1) * $per_page;
                        }
                        
                        $payments = getFilteredPayments($pdo, $_SESSION['user_id'], $filters, $per_page, $offset);

                        if (count($payments) > 0) {
                            foreach ($payments as $payment) {
                                renderPaymentCard($payment);
                            }
                        } else {
                            renderEmptyState('fas fa-receipt', 'No Payments Found', 'No payments match your current filters. Try adjusting your search criteria.');
                        }
                    } catch(PDOException $e) {
                        renderEmptyState('fas fa-exclamation-triangle', 'Error Loading Payments', htmlspecialchars($e->getMessage()), 'error');
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="card mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        <small>Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $per_page, $total_payments); ?></strong> of <strong><?php echo $total_payments; ?></strong> payments</small>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="payments?page=1<?php echo !empty($filter_student) ? '&student_id=' . urlencode($filter_student) : ''; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_method) ? '&payment_method=' . urlencode($filter_method) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="payments?page=<?php echo $page - 1; ?><?php echo !empty($filter_student) ? '&student_id=' . urlencode($filter_student) : ''; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_method) ? '&payment_method=' . urlencode($filter_method) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            // Show page numbers
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = $i == $page ? 'active' : '';
                                ?>
                                <li class="page-item <?php echo $active; ?>">
                                    <a class="page-link" href="payments?page=<?php echo $i; ?><?php echo !empty($filter_student) ? '&student_id=' . urlencode($filter_student) : ''; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_method) ? '&payment_method=' . urlencode($filter_method) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php
                            }
                            
                            if ($end_page < $total_pages) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="payments?page=<?php echo $page + 1; ?><?php echo !empty($filter_student) ? '&student_id=' . urlencode($filter_student) : ''; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_method) ? '&payment_method=' . urlencode($filter_method) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="payments?page=<?php echo $total_pages; ?><?php echo !empty($filter_student) ? '&student_id=' . urlencode($filter_student) : ''; ?><?php echo !empty($filter_status) ? '&status=' . urlencode($filter_status) : ''; ?><?php echo !empty($filter_method) ? '&payment_method=' . urlencode($filter_method) : ''; ?><?php echo !empty($filter_date_from) ? '&date_from=' . urlencode($filter_date_from) : ''; ?><?php echo !empty($filter_date_to) ? '&date_to=' . urlencode($filter_date_to) : ''; ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="receiptModalLabel">
                        <i class="fas fa-receipt me-2"></i>Payment Receipt
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="receiptContent">
                    <!-- Receipt content will be loaded here -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading receipt...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="downloadPdfBtn">
                        <i class="fas fa-file-pdf me-1"></i>Download PDF
                    </button>
                    <button type="button" class="btn btn-primary" id="printReceiptBtn">
                        <i class="fas fa-print me-1"></i>Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>

    <!-- Receipt Modal Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const receiptModal = document.getElementById('receiptModal');
        const receiptContent = document.getElementById('receiptContent');
        const printReceiptBtn = document.getElementById('printReceiptBtn');
        const downloadPdfBtn = document.getElementById('downloadPdfBtn');

        receiptModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const paymentId = button.getAttribute('data-payment-id');

            // Load receipt content
            loadReceiptContent(paymentId);
        });

        function loadReceiptContent(paymentId) {
            fetch(`php/get_payment_receipt.php?payment_id=${paymentId}`)
                .then(response => response.text())
                .then(html => {
                    receiptContent.innerHTML = html;
                })
                .catch(error => {
                    receiptContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading receipt: ${error.message}
                        </div>
                    `;
                });
        }

        // Print receipt functionality
        printReceiptBtn.addEventListener('click', function() {
            const receiptElement = receiptContent.querySelector('.receipt-container');
            if (!receiptElement) {
                return;
            }

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body { margin: 0; font-family: Arial, sans-serif; }
                        @page { size: 80mm auto; margin: 5mm; }
                        .receipt-container { width: 100%; max-width: 80mm; margin: 0 auto; }
                        .receipt-container .btn, .modal-footer { display: none !important; }
                    </style>
                </head>
                <body>${receiptElement.outerHTML}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        });

        // Download PDF functionality
        downloadPdfBtn.addEventListener('click', function() {
            const receiptElement = receiptContent.querySelector('.receipt-container');
            if (!receiptElement) {
                return;
            }

            const opt = {
                margin: [5, 5, 5, 5],
                filename: `receipt_${Date.now()}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: [80, 297], orientation: 'portrait' }
            };

            html2pdf().set(opt).from(receiptElement).save();
        });
    });
    </script>
</body>
</html>