<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Add Student - Student Payment Management System</title>
    <link rel="icon" href="img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login');
        exit;
    }
    $active_page = 'add_student';
    include 'navbar.php';
    include 'php/db.php';
    include 'email_template.php';
    include 'email_send_student.php';
    
    $popup_message = '';
    $popup_type = 'info';
    $formData = [];
    $todayDate = date('Y-m-d');
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $class_type = trim($_POST['class_type'] ?? '');
        $admission_fees = trim($_POST['admission_fees'] ?? '');
        $admission_date = $_POST['admission_date'] ?? '';
        $fee_amount = floatval($_POST['fee_amount'] ?? 0);
        $student_id = trim($_POST['student_id'] ?? '');
        $old_student_id = trim($_POST['old_student_id'] ?? '');
        $is_update = !empty($old_student_id);
        
        $formData = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'course' => $course,
            'class_type' => $class_type,
            'admission_fees' => $admission_fees,
            'admission_date' => $admission_date,
            'fee_amount' => $fee_amount,
            'student_id' => $student_id
        ];
        
        $errors = [];
        if (empty($first_name)) $errors[] = 'First name is required.';
        if (empty($last_name)) $errors[] = 'Last name is required.';
        if (empty($admission_date)) {
            $errors[] = 'Admission date is required.';
        } else {
            $admissionTime = strtotime($admission_date);
            if ($admissionTime === false || $admissionTime > time()) {
                $errors[] = 'Admission date must be valid and not in the future.';
            }
        }
        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        }
        if (empty($phone)) $errors[] = 'Phone number is required.';
        if (empty($course)) $errors[] = 'Course is required.';
        if ($admission_fees !== '' && !is_numeric($admission_fees)) $errors[] = 'Admission fees must be a valid number.';
        if ($fee_amount < 0) $errors[] = 'Fee amount cannot be negative.';
        if (empty($student_id)) {
            $errors[] = 'Student ID is required.';
        } else {
            if (!$is_update || ($is_update && $student_id !== $old_student_id)) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ? AND user_id = ?");
                    $stmt->execute([$student_id, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        $errors[] = 'Student ID already exists.';
                    }
                } catch(PDOException $e) {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
        
        if (empty($errors)) {
            try {
                if ($is_update) {
                    if ($student_id !== $old_student_id) {
                        $stmt = $pdo->prepare("UPDATE students SET student_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?, course = ?, class_type = ?, admission_date = ?, fee_amount = ? WHERE student_id = ? AND user_id = ?");
                        $stmt->execute([$student_id, $first_name, $last_name, $email, $phone, $course, $class_type, $admission_date, $fee_amount, $old_student_id, $_SESSION['user_id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE students SET first_name = ?, last_name = ?, email = ?, phone = ?, course = ?, class_type = ?, admission_date = ?, fee_amount = ? WHERE student_id = ? AND user_id = ?");
                        $stmt->execute([$first_name, $last_name, $email, $phone, $course, $class_type, $admission_date, $fee_amount, $student_id, $_SESSION['user_id']]);
                    }
                    $popup_message = 'Student updated successfully!';
                    $popup_type = 'success';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, phone, course, class_type, admission_date, fee_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $student_id, $first_name, $last_name, $email, $phone, $course, $class_type, $admission_date, $fee_amount]);
                    $popup_message = 'Student added successfully!';
                    $popup_type = 'success';
                }
                
                // Send email to student
                $student_data = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'student_id' => $student_id,
                    'course' => $course,
                    'class_type' => $class_type,
                    'admission_fees' => $admission_fees,
                    'admission_date' => $admission_date,
                    'fee_amount' => $fee_amount,
                    'email' => $email,
                    'is_update' => $is_update
                ];
                $emailBody = generate_student_added_email($student_data);
                $emailSubject = ($is_update ? 'Student Record Updated - ' : 'Student Record Created - ') . $student_id;
                $email_result = smtp_mailer($email, $emailSubject, $emailBody, $_SESSION['user_id']);
                
                // Check email result (returns array with success and message)
                if (!is_array($email_result) || !$email_result['success']) {
                    $popup_message .= ' (Note: Email notification could not be sent)';
                }
                
                $formData = [];
                header("Refresh: 2; url=students");
            } catch(PDOException $e) {
                $popup_message = 'Error saving student: ' . $e->getMessage();
                $popup_type = 'danger';
            }
        } else {
            $popup_message = implode(' ', $errors);
            $popup_type = 'danger';
        }
    }
    
    ?>
    
    <div class="container mt-4 main-content">
        <div class="row mb-4">
            <div class="col"><h2 class="h4 fw-bold mb-0"><i class="fas fa-user-plus me-2 text-primary"></i><span id="pageTitle">Add New Student</span></h2></div>
            <div class="col-auto"><a href="students" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a></div>
        </div>

        <div class="card border-0 shadow-sm mb-4" id="importCard">
            <div class="card-body p-4">
                <h5 class="card-title mb-3"><i class="fas fa-file-import me-2 text-primary"></i>Import from Application</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" id="form_code" placeholder="Enter form code or scan QR">
                            <button type="button" class="btn btn-outline-secondary" id="fetchFormBtn" title="Fetch"><i class="fas fa-download me-2"></i>Fetch</button>
                        </div>
                        <div id="formCodeStatus" class="mt-2"></div>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#qrScannerModal" title="Scan QR">
                            <i class="fas fa-qrcode me-2"></i>Scan QR
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($popup_message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($popup_type); ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $popup_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($popup_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" id="addStudentForm">
                    <input type="hidden" id="old_student_id" name="old_student_id" value="">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="student_id" class="form-label fw-semibold">Student ID <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Auto-generate or search" value="<?php echo htmlspecialchars($formData['student_id'] ?? ''); ?>" required>
                                <button type="button" class="btn btn-outline-secondary" id="searchBtn"><i class="fas fa-search"></i></button>
                            </div>
                            <div class="invalid-feedback" id="studentIDFeedback"></div>
                            <small class="form-text">Auto-generated based on configured format. Use keywords: {YYYY}, {YY}, {MM}, {DD}, {SEQ}, {FIRST}, {LAST}, {F}, {L}, {PREFIX}</small>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="useFillGap" title="Use gaps in sequence">
                                <label class="form-check-label small" for="useFillGap">Use deleted ID sequence</label>
                            </div>
                            <div id="searchStatus" class="mt-2"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="First name" value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Last name" value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="admission_date" class="form-label fw-semibold">Admission Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="admission_date" name="admission_date" value="<?php echo htmlspecialchars($formData['admission_date'] ?? $todayDate); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone" value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="course" class="form-label fw-semibold">Course Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="course" name="course" placeholder="Course name" value="<?php echo htmlspecialchars($formData['course'] ?? ''); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="class_type" class="form-label fw-semibold">Class Type</label>
                            <input type="text" class="form-control" id="class_type" name="class_type" placeholder="Class type" value="<?php echo htmlspecialchars($formData['class_type'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6" id="admissionFeesCol">
                            <label for="admission_fees" class="form-label fw-semibold">Admission Fees</label>
                            <input type="number" class="form-control" id="admission_fees" name="admission_fees" step="0.01" min="0" value="<?php echo htmlspecialchars($formData['admission_fees'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="fee_amount" class="form-label fw-semibold">Fee Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="fee_amount" name="fee_amount" step="0.01" min="0" value="<?php echo htmlspecialchars($formData['fee_amount'] ?? '0'); ?>" required>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><span id="submitBtnText">Add</span></button>
                            <a href="students" class="btn btn-outline-secondary"><i class="fas fa-times me-2"></i>Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qrScannerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-qrcode me-2"></i>Scan QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
                    <div id="qr-result" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="stopScanBtn" class="btn btn-secondary">Stop</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        let isEditMode = false, qrScanner = null, searchTimeout = null, formCodeTimeout = null, idConfig = null, idGenerationTimeout = null;

        const qrModal = document.getElementById('qrScannerModal');
        qrModal.addEventListener('show.bs.modal', startQRScanning);
        qrModal.addEventListener('hide.bs.modal', stopQRScanning);
        document.getElementById('stopScanBtn').addEventListener('click', stopQRScanning);

        // Load user's ID configuration on page load
        function setStudentIDError(message) {
            const studentIdField = document.getElementById('student_id');
            const feedback = document.getElementById('studentIDFeedback');
            if (studentIdField) studentIdField.classList.add('is-invalid');
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }

        function clearStudentIDError() {
            const studentIdField = document.getElementById('student_id');
            const feedback = document.getElementById('studentIDFeedback');
            if (studentIdField) studentIdField.classList.remove('is-invalid');
            if (feedback) {
                feedback.textContent = '';
                feedback.style.display = 'none';
            }
        }

        function loadIDConfig() {
            fetch('php/get_application.php?action=get_user_id_config')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        idConfig = data.data;
                        // Update student ID field label if required
                        const label = document.querySelector('label[for="student_id"]');
                        if (label && idConfig.student_id_required === 1) {
                            if (!label.innerHTML.includes('*')) {
                                label.innerHTML += ' <span class="text-danger">*</span>';
                            }
                        }
                        if (!idConfig.student_id_format) {
                            setStudentIDError('Student ID format is not configured. Please set it in Profile.');
                        } else {
                            clearStudentIDError();
                            generateStudentID();
                        }
                    }
                })
                .catch(e => console.log('Error loading ID config:', e));
        }

        function startQRScanning() {
            const qrDiv = document.getElementById('qr-result');
            qrDiv.innerHTML = '';
            if (!qrScanner) {
                qrScanner = new Html5Qrcode("qr-reader");
                qrScanner.start({facingMode: "environment"}, {fps: 10, qrbox: {width: 250, height: 250}}, onQRCodeScanned, onQRCodeError)
                    .catch(err => { qrDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Camera error: ' + err + '</div>'; });
            }
        }

        function stopQRScanning() {
            if (qrScanner && qrScanner.isScanning) {
                qrScanner.stop().catch(err => console.log('Error:', err));
            }
        }

        function onQRCodeScanned(text) {
            try {
                let code = null;
                try {
                    const url = new URL(text);
                    code = url.searchParams.get('form_code');
                } catch {
                    code = text.includes('form_code=') ? text.split('form_code=')[1] : text;
                }
                code = code ? code.trim() : null;
                if (code) {
                    document.getElementById('form_code').value = code;
                    document.getElementById('qr-result').innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> QR Code: <strong>' + code + '</strong></div>';
                    stopQRScanning();
                    setTimeout(() => bootstrap.Modal.getInstance(qrModal).hide(), 1500);
                } else {
                    document.getElementById('qr-result').innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Could not extract form code.</div>';
                }
            } catch(e) {
                document.getElementById('qr-result').innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Invalid format.</div>';
            }
        }

        function onQRCodeError(e) { console.log('QR error:', e); }

        function fetchFormData() {
            const code = document.getElementById('form_code').value.trim();
            if (!code) return;
            const div = document.getElementById('formCodeStatus');
            div.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin"></i> Fetching...</div>';
            fetch('php/get_application.php?form_code=' + encodeURIComponent(code))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const d = data.data;
                        if (d.first_name) document.getElementById('first_name').value = d.first_name;
                        if (d.last_name) document.getElementById('last_name').value = d.last_name;
                        if (d.email) document.getElementById('email').value = d.email;
                        if (d.phone) document.getElementById('phone').value = d.phone;
                        if (d.course) document.getElementById('course').value = d.course;
                        document.getElementById('class_type').value = d.class_type || '';
                        document.getElementById('admission_fees').value = d.admission_fees || '';
                        document.getElementById('fee_amount').value = d.fee_amount || '0';
                        generateStudentID();
                        div.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Data loaded!</div>';
                    } else {
                        div.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-circle"></i> Not found.</div>';
                    }
                })
                .catch(e => { div.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle"></i> Error: ' + e.message + '</div>'; });
        }

        document.getElementById('fetchFormBtn').addEventListener('click', fetchFormData);
        document.getElementById('form_code').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); fetchFormData(); }
        });
        document.getElementById('form_code').addEventListener('input', function() {
            clearTimeout(formCodeTimeout);
            if (!this.value.trim()) {
                document.getElementById('formCodeStatus').innerHTML = '';
                return;
            }
            formCodeTimeout = setTimeout(fetchFormData, 600);
        });

        function performSearch() {
            const id = document.getElementById('student_id').value.trim();
            if (!id) return;
            const div = document.getElementById('searchStatus');
            div.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
            fetch('php/get_application.php?action=search_student&student_id=' + encodeURIComponent(id))
                .then(r => {
                    if (!r.ok) throw new Error('Server error');
                    return r.json();
                })
                .then(data => {
                    if (data && data.success && data.data) {
                        const d = data.data;
                        document.getElementById('first_name').value = d.first_name || '';
                        document.getElementById('last_name').value = d.last_name || '';
                        document.getElementById('email').value = d.email || '';
                        document.getElementById('phone').value = d.phone || '';
                        document.getElementById('course').value = d.course || '';
                        document.getElementById('class_type').value = d.class_type || '';
                        document.getElementById('admission_fees').value = d.admission_fees || '';
                        document.getElementById('admission_date').value = d.admission_date || '';
                        document.getElementById('fee_amount').value = d.fee_amount || '0';
                        document.getElementById('old_student_id').value = id;
                        isEditMode = true;
                        document.getElementById('submitBtnText').textContent = 'Update';
                        document.getElementById('importCard').style.display = 'none';
                        document.getElementById('admissionFeesCol').style.display = 'none';
                        div.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Found! Ready to update.</div>';
                    } else {
                        isEditMode = false;
                        document.getElementById('old_student_id').value = '';
                        document.getElementById('submitBtnText').textContent = 'Add';
                        document.getElementById('importCard').style.display = 'block';
                        document.getElementById('admissionFeesCol').style.display = 'block';
                        div.innerHTML = '<div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> Not found. Will save as new.</div>';
                    }
                })
                .catch(e => {
                    div.innerHTML = '<div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle"></i> Could not fetch. Continue or retry.</div>';
                    console.log('Error:', e);
                });
        }

        document.getElementById('searchBtn').addEventListener('click', performSearch);
        document.getElementById('student_id').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); performSearch(); }
        });
        document.getElementById('student_id').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            if (!this.value.trim()) {
                document.getElementById('searchStatus').innerHTML = '';
                return;
            }
            searchTimeout = setTimeout(performSearch, 600);
        });

        /**
         * Generate Student ID based on configured format template
         */
        function generateStudentID() {
            if (!idConfig || !idConfig.student_id_format) return;
            
            const fn = document.getElementById('first_name').value.trim();
            const ln = document.getElementById('last_name').value.trim();
            const ad = document.getElementById('admission_date').value;
            const useFillGap = document.getElementById('useFillGap').checked;
            
            if (!fn || !ad || isEditMode) return;
            
            clearTimeout(idGenerationTimeout);
            idGenerationTimeout = setTimeout(() => {
                const fillGaps = useFillGap ? 'true' : 'false';
                fetch(`php/get_application.php?action=generate_id&first_name=${encodeURIComponent(fn)}&last_name=${encodeURIComponent(ln)}&admission_date=${encodeURIComponent(ad)}&fill_gaps=${fillGaps}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.data && data.data.student_id) {
                            document.getElementById('student_id').value = data.data.student_id;
                            document.getElementById('searchStatus').innerHTML = '';
                        } else {
                            console.log('Error generating ID:', data.message);
                        }
                    })
                    .catch(e => console.log('Error generating student ID:', e));
            }, 300);
        }

        document.getElementById('useFillGap').addEventListener('change', generateStudentID);
        document.getElementById('first_name').addEventListener('change', generateStudentID);
        document.getElementById('first_name').addEventListener('blur', generateStudentID);
        document.getElementById('last_name').addEventListener('change', generateStudentID);
        document.getElementById('last_name').addEventListener('blur', generateStudentID);
        document.getElementById('admission_date').addEventListener('change', generateStudentID);
        document.getElementById('admission_date').addEventListener('blur', generateStudentID);

        window.addEventListener('DOMContentLoaded', loadIDConfig);

        // Auto-search if search parameter provided in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchId = urlParams.get('search');
            if (searchId) {
                document.getElementById('student_id').value = searchId;
                // Update page title
                const pageTitle = document.getElementById('pageTitle');
                if (pageTitle) pageTitle.textContent = 'Edit Student';
                document.getElementById('importCard').style.display = 'none';
                document.getElementById('admissionFeesCol').style.display = 'none';
                setTimeout(performSearch, 500);
            }
        });

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('admission_date').max = tomorrow.toISOString().split('T')[0];
    </script>
    <script src="js/validation.js"></script>
</body>
</html>
