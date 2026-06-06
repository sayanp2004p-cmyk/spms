<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
require_once __DIR__ . '/db.php';

function output_csv($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM for Excel compatibility
    echo "\xEF\xBB\xBF";
    if (!empty($headers)) fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function fetch_students($pdo) {
    $stmt = $pdo->prepare('SELECT student_id, first_name, last_name, email, phone, admission_date, course, fee_amount, created_at FROM students WHERE user_id = ? ORDER BY created_at ASC');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_payments($pdo) {
    $stmt = $pdo->prepare('SELECT id, student_id, amount, payment_date, payment_method, status, description, due_amount, created_at FROM payments WHERE user_id = ? ORDER BY created_at ASC');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$type = isset($_GET['type']) ? $_GET['type'] : 'students';
$now = date('Ymd_His');

if ($type === 'students') {
    $rows = fetch_students($pdo);
    $headers = ['Student ID','First Name','Last Name','Email','Phone','Admission Date','Course','Fee Amount','Created At'];
    $outRows = [];
    foreach ($rows as $r) {
        $outRows[] = [$r['student_id'], $r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['admission_date'], $r['course'], $r['fee_amount'], $r['created_at']];
    }
    output_csv("students_{$now}.csv", $headers, $outRows);

} elseif ($type === 'payments') {
    $rows = fetch_payments($pdo);
    $headers = ['ID','Student ID','Amount','Payment Date','Payment Method','Status','Description','Due Amount','Created At'];
    $outRows = [];
    foreach ($rows as $r) {
        $outRows[] = [$r['id'], $r['student_id'], $r['amount'], $r['payment_date'], $r['payment_method'], $r['status'], $r['description'], $r['due_amount'], $r['created_at']];
    }
    output_csv("payments_{$now}.csv", $headers, $outRows);

} elseif ($type === 'all') {
    // Create temporary CSV files and zip them
    $tmpDir = sys_get_temp_dir();
    $files = [];
    // students
    $students = fetch_students($pdo);
    $stuFile = tempnam($tmpDir, 'exp_');
    $fh = fopen($stuFile, 'w');
    if ($fh) {
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['Student ID','First Name','Last Name','Email','Phone','Admission Date','Course','Fee Amount','Created At']);
        foreach ($students as $r) fputcsv($fh, [$r['student_id'],$r['first_name'],$r['last_name'],$r['email'],$r['phone'],$r['admission_date'],$r['course'],$r['fee_amount'],$r['created_at']]);
        fclose($fh);
        $files["students_{$now}.csv"] = $stuFile;
    }
    // payments
    $payments = fetch_payments($pdo);
    $payFile = tempnam($tmpDir, 'exp_');
    $fh = fopen($payFile, 'w');
    if ($fh) {
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['ID','Student ID','Amount','Payment Date','Payment Method','Status','Description','Due Amount','Created At']);
        foreach ($payments as $r) fputcsv($fh, [$r['id'],$r['student_id'],$r['amount'],$r['payment_date'],$r['payment_method'],$r['status'],$r['description'],$r['due_amount'],$r['created_at']]);
        fclose($fh);
        $files["payments_{$now}.csv"] = $payFile;
    }
    // application
    // no application export (removed)

    // Create zip if ZipArchive available; otherwise fallback to a combined CSV
    if (class_exists('ZipArchive')) {
        $zipName = "export_all_{$now}.zip";
        $zipPath = tempnam($tmpDir, 'zip_');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $name => $path) {
                $zip->addFile($path, $name);
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
        }
        // cleanup
        foreach ($files as $p) { if (file_exists($p)) @unlink($p); }
        if (file_exists($zipPath)) @unlink($zipPath);
        exit;
    } else {
        // Fallback: stream a single CSV containing all sections (students, payments, application)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export_all_{$now}.csv"');
        $out = fopen('php://output', 'w');
        echo "\xEF\xBB\xBF"; // BOM

        // Students section
        fputcsv($out, ["--- STUDENTS ---"]);
        fputcsv($out, ['Student ID','First Name','Last Name','Email','Phone','Admission Date','Course','Fee Amount','Created At']);
        foreach ($students as $r) fputcsv($out, [$r['student_id'],$r['first_name'],$r['last_name'],$r['email'],$r['phone'],$r['admission_date'],$r['course'],$r['fee_amount'],$r['created_at']]);
        fputcsv($out, []);

        // Payments section
        fputcsv($out, ["--- PAYMENTS ---"]);
        fputcsv($out, ['ID','Student ID','Amount','Payment Date','Payment Method','Status','Description','Due Amount','Created At']);
        foreach ($payments as $r) fputcsv($out, [$r['id'],$r['student_id'],$r['amount'],$r['payment_date'],$r['payment_method'],$r['status'],$r['description'],$r['due_amount'],$r['created_at']]);
        fputcsv($out, []);

        // (application export removed)

        fclose($out);
        // cleanup temp files created earlier
        foreach ($files as $p) { if (file_exists($p)) @unlink($p); }
        exit;
    }

} else {
    http_response_code(400);
    echo 'Invalid export type';
    exit;
}

?>