<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Students - Student Payment Management System</title>
    <link rel="icon" href="img/logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login');
        exit;
    }
    $active_page = 'students';
    include 'navbar.php';
    ?>
    <!-- Main Content -->
    <div class="container mt-4 main-content">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h2 class="h4 fw-bold mb-0"><i class="fas fa-users me-2 text-primary"></i>Students</h2>
            </div>
            <div class="col-auto">
                <div class="btn-group">
                    <a href="add_student" class="btn btn-primary" title="Add New Student">
                        <i class="fas fa-user-plus me-2"></i>Add Student
                    </a>
                    <button id="downloadDataBtn" class="btn btn-outline-secondary" title="Export Data">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Export Options Modal -->
        <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content border-0">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-semibold">Export Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="exportForm">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_type" id="export_students" value="students" checked>
                                <label class="form-check-label" for="export_students">Student data</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_type" id="export_payments" value="payments">
                                <label class="form-check-label" for="export_payments">Payment data</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="export_type" id="export_all" value="all">
                                <label class="form-check-label" for="export_all">All data (zipped)</label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmExportBtn" class="btn btn-primary">Download</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body py-4">
                <?php
                $popup_message = '';
                $popup_type = 'info';

                include 'php/db.php';
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student_id'])) {
                    $delete_id = $_POST['delete_student_id'];
                    try {
                        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ? AND user_id = ?");
                        $stmt->execute([$delete_id, $_SESSION['user_id']]);
                        $popup_message = 'Student deleted successfully!';
                        $popup_type = 'success';
                    } catch(PDOException $e) {
                        $popup_message = 'Error deleting student: ' . $e->getMessage();
                        $popup_type = 'danger';
                    }
                }

                // Get filter values
                $filter_course = isset($_GET['course']) ? $_GET['course'] : '';
                $filter_year = isset($_GET['year']) ? $_GET['year'] : '';
                $filter_not_paid = isset($_GET['not_paid_3m']) ? '1' : '';
                $filter_class_type = isset($_GET['class_type']) ? $_GET['class_type'] : '';
                // Search term
                $search = isset($_GET['q']) ? trim($_GET['q']) : '';
                
                // Pagination
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $per_page = 10;
                $offset = ($page - 1) * $per_page;

                // Check if any filters are active
                $filters_active = !empty($search) || !empty($filter_course) || !empty($filter_year) || !empty($filter_not_paid) || !empty($filter_class_type);

                // Get distinct courses for filter
                $courses = [];
                try {
                    $stmt = $pdo->prepare("SELECT DISTINCT course FROM students WHERE course != '' AND user_id = ? ORDER BY course");
                    $stmt->execute([$_SESSION['user_id']]);
                    $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch(PDOException $e) {
                    // Ignore error
                }

                // Get distinct admission years
                $years = [];
                try {
                    $stmt = $pdo->prepare("SELECT DISTINCT YEAR(admission_date) as year FROM students WHERE user_id = ? ORDER BY year DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch(PDOException $e) {
                    // Ignore error
                }

                // Get distinct class_types
                $class_types = [];
                try {
                    $stmt = $pdo->prepare("SELECT DISTINCT class_type FROM students WHERE class_type IS NOT NULL AND class_type != '' AND user_id = ? ORDER BY class_type");
                    $stmt->execute([$_SESSION['user_id']]);
                    $class_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch(PDOException $e) {
                    // Ignore error
                }
                ?>

                <!-- Filter Form -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleFilters()">
                        <h6 class="mb-0 fw-semibold">
                            <i class="fas fa-filter me-2 text-primary"></i>Filters
                            <?php if ($filters_active): ?>
                                <span class="badge bg-primary ms-2">Active</span>
                            <?php endif; ?>
                        </h6>
                        <i id="filterIcon" class="fas fa-chevron-down"></i>
                    </div>
                    <div id="filterContent" class="card-body" style="overflow: hidden; max-height: 0; transition: max-height 0.3s ease;">
                        <form method="GET" id="filterForm" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="searchInput" class="form-label fw-semibold">Search</label>
                                <input type="text" id="searchInput" name="q" class="form-control" placeholder="Search by name, ID, email or phone" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="courseFilter" class="form-label fw-semibold">Course</label>
                                <select class="form-select" id="courseFilter" name="course">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course); ?>" <?php echo $filter_course === $course ? 'selected' : ''; ?>><?php echo htmlspecialchars($course); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="yearFilter" class="form-label fw-semibold">Admission Year</label>
                                <select class="form-select" id="yearFilter" name="year">
                                    <option value="">All Years</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $filter_year === (string)$year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="classTypeFilter" class="form-label fw-semibold">Class Type</label>
                                <select class="form-select" id="classTypeFilter" name="class_type">
                                    <option value="">All Class Types</option>
                                    <?php foreach ($class_types as $ct): ?>
                                        <option value="<?php echo htmlspecialchars($ct); ?>" <?php echo $filter_class_type === $ct ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="notPaid3m" name="not_paid_3m" <?php echo $filter_not_paid ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notPaid3m">Not paid in last 3 months</label>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                            </div>
                            <div class="col-md-1">
                                <a href="students" class="btn btn-outline-secondary w-100">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                        <div class="card h-100 border-0 shadow-sm" style="border: 2px dashed #dee2e6;">
                            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center py-5">
                                <i class="fas fa-plus fa-3x text-primary mb-3"></i>
                                <h5 class="card-title text-primary">Add New Student</h5>
                                <p class="card-text text-muted">Click to add a new student to the system</p>
                                <a href="add_student" class="btn btn-primary">Add Student</a>
                            </div>
                        </div>
                    </div>
                    <?php
                    try {
                        // Build the base query for counting total records
                        $count_query = "SELECT COUNT(*) as total FROM students WHERE user_id = ? ";
                        $params = [$_SESSION['user_id']];

                        if ($filter_course) {
                            $count_query .= " AND course = ?";
                            $params[] = $filter_course;
                        }

                        if ($filter_year) {
                            $count_query .= " AND YEAR(admission_date) = ?";
                            $params[] = $filter_year;
                        }

                        if ($filter_class_type) {
                            $count_query .= " AND class_type = ?";
                            $params[] = $filter_class_type;
                        }

                        if ($filter_not_paid) {
                            $count_query .= " AND student_id NOT IN (SELECT student_id FROM payments WHERE user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND status = 'completed')";
                            $params[] = $_SESSION['user_id'];
                        }

                        if (!empty($search)) {
                            $count_query .= " AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? )";
                            $like = "%" . $search . "%";
                            $params[] = $like;
                            $params[] = $like;
                            $params[] = $like;
                            $params[] = $like;
                            $params[] = $like;
                        }

                        $stmt = $pdo->prepare($count_query);
                        $stmt->execute($params);
                        $total_students = $stmt->fetch()['total'];
                        $total_pages = ceil($total_students / $per_page);

                        // Adjust page if it exceeds total pages
                        if ($page > $total_pages && $total_pages > 0) {
                            $page = $total_pages;
                            $offset = ($page - 1) * $per_page;
                        }

                        // Build the main query with pagination
                        $query = "SELECT * FROM students WHERE user_id = ? ";
                        $params = [$_SESSION['user_id']];

                        if ($filter_course) {
                            $query .= " AND course = ?";
                            $params[] = $filter_course;
                        }

                        if ($filter_year) {
                            $query .= " AND YEAR(admission_date) = ?";
                            $params[] = $filter_year;
                        }

                        if ($filter_class_type) {
                            $query .= " AND class_type = ?";
                            $params[] = $filter_class_type;
                        }

                        if ($filter_not_paid) {
                            // Exclude students who have any completed payment in the last 3 months
                            $query .= " AND student_id NOT IN (SELECT student_id FROM payments WHERE user_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND status = 'completed')";
                            $params[] = $_SESSION['user_id'];
                        }

                        if (!empty($search)) {
                            $query .= " AND (student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? )";
                            $like = "%" . $search . "%";
                            $params[] = $like;
                            $params[] = $like;
                            $params[] = $like;
                            $params[] = $like;
                            $params[] = $like;
                        }

                        $query .= " ORDER BY admission_date ASC, CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(student_id, '/', 1), 'YC', -1) AS UNSIGNED) ASC";
                        $query .= " LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        $students = $stmt->fetchAll();

                        if (count($students) > 0) {
                            foreach ($students as $student) {
                                ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                            <small class="text-white-50"><?php echo htmlspecialchars($student['student_id']); ?></small>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <i class="fas fa-envelope text-muted me-2"></i>
                                                <small><?php echo htmlspecialchars($student['email']); ?></small>
                                            </div>
                                            <div class="mb-2">
                                                <i class="fas fa-phone text-muted me-2"></i>
                                                <small><?php echo htmlspecialchars($student['phone']); ?></small>
                                            </div>
                                            <div class="mb-2">
                                                <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                <small><?php echo date('M d, Y', strtotime($student['admission_date'])); ?></small>
                                            </div>
                                            <div class="mb-2">
                                                <i class="fas fa-book text-muted me-2"></i>
                                                <small><?php echo htmlspecialchars($student['course']); ?></small>
                                            </div>
                                            <?php if (!empty($student['class_type'])): ?>
                                            <div class="mb-2">
                                                <i class="fas fa-tag text-muted me-2"></i>
                                                <small><?php echo htmlspecialchars($student['class_type']); ?></small>
                                            </div>
                                            <?php endif; ?>
                                            <div class="mb-3">
                                                <i class="fas fa-rupee-sign text-muted me-2"></i>
                                                <strong><?php echo number_format($student['fee_amount'], 2); ?></strong>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <div class="d-flex gap-2">
                                                <a href="payments?student_id=<?php echo $student['student_id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">View Payments</a>
                                                <a href="add_student?search=<?php echo urlencode($student['student_id']); ?>" class="btn btn-outline-info btn-sm" title="Edit student"><i class="fas fa-edit"></i></a>
                                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" data-student-id="<?php echo $student['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="col-12">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-users fa-3x mb-3 text-secondary"></i>
                                    <h5>No students found.</h5>
                                    <p>Try adjusting your filters or add a new student.</p>
                                </div>
                            </div>
                            <?php
                        }
                    } catch(PDOException $e) {
                        $popup_message = 'Error loading students: ' . $e->getMessage();
                        $popup_type = 'danger';
                        ?>
                        <div class="col-12">
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Error loading students: <?php echo htmlspecialchars($e->getMessage()); ?>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        <small>Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $per_page, $total_students); ?></strong> of <strong><?php echo $total_students; ?></strong> students</small>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="students?page=1<?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?><?php echo !empty($filter_course) ? '&course=' . urlencode($filter_course) : ''; ?><?php echo !empty($filter_year) ? '&year=' . urlencode($filter_year) : ''; ?><?php echo !empty($filter_class_type) ? '&class_type=' . urlencode($filter_class_type) : ''; ?><?php echo $filter_not_paid ? '&not_paid_3m=1' : ''; ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="students?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?><?php echo !empty($filter_course) ? '&course=' . urlencode($filter_course) : ''; ?><?php echo !empty($filter_year) ? '&year=' . urlencode($filter_year) : ''; ?><?php echo !empty($filter_class_type) ? '&class_type=' . urlencode($filter_class_type) : ''; ?><?php echo $filter_not_paid ? '&not_paid_3m=1' : ''; ?>">Previous</a>
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
                                    <a class="page-link" href="students?page=<?php echo $i; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?><?php echo !empty($filter_course) ? '&course=' . urlencode($filter_course) : ''; ?><?php echo !empty($filter_year) ? '&year=' . urlencode($filter_year) : ''; ?><?php echo !empty($filter_class_type) ? '&class_type=' . urlencode($filter_class_type) : ''; ?><?php echo $filter_not_paid ? '&not_paid_3m=1' : ''; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php
                            }
                            
                            if ($end_page < $total_pages) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="students?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?><?php echo !empty($filter_course) ? '&course=' . urlencode($filter_course) : ''; ?><?php echo !empty($filter_year) ? '&year=' . urlencode($filter_year) : ''; ?><?php echo !empty($filter_class_type) ? '&class_type=' . urlencode($filter_class_type) : ''; ?><?php echo $filter_not_paid ? '&not_paid_3m=1' : ''; ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="students?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&q=' . urlencode($search) : ''; ?><?php echo !empty($filter_course) ? '&course=' . urlencode($filter_course) : ''; ?><?php echo !empty($filter_year) ? '&year=' . urlencode($filter_year) : ''; ?><?php echo !empty($filter_class_type) ? '&class_type=' . urlencode($filter_class_type) : ''; ?><?php echo $filter_not_paid ? '&not_paid_3m=1' : ''; ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content border-0">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-semibold">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong id="studentName"></strong>?</p>
                        <p class="text-danger small">This cannot be undone; all their payments will be removed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" class="m-0 p-0">
                            <input type="hidden" name="delete_student_id" id="deleteStudentId">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

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
        // Handle delete modal
        const deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const studentId = button.getAttribute('data-student-id');
            const studentName = button.getAttribute('data-student-name');

            const studentNameElement = deleteModal.querySelector('#studentName');
            const deleteStudentIdInput = deleteModal.querySelector('#deleteStudentId');

            studentNameElement.textContent = studentName;
            deleteStudentIdInput.value = studentId;
        });

        // Filter toggle functionality
        let filtersExpanded = false;

        function toggleFilters() {
            const filterContent = document.getElementById('filterContent');
            const filterIcon = document.getElementById('filterIcon');

            if (filtersExpanded) {
                // Collapse
                filterContent.style.maxHeight = '0';
                filterIcon.classList.remove('fa-chevron-up');
                filterIcon.classList.add('fa-chevron-down');
                filtersExpanded = false;
            } else {
                // Expand
                filterContent.style.maxHeight = filterContent.scrollHeight + 'px';
                filterIcon.classList.remove('fa-chevron-down');
                filterIcon.classList.add('fa-chevron-up');
                filtersExpanded = true;
            }
        }

        // Auto-expand filters if any filters are active
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('q') || urlParams.has('course') || urlParams.has('year') || urlParams.has('not_paid_3m') || urlParams.has('class_type');

            if (hasFilters) {
                // Small delay to ensure content is rendered
                setTimeout(() => {
                    toggleFilters();
                }, 100);
            }

            // Submit form on Enter key in search input
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('filterForm').submit();
                    }
                });
            }
        });
    </script>
    <script>
        // Export modal handling
        (function(){
            var downloadBtn = document.getElementById('downloadDataBtn');
            var exportModalEl = document.getElementById('exportModal');
            var confirmBtn = document.getElementById('confirmExportBtn');
            if (downloadBtn && exportModalEl) {
                var exportModal = new bootstrap.Modal(exportModalEl);
                downloadBtn.addEventListener('click', function(){ exportModal.show(); });
                confirmBtn.addEventListener('click', function(){
                    var form = document.getElementById('exportForm');
                    var type = form.querySelector('input[name="export_type"]:checked').value;
                    // Trigger download by navigating to endpoint
                    window.location = 'php/export.php?type=' + encodeURIComponent(type);
                    exportModal.hide();
                });
            }
        })();
    </script>
</body>
</html>