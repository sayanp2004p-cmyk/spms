<?php
require_once __DIR__ . '/php/db.php';

// Require login to access this page
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['user_id'])) {
    // If request is AJAX/POST expecting JSON, return 401; otherwise redirect to login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_code'])) {
        http_response_code(401);
        echo 'Login required';
        exit;
    }
    header('Location: login.php');
    exit;
}

// Accept form_code via POST (AJAX) or legacy GET with form_code.
// Direct links with form code will work.
$form_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['form_code'])) {
    $form_code = trim($_POST['form_code']);
} elseif (!empty($_GET['form_code'])) {
    // direct link with form code
    $form_code = trim($_GET['form_code']);
}

if ($form_code !== '') {
    $stmt = $pdo->prepare('SELECT * FROM application WHERE form_code = :fc LIMIT 1');
    $stmt->execute([':fc' => $form_code]);
    $row = $stmt->fetch();
    if (!$row) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(404);
            echo 'Form not found';
            exit;
        }
        http_response_code(404);
        echo 'Form not found';
        exit;
    }
    // generate or update share token if not already set
        // share links removed: no token generation or storage
} else {
    $row = null;
}

// calculate age in years from dob (year only)
$age = '';
if ($row !== null && !empty($row['dob'])) {
    try {
        $dob_dt = new DateTime($row['dob']);
        $now_dt = new DateTime();
        $age = $now_dt->diff($dob_dt)->y;
    } catch (Exception $e) {
        $age = '';
    }
}

// Parse course and fee data from comma-separated values
$course_name = '';
$class_type = '';
$admission_fees = '';
$monthly_fees = '';
if ($row !== null) {
    // Parse class_type: stored as 'coursename,classtype'
    if (!empty($row['class_type'])) {
        $courseParts = explode(',', $row['class_type'], 2);
        $course_name = htmlspecialchars(trim($courseParts[0]));
        $class_type = isset($courseParts[1]) ? htmlspecialchars(trim($courseParts[1])) : '';
    }
    // Parse admission_fees: stored as 'admissionfees,monthlyfees'
    if (!empty($row['admission_fees'])) {
        $feesParts = explode(',', $row['admission_fees'], 2);
        $admission_fees = htmlspecialchars(trim($feesParts[0]));
        $monthly_fees = isset($feesParts[1]) ? htmlspecialchars(trim($feesParts[1])) : '';
    }
}

// If no form code provided, show a modal prompting for it (avoid exposing code in URL).
if ($row === null) {
    ?><!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="description" content="Student Payment Management System">
        <meta name="robots" content="index, follow">
        <title>Enter Form Code</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="p-3">
        <div class="container" style="max-width:600px;margin-top:40px">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Enter Form Code</h5>
                    <p class="card-text">Please enter the application form code. The code will not be shown in the URL.</p>
                    <div class="mb-3">
                        <input id="codeInput" class="form-control" placeholder="Form code">
                    </div>
                    <button id="openBtn" class="btn btn-primary">Open Form</button>
                    <div id="err" class="text-danger mt-2" style="display:none"></div>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('openBtn').addEventListener('click', function(){
                var code = document.getElementById('codeInput').value.trim();
                if (!code) { document.getElementById('err').textContent = 'Please enter a code.'; document.getElementById('err').style.display='block'; return; }
                document.getElementById('err').style.display='none';
                // POST to same URL to get full page (server will return the full preview page)
                var form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                var input = document.createElement('input');
                input.name = 'form_code';
                input.value = code;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            });
            document.getElementById('codeInput').addEventListener('keypress', function(e){ if (e.key === 'Enter') { e.preventDefault(); document.getElementById('openBtn').click(); } });
        </script>
    </body>
    </html><?php
    exit;
}

