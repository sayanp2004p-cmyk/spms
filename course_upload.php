<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}
require_once 'php/db.php';

// Flash message support (used for Post/Redirect/Get to avoid resubmission)
$popup_message = '';
$popup_type = 'info';
if (isset($_SESSION['flash_message'])) {
    $popup_message = $_SESSION['flash_message'];
    $popup_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $course_name = trim($_POST['course_name'] ?? '');
    $course_details = trim($_POST['course_details'] ?? '');
    $class_start_time = $_POST['class_start_time'] ?? '';
    $class_end_time = $_POST['class_end_time'] ?? '';
    $class_weeks = trim($_POST['class_weeks'] ?? '');
    $course_fees = floatval($_POST['course_fees'] ?? 0);
    $class_type = trim($_POST['class_type'] ?? '');
    $monthly_fees = trim($_POST['monthly_fees'] ?? null);
    $has_offer = isset($_POST['has_offer']) ? 1 : 0;
    $offer_percentage = $has_offer ? floatval($_POST['offer_percentage'] ?? 0) : null;
    $offer_expire_date = $_POST['offer_expire_date'] ?: null;
    
    

    // If updating, fetch existing image path first
    $existingImg = '';
    if ($course_id > 0) {
        try {
            $s = $pdo->prepare("SELECT course_img FROM courses WHERE id = ? LIMIT 1");
            $s->execute([$course_id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if ($row) $existingImg = $row['course_img'];
        } catch (PDOException $e) {
            // ignore
        }
    }

    // Handle image upload
    $imgPath = $existingImg ?: '';
    if (!empty($_FILES['course_img']['name'])) {
        $uploadDir = __DIR__ . '/img/courses';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file = $_FILES['course_img'];
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $popup_message = 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp.';
            $popup_type = 'danger';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $popup_message = 'Image upload error. Please try again.';
            $popup_type = 'danger';
        } else {
            if ($existingImg) {
                // overwrite existing file (use same filename)
                $dest = __DIR__ . '/' . $existingImg;
                $d = dirname($dest);
                if (!is_dir($d)) mkdir($d, 0755, true);
                if (file_exists($dest)) @unlink($dest);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imgPath = str_replace('\\', '/', substr($dest, strlen(__DIR__) + 1));
                } else {
                    $popup_message = 'Failed to save uploaded image.';
                    $popup_type = 'danger';
                }
            } else {
                $newName = uniqid('course_', true) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imgPath = 'img/courses/' . $newName;
                } else {
                    $popup_message = 'Failed to save uploaded image.';
                    $popup_type = 'danger';
                }
            }
        }
    }

    if (empty($popup_message)) {
        try {
            if ($course_id > 0) {
                // check if updated_at column exists
                $hasUpdatedAt = false;
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM courses LIKE 'updated_at'")->fetch();
                    if ($col) $hasUpdatedAt = true;
                } catch (Exception $e) { /* ignore */ }

                $sql = "UPDATE courses SET course_img = ?, course_name = ?, course_details = ?, class_start_time = ?, class_end_time = ?, class_weeks = ?, course_fees = ?, class_type = ?, monthly_fees = ?, has_offer = ?, offer_percentage = ?, offer_expire_date = ?";
                if ($hasUpdatedAt) $sql .= ", updated_at = NOW()";
                $sql .= " WHERE id = ?";

                $params = [
                    $imgPath ?: '',
                    $course_name,
                    $course_details,
                    $class_start_time,
                    $class_end_time,
                    $class_weeks,
                    $course_fees,
                    $class_type,
                    $monthly_fees,
                    $has_offer,
                    $offer_percentage,
                    $offer_expire_date,
                    $course_id
                ];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['flash_message'] = 'Course updated successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: course_upload');
                exit;
            } else {
                $stmt = $pdo->prepare("INSERT INTO courses (course_img, course_name, course_details, class_start_time, class_end_time, class_weeks, course_fees, class_type, monthly_fees, has_offer, offer_percentage, offer_expire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $imgPath ?: '',
                    $course_name,
                    $course_details,
                    $class_start_time,
                    $class_end_time,
                    $class_weeks,
                    $course_fees,
                    $class_type,
                    $monthly_fees,
                    $has_offer,
                    $offer_percentage,
                    $offer_expire_date
                ]);
                $_SESSION['flash_message'] = 'Course uploaded successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: course_upload.php');
                exit;
            }
        } catch (PDOException $e) {
            $popup_message = 'Database error: ' . $e->getMessage();
            $popup_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <title>Upload Course - Student Payment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<?php
$active_page = 'courses';
include 'navbar.php';
?>

<div class="container mt-5 main-content">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Course</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($popup_message)): ?>
                        <div class="alert alert-<?php echo $popup_type === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($popup_message); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="courseForm">
                        <input type="hidden" name="course_id" id="course_id" value="">
                        <div class="mb-3">
                            <label class="form-label">Course Image</label>
                            <input type="file" name="course_img" id="course_img" accept="image/*" class="form-control">
                            <div class="mt-2"><img id="imgPreview" src="img/placeholder.png" alt="Preview" style="max-width:180px;max-height:120px;display:none;border:1px solid #ddd;padding:6px;border-radius:6px;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Name *</label>
                            <input type="text" name="course_name" id="course_name" class="form-control" autocomplete="off" required>
                            <div id="courseSuggestions" class="list-group" style="position:relative;z-index:2000;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Details</label>
                            <textarea name="course_details" class="form-control" rows="5"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="class_start_time" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" name="class_end_time" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class Weeks</label>
                                <input type="text" name="class_weeks" class="form-control" placeholder="e.g., Mon, Wed, Fri">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Class Type</label>
                                <input type="text" name="class_type" class="form-control" placeholder="e.g., Offline, Online">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Course Fees</label>
                                <input type="number" step="0.01" name="course_fees" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Monthly Fees</label>
                                <input type="text" name="monthly_fees" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3 d-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="has_offer" name="has_offer" value="1">
                                    <label class="form-check-label" for="has_offer">Has Offer</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Offer Percentage</label>
                                <input type="number" step="0.01" name="offer_percentage" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Offer Expiry Date</label>
                                <input type="date" name="offer_expire_date" class="form-control">
                            </div>
                        </div>

                        <div class="d-flex">
                            <button id="submitBtn" class="btn btn-primary me-2" type="submit">Upload Course</button>
                            <button id="deleteBtn" type="button" class="btn btn-outline-danger" style="display:none;">Delete Course</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const courseName = document.getElementById('course_name');
    const suggestions = document.getElementById('courseSuggestions');
    const submitBtn = document.getElementById('submitBtn');
    const courseIdInput = document.getElementById('course_id');
    const imgPreview = document.getElementById('imgPreview');
    const imgInput = document.getElementById('course_img');

    // Offer-related elements
    const hasOfferCheckbox = document.querySelector('[name="has_offer"]');
    const offerPercentageInput = document.querySelector('[name="offer_percentage"]');
    const offerExpireInput = document.querySelector('[name="offer_expire_date"]');

    function updateOfferVisibility() {
        const show = hasOfferCheckbox && hasOfferCheckbox.checked;
        if (offerPercentageInput) {
            const wrap = offerPercentageInput.closest('.mb-3');
            if (wrap) wrap.style.display = show ? '' : 'none';
            if (!show) offerPercentageInput.value = '';
        }
        if (offerExpireInput) {
            const wrap2 = offerExpireInput.closest('.mb-3');
            if (wrap2) wrap2.style.display = show ? '' : 'none';
            if (!show) offerExpireInput.value = '';
        }
    }
    if (hasOfferCheckbox) hasOfferCheckbox.addEventListener('change', updateOfferVisibility);

    let debounceTimer = null;

    function clearSuggestions(){ suggestions.innerHTML = ''; suggestions.style.display = 'none'; }

    function showConfirmModal(message, title, confirmText, onConfirm){
        title = title || 'Confirm';
        confirmText = confirmText || 'OK';
        const overlay = document.createElement('div');
        overlay.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:4000;';
        const box = document.createElement('div');
        box.style = 'background:#fff;padding:18px;border-radius:8px;max-width:520px;width:92%;box-shadow:0 12px 30px rgba(0,0,0,0.25);font-family:Arial, sans-serif;text-align:left;';
        const h = document.createElement('div'); h.style = 'font-weight:700;margin-bottom:8px;color:#212529'; h.textContent = title;
        const p = document.createElement('div'); p.style = 'margin-bottom:12px;color:#333;white-space:pre-wrap;'; p.textContent = message;
        const actions = document.createElement('div'); actions.style = 'text-align:right;';
        const cancel = document.createElement('button'); cancel.textContent = 'Cancel'; cancel.className = 'btn btn-secondary btn-sm me-2';
        const confirm = document.createElement('button'); confirm.textContent = confirmText; confirm.className = 'btn btn-primary btn-sm';
        cancel.addEventListener('click', function(){ try{ document.body.removeChild(overlay); }catch(e){} });
        confirm.addEventListener('click', function(){ try{ onConfirm(); }catch(e){} try{ document.body.removeChild(overlay); }catch(e){} });
        actions.appendChild(cancel); actions.appendChild(confirm);
        box.appendChild(h); box.appendChild(p); box.appendChild(actions); overlay.appendChild(box); document.body.appendChild(overlay);
    }

    courseName.addEventListener('input', function(e){
        const term = this.value.trim();
        courseIdInput.value = '';
        submitBtn.textContent = 'Upload Course';
        imgPreview.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'none';
        if (debounceTimer) clearTimeout(debounceTimer);
        if (term.length < 2){ clearSuggestions(); return; }
        debounceTimer = setTimeout(() => {
            fetch(`php/get_course_data.php?term=${encodeURIComponent(term)}`)
                .then(r => r.json())
                .then(data => {
                    suggestions.innerHTML = '';
                    if (!data.success || !data.data || data.data.length === 0) { clearSuggestions(); return; }
                    data.data.forEach(item => {
                        const el = document.createElement('button');
                        el.type = 'button';
                        el.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                        el.innerHTML = `<span>${item.course_name}</span><small class="text-muted">${item.course_fees}</small>`;
                        el.addEventListener('click', function(){
                                const msg = 'Course "' + item.course_name + '" already exists.\n\nDo you want to edit this course?';
                                showConfirmModal(msg, 'Edit Course', 'Edit', function(){
                                    // fetch full course
                                    fetch(`php/get_course_data.php?id=${item.id}`)
                                        .then(r => r.json())
                                        .then(d2 => {
                                            if (!d2.success) { alert('Failed to load course'); return; }
                                            const c = d2.data;
                                            courseIdInput.value = c.id;
                                            courseName.value = c.course_name || '';
                                            document.querySelector('[name="course_details"]').value = c.course_details || '';
                                            document.querySelector('[name="class_start_time"]').value = c.class_start_time || '';
                                            document.querySelector('[name="class_end_time"]').value = c.class_end_time || '';
                                            document.querySelector('[name="class_weeks"]').value = c.class_weeks || '';
                                            document.querySelector('[name="course_fees"]').value = c.course_fees || '';
                                            document.querySelector('[name="class_type"]').value = c.class_type || '';
                                            document.querySelector('[name="monthly_fees"]').value = c.monthly_fees || '';
                                            document.querySelector('[name="has_offer"]').checked = parseInt(c.has_offer) ? true : false;
                                                // reflect change visually and clear fields if unchecked
                                                if (typeof updateOfferVisibility === 'function') updateOfferVisibility();
                                            document.querySelector('[name="offer_percentage"]').value = c.offer_percentage || '';
                                            document.querySelector('[name="offer_expire_date"]').value = c.offer_expire_date || '';
                                            // preview image
                                            if (c.course_img) {
                                                imgPreview.src = c.course_img;
                                                imgPreview.style.display = 'inline-block';
                                            } else {
                                                imgPreview.style.display = 'none';
                                            }
                                            submitBtn.textContent = 'Update Course';
                                            if (deleteBtn) deleteBtn.style.display = 'inline-block';
                                            clearSuggestions();
                                        });
                                });
                            });
                        suggestions.appendChild(el);
                    });
                    suggestions.style.display = 'block';
                }).catch(()=> clearSuggestions());
        }, 300);
    });

    // Preview selected image locally
    imgInput.addEventListener('change', function(){
        const f = this.files && this.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        imgPreview.src = url;
        imgPreview.style.display = 'inline-block';
    });

    const deleteBtn = document.getElementById('deleteBtn');
    function showDeleteModal(onConfirm){
        const overlay = document.createElement('div');
        overlay.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:4000;';
        const box = document.createElement('div');
        box.style = 'background:#fff;padding:18px;border-radius:8px;max-width:420px;width:92%;box-shadow:0 12px 30px rgba(0,0,0,0.25);font-family:Arial, sans-serif;text-align:left;';
        const title = document.createElement('div'); title.style = 'font-weight:700;margin-bottom:8px;color:#212529'; title.textContent = 'Delete Course';
        const p = document.createElement('div'); p.style = 'margin-bottom:12px;color:#333;'; p.textContent = 'Are you sure you want to permanently delete this course and its image? This action cannot be undone.';
        const actions = document.createElement('div'); actions.style = 'text-align:right;';
        const cancel = document.createElement('button'); cancel.textContent = 'Cancel'; cancel.className = 'btn btn-secondary btn-sm me-2';
        const confirm = document.createElement('button'); confirm.textContent = 'Delete'; confirm.className = 'btn btn-danger btn-sm';
        cancel.addEventListener('click', function(){ try{ document.body.removeChild(overlay); }catch(e){} });
        confirm.addEventListener('click', function(){ try{ onConfirm(); }catch(e){} try{ document.body.removeChild(overlay); }catch(e){} });
        actions.appendChild(cancel); actions.appendChild(confirm);
        box.appendChild(title); box.appendChild(p); box.appendChild(actions); overlay.appendChild(box); document.body.appendChild(overlay);
    }

    if (deleteBtn) deleteBtn.addEventListener('click', function(){
        const id = courseIdInput.value ? parseInt(courseIdInput.value) : 0;
        if (!id) return;
        showDeleteModal(function(){
            const fd = new FormData(); fd.append('id', id);
            fetch('php/delete_course.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        // simple feedback then reload
                        window.location.href = 'course_upload.php';
                    } else {
                        alert('Delete failed: ' + (res.error||'Unknown'));
                    }
                }).catch(err => { alert('Error: ' + err); });
        });
    });

    // Click outside to hide suggestions
    document.addEventListener('click', function(e){ if (!e.target.closest('#courseSuggestions') && !e.target.closest('#course_name')) clearSuggestions(); });
    // initialize offer fields visibility on page load
    try{ if (typeof updateOfferVisibility === 'function') updateOfferVisibility(); }catch(e){}
});
</script>