// Render preview page with download button. PDF generation will occur client-side when user clicks the button.
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Student Payment Management System">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/png" href="https://yogaculture.kesug.com/https://yogaculture.kesug.com/img/logo.png">
        <link rel="apple-touch-icon" href="https://yogaculture.kesug.com/https://yogaculture.kesug.com/img/logo.png">
    <title>Application <?php echo htmlspecialchars($form_code); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- Theme Script -->
        <script src="js/theme.js"></script>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#f8f9fa}
        .container-card{max-width:950px;margin:18px auto;padding:12px;background:#fff;border-radius:6px;border:1px solid #e7e7e7}
        .header{display:flex;align-items:center;gap:12px}
        .logo{height:56px}
        #formContent{background:white;padding:14px;border:1px solid #ddd}

        /* Form-paper styling similar to printed form */
        .form-paper{border:6px solid #0b5ea8;padding:12px}
        .form-title{font-size:34px;text-align:center;font-weight:700;color:#0b3b6f;letter-spacing:2px}
        .form-tag{display:inline-block;border:1px solid #0b3b6f;padding:4px 8px;margin-top:6px;font-size:12px;color:#0b3b6f}
        .banner{background:#0b3b6f;color:#fff;padding:6px 10px;margin-top:8px;text-align:center;font-weight:600}

        .form-body{margin-top:12px}
          /* Print sizing: fit content to custom paper size (width x height mm).
              Height will be adjusted in JS by adding extra bottom space for PDF output. */
          @page { size: 218mm 300mm; margin: 6mm; }
        #formContent { box-sizing:border-box; }

        /* Print helpers: make the form occupy printable area and avoid extra outer spacing */
        @media print {
            html, body { width: 218mm; height: 300mm; margin: 0; padding: 0; }
            .container-card { margin: 0; padding: 0; border: none; }
            #formContent { width: 206mm; margin: 0 auto; }
        }

        /* Print-fit class reduces paddings and borders for single-page output without shrinking fonts excessively */
        .print-fit .form-paper{border-width:3px;padding:6px}
        .print-fit .form-header{margin-bottom:8px;gap:8px}
        .print-fit .form-qr-container{width:50px;height:50px;padding:2px}
        .print-fit .form-logo-container{width:45px;height:45px}
        .print-fit .banner{padding:4px 6px;font-size:13px}
        .print-fit .form-title{font-size:28px}
        .print-fit .row{gap:8px;margin-bottom:6px}
        .print-fit .field-label{font-size:13px}
        .print-fit .field-line{padding:4px 2px;min-height:18px}
        /* photo and signature removed from print layout */
        .print-fit .photo-box{width:35mm;height:45mm;padding:2px}
        .print-fit .for-office{font-size:14px}
        .form-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px}
        .form-qr-container{flex:0 0 auto;width:70px;height:70px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #ddd;padding:4px;border-radius:4px}
        .form-qr-container canvas{width:100%;height:100%;}
        .form-header-content{flex:1}
        .form-logo-container{flex:0 0 auto;width:60px;height:60px;display:flex;align-items:center;justify-content:center}
        .form-logo-container img{width:100%;height:100%;object-fit:contain}
        .row{display:flex;gap:12px;margin-bottom:8px}
        .col{flex:1}
        .col-3{flex:0 0 180px}

        .field-label{font-size:13px;color:#222;margin-bottom:6px}
        .field-line{border-bottom:1px dashed #333;padding:6px 4px;min-height:20px}
        .small-line{border-bottom:1px dashed #333;display:inline-block;width:80px}

        /* photo and signature styles (client-side preview) */
            /* passport size photo: 35mm x 45mm */
            .photo-box{width:35mm;height:45mm;border:1px dashed #333;display:flex;align-items:center;justify-content:center;font-size:12px;color:#666;overflow:hidden;box-sizing:border-box;padding:2px;cursor:pointer;background:#fff}
            .photo-box img{width:100%;height:100%;object-fit:cover;display:block}
        .photo-caption{font-size:12px;color:#666}
        .photo-note{font-size:12px;color:#666;margin-top:6px}
        .print-fit .photo-note{display:none}

        .dotted-sep{border-top:2px dashed #999;margin:14px 0}
        .for-office{font-weight:700;margin-top:8px}

        /* smaller print for footer rules */
        .rules{font-size:12px;color:#555}

        /* responsive tweaks */
        @media(max-width:700px){.row{flex-direction:column}.col-3{flex:initial}}
        /* Watermark styling */
        #watermark {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;
            z-index: 1; display: flex; align-items: center; justify-content: center;
            opacity: 0.08; font-size: 120px; font-weight: bold; color: #999;
            transform: rotate(-45deg); white-space: nowrap; overflow: hidden;
        }
        #watermark .watermark-logo { position: absolute; width: 200px; height: 200px; opacity: 0.15; }
        #watermark .watermark-code { position: absolute; font-size: 80px; letter-spacing: 8px; }
        
        /* Watermark in form-content (for PDF) */
        #formContent { position: relative; }
        .watermark-pdf-bg {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;
            opacity: 0.05; z-index: 0; display: flex; align-items: center; justify-content: center;
            transform: rotate(-45deg); overflow: hidden;
        }
        .watermark-pdf-bg img { width: 250px; height: 250px; opacity: 0.2; }
        .watermark-pdf-bg .watermark-pdf-text { font-size: 100px; font-weight: bold; color: #999; letter-spacing: 6px; }
        
        .print-fit #formContent { position: relative; }
        .print-fit .watermark-pdf-bg { opacity: 0.08; }
        
        /* Print-blocking message: hide on screen, shown only in browser print preview */
        #printMessage{display:none}
        @media print {
            /* hide all content and show only the print message */
            body * { visibility: hidden !important; }
            #printMessage { display:flex !important; visibility: visible !important; position:fixed; left:0; top:0; width:100%; height:100%; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; background:#fff; color:#111; font-size:20px; text-align:center; }
        }
    </style>
</head>
<body>
    <div id="watermark">
        <img src="https://yogaculture.kesug.com/img/logo.png" class="watermark-logo" alt="Watermark Logo">
    </div>
    <div class="container-card">
        <div id="printMessage">Printing is disabled from this page. Please use the "Download PDF" button to save the form.</div>
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="header">
                <img src="https://yogaculture.kesug.com/img/logo.png" class="logo" alt="Logo">
                <div>
                    <h4 class="mb-0">YOGA CULTURE</h4>
                    <div class="text-muted">Application Submission</div>
                </div>
            </div>
            <div class="btn-group" role="group" aria-label="actions">
                <button id="backBtn" class="btn btn-secondary" title="Back">&larr; Back</button>
                <button id="downloadBtn" class="btn btn-primary" title="Download PDF">
                    <i class="fa fa-download" aria-hidden="true"></i>
                </button>
                <button id="resetPhotoBtn" class="btn btn-secondary" title="Reset photo">Reset Photo</button>
                <button id="newBtn" class="btn btn-outline-secondary" title="Check another">New Check</button>
            </div>
        </div>

        <div id="formContent">
            <div class="watermark-pdf-bg">
                <img src="https://yogaculture.kesug.com/img/logo.png" alt="Watermark">
                <div class="watermark-pdf-text"><?php echo htmlspecialchars(strtoupper(substr($form_code, 0, 20))); ?></div>
            </div>
            <div class="form-paper">
                <?php
                    // build a QR for this application (points to the application_form view)
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? '';
                    $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/') ?: '';
                    $appLink = $scheme . '://' . $host . $basePath . '/download_form?form_code=' . urlencode($form_code);
                    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=' . urlencode($appLink);
                ?>

                <div class="form-header row" style="align-items:center;gap:12px">
                    <div class="col-3" style="text-align:left">
                        <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="QR" style="width:100px;height:100px;object-fit:contain">
                    </div>
                    <div class="col form-header-content text-center">
                        <div class="form-title">YOGA CULTURE</div>
                        <div class="text-center"><span class="form-tag">Health for Mind And Body</span></div>
                        <div class="banner">THROUGH : EXERCISE, ASANA, PRANAYAM &amp; MEDITATION</div>
                    </div>
                    <div class="col-3 form-logo-container">
                        <img src="https://yogaculture.kesug.com/img/logo.png" alt="Logo">
                    </div>
                </div>

                <div class="form-body">
                    <div class="row">
                        <div class="col">
                            <div class="field-label">Form Code: <strong><?php echo htmlspecialchars($row['form_code']); ?></strong></div>
                        </div>
                        <div class="col-3" style="text-align:right">
                            <div id="photoBox" class="photo-box" title="Click to upload photo">
                                <span class="photo-caption">Click to upload photo</span>
                            </div>
                            <input type="file" id="photoInput" accept="image/*" style="display:none">
                            <div class="photo-note">Note: This image is only for preview and will not be saved to our servers. This text will be removed from the PDF.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Name (in block letter)</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['name']); ?></div>
                        </div>
                        <div class="col">
                            <div class="field-label">Guardian Name (F / M / H)</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['guardian_name']); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Address : Present</div>
                            <div class="field-line"><?php echo nl2br(htmlspecialchars($row['present_address'])); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Permanent</div>
                            <div class="field-line"><?php echo nl2br(htmlspecialchars($row['permanent_address'])); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Mobile</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['mobile']); ?></div>
                        </div>
                        <div class="col">
                            <div class="field-label">Email</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['email']); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Course Information</div>
                            <div style="font-size:12px;color:#666;margin-top:4px;padding:6px;background:#f8f9fa;border-radius:4px;border:1px solid #e9ecef;">
                                <?php if ($course_name || $class_type): ?>
                                    <strong>Course:</strong> <?php echo $course_name ?: 'N/A'; ?><br>
                                    <strong>Class Type:</strong> <?php echo $class_type ?: 'N/A'; ?><br>
                                    <strong>Admission Fees:</strong> <?php echo $admission_fees ?: 'N/A'; ?><br>
                                    <strong>Monthly Fees:</strong> <?php echo $monthly_fees ?: 'N/A'; ?>
                                <?php else: ?>
                                    No course information available
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-3">
                            <div class="field-label">Gender</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['gender']); ?></div>
                        </div>
                        <div class="col-3">
                            <div class="field-label">Date of Birth</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['dob']); ?></div>
                        </div>
                        <div class="col">
                            <div class="field-label">Age</div>
                            <div class="field-line"><?php echo ($age !== '' ? htmlspecialchars($age) : ''); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-3">
                            <div class="field-label">Blood Group</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['blood_group']); ?></div>
                        </div>
                        <div class="col-3">
                            <div class="field-label">Blood Pressure</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['blood_pressure']); ?></div>
                        </div>
                        <div class="col-3">
                            <div class="field-label">Weight</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['weight']); ?></div>
                        </div>
                        <div class="col-3">
                            <div class="field-label">Height</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['height']); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Chronic Disease / Diseases, if any</div>
                            <div class="field-line"><?php echo nl2br(htmlspecialchars($row['chronic_disease'])); ?></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="field-label">Contact No.</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['contact_no']); ?></div>
                        </div>
                        <div class="col">
                            <div class="field-label">Date</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['date_field']); ?></div>
                        </div>
                        <div class="col">
                            <div class="field-label">Place</div>
                            <div class="field-line"><?php echo htmlspecialchars($row['place']); ?></div>
                        </div>
                    </div>

                    <div class="rules">1. Verification and other steps are offline.<br>
                        2. For verification, bring photo copy (xerox) of Aadhaar or birth certificate and this form*.</div>

                    <div class="dotted-sep"></div>

                    <div class="row">
                        <div class="col">
                            <div class="rules">1) Attend class on time. 2) Follow dress code as applicable.</div>
                        </div>
                        <div class="col" style="text-align:right">
                            <div style="margin-bottom:36px">Signature in Full</div>
                            <div class="field-line"></div>
                        </div>
                    </div>

                    <div class="dotted-sep"></div>

                    <div class="for-office">For Office Use Only</div>
                    <div class="row" style="margin-top:8px">
                        <div class="col">
                            <div class="field-label">Registration No.</div>
                            <div class="field-line"></div>
                        </div>
                          <div class="col">
                            <div class="field-label">Date</div>
                            <div class="field-line"></div>
                        </div>
                        <div class="col">
                            <div class="field-label">Signature of the Authority</div>
                            <div class="field-line"></div>
                        </div>
                    </div>
                    <div class="form-footer" style="margin-top:10px;text-align:right;font-size:13px;color:#333">Download Date: <span id="downloadDate">-</span></div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>

    <!-- html2pdf CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const btn = document.getElementById('downloadBtn');
        const backBtn = document.getElementById('backBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function(e){
                e.preventDefault();
                if (document.referrer) {
                    window.location.href = document.referrer;
                } else {
                    history.back();
                }
            });
        }

        btn && btn.addEventListener('click', () => {
            const element = document.getElementById('formContent');

            const marginMM = 6; // matches print CSS @page

            // set download date (dd/mm/yyyy) and apply print-fit class to reduce paddings and borders for PDF
            (function setDownloadDate(){
                const el = document.getElementById('downloadDate');
                if (!el) return;
                const d = new Date();
                const dd = String(d.getDate()).padStart(2,'0');
                const mm = String(d.getMonth()+1).padStart(2,'0');
                const yyyy = d.getFullYear();
                el.textContent = dd + '/' + mm + '/' + yyyy;
            })();

            element.classList.add('print-fit');
            // ensure width/height match print size in millimeters
            const prevWidth = element.style.width;
            const prevHeight = element.style.height;
            element.style.width = '218mm';

            // base PDF page height in mm and adjustable extra bottom space
            const basePageHeightMM = 290; // base height requested
            const extraBottomMM = 10; // increase page height from bottom
            const pageHeightMM = basePageHeightMM + extraBottomMM;
            element.style.height = pageHeightMM + 'mm';

            const devicePR = window.devicePixelRatio || 1;
            // compute target pixel size for 218 x pageHeightMM mm paper
            const pxPerMm = 96 / 25.4;
            const targetWidthPx = 218 * pxPerMm;
            const targetHeightPx = pageHeightMM * pxPerMm;

            // measure element size in CSS pixels
            const rect = element.getBoundingClientRect();
            const elemWidth = rect.width;
            const elemHeight = rect.height;

            // compute scale so rendered canvas height approximates targetHeightPx
            let canvasScale = (targetHeightPx * devicePR) / (elemHeight || 1);
            // clamp scale to reasonable bounds
            canvasScale = Math.max(0.5, Math.min(canvasScale, 4));

            const opt = {
                margin: marginMM / 25.4,
                filename: 'application_<?php echo preg_replace('/[^a-zA-Z0-9_-]/','_', $form_code); ?>.pdf',
                image: { type: 'jpeg', quality: 0.92 },
                html2canvas: { scale: canvasScale, useCORS: true, logging: false, dpi: 96 * canvasScale },
                jsPDF: { unit: 'mm', format: [218, pageHeightMM], orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all'] }
            };

            btn.style.display = 'none';

            // Generate PDF, then remove any extra pages so only the first page remains
            html2pdf().set(opt).from(element).toPdf().get('pdf').then((pdf) => {
                try {
                    const total = pdf.internal.getNumberOfPages();
                    for (let p = total; p > 1; p--) {
                        pdf.deletePage(p);
                    }
                    // save with filename from options
                    pdf.save(opt.filename);
                } catch (e) {
                    console.error('Error trimming PDF pages', e);
                    // fallback: let html2pdf save normally
                    return html2pdf().set(opt).from(element).save();
                } finally {
                    element.classList.remove('print-fit');
                    element.style.width = prevWidth;
                    element.style.height = prevHeight;
                    setTimeout(() => { if (btn && btn.parentNode) btn.parentNode.removeChild(btn); }, 300);
                }
            }).catch((err) => {
                element.classList.remove('print-fit');
                element.style.width = prevWidth;
                element.style.height = prevHeight;
                btn.style.display = '';
                console.error('PDF generation failed', err);
            });
        });

        // Client-side photo upload & preview (not saved to server)
        (function(){
            const photoBox = document.getElementById('photoBox');
            const photoInput = document.getElementById('photoInput');
            if (!photoBox || !photoInput) return;

            photoBox.addEventListener('click', () => photoInput.click());

            photoInput.addEventListener('change', (e) => {
                const file = e.target.files && e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(ev) {
                    photoBox.innerHTML = ''; // clear placeholder
                    const img = document.createElement('img');
                    img.src = ev.target.result;
                    photoBox.appendChild(img);
                    // hide any previous error
                    const photoErrorEl = document.getElementById('photoError');
                    if (photoErrorEl) photoErrorEl.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });
        })();

        // Reset photo preview and clear input
        (function(){
            const resetBtn = document.getElementById('resetPhotoBtn');
            const newBtn = document.getElementById('newBtn');
            const photoBoxEl = document.getElementById('photoBox');
            const photoInputEl = document.getElementById('photoInput');

            if (resetBtn) {
                resetBtn.addEventListener('click', function(){
                    if (photoInputEl) photoInputEl.value = '';
                    if (photoBoxEl) photoBoxEl.innerHTML = '<span class="photo-caption">Click to upload photo</span>';
                });
            }

            if (newBtn) {
                newBtn.addEventListener('click', function(){
                    window.location.href = window.location.pathname;
                });
            }
        })();


    </script>
</body>
</html>
