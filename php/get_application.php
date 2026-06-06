<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

/**
 * Get user's student ID configuration
 */
function get_user_id_config($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT student_id_format, student_id_prefix, student_id_required FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    return $config ?: ['student_id_format' => null, 'student_id_prefix' => null, 'student_id_required' => 0];
}

/**
 * Replace format keywords with actual values
 * @param $fill_gaps boolean - if true, use 3-digit SEQ padding, otherwise 2-digit
 */
function replace_format_keywords($format, $first_name, $last_name, $admission_date, $seq_number, $prefix, $fill_gaps = false) {
    // Parse date components
    $date_parts = explode('-', $admission_date);
    $yyyy = $date_parts[0] ?? '';
    $mm = str_pad($date_parts[1] ?? '', 2, '0', STR_PAD_LEFT);
    $dd = str_pad($date_parts[2] ?? '', 2, '0', STR_PAD_LEFT);
    $yy = substr($yyyy, -2);
    
    // Get name initials
    $first_initial = strtoupper(substr($first_name, 0, 1));
    $last_initial = strtoupper(substr($last_name, 0, 1));
    
    // Format sequential number: 3 digits if gap-fill enabled, otherwise 2 digits
    $seq_padding = $fill_gaps ? 3 : 2;
    $seq_formatted = str_pad($seq_number, $seq_padding, '0', STR_PAD_LEFT);
    
    // Replace keywords in format
    $student_id = $format;
    $student_id = str_replace('{YYYY}', $yyyy, $student_id);
    $student_id = str_replace('{YY}', $yy, $student_id);
    $student_id = str_replace('{MM}', $mm, $student_id);
    $student_id = str_replace('{DD}', $dd, $student_id);
    $student_id = str_replace('{SEQ}', $seq_formatted, $student_id);
    $student_id = str_replace('{FIRST}', ucfirst(strtolower($first_name)), $student_id);
    $student_id = str_replace('{LAST}', ucfirst(strtolower($last_name)), $student_id);
    $student_id = str_replace('{F}', $first_initial, $student_id);
    $student_id = str_replace('{L}', $last_initial, $student_id);
    $student_id = str_replace('{PREFIX}', $prefix ?? '', $student_id);
    
    return $student_id;
}

/**
 * Get next sequential number, with optional gap-fill
 */
function get_next_sequential_number($pdo, $user_id, $format, $first_name, $last_name, $admission_date, $prefix, $fill_gaps = false) {
    // Get all existing student IDs for this user to extract sequence numbers
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? ORDER BY student_id");
    $stmt->execute([$user_id]);
    $existing_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Extract all sequence numbers from existing IDs using the format
    $used_sequences = [];
    foreach ($existing_ids as $id) {
        // Try to extract sequence number by comparing against the format pattern
        $pattern = generate_format_pattern($format);
        if (preg_match($pattern, $id, $matches)) {
            if (isset($matches['seq'])) {
                $used_sequences[] = (int)$matches['seq'];
            }
        }
    }
    
    if (empty($used_sequences)) {
        return 1;
    }
    
    sort($used_sequences);
    
    if ($fill_gaps) {
        // Find first gap
        for ($i = 1; $i <= max($used_sequences) + 1; $i++) {
            if (!in_array($i, $used_sequences)) {
                return $i;
            }
        }
    } else {
        // Return next sequential
        return max($used_sequences) + 1;
    }
    
    return 1;
}

/**
 * Generate regex pattern from format to extract sequence number
 */
function generate_format_pattern($format) {
    // Create a regex pattern by escaping special chars and replacing keywords with capture groups
    $pattern = preg_quote($format, '/');
    $pattern = str_replace('\{SEQ\}', '(?<seq>\d+)', $pattern);
    $pattern = str_replace('\{YYYY\}', '\d{4}', $pattern);
    $pattern = str_replace('\{YY\}', '\d{2}', $pattern);
    $pattern = str_replace('\{MM\}', '\d{2}', $pattern);
    $pattern = str_replace('\{DD\}', '\d{2}', $pattern);
    $pattern = str_replace('\{FIRST\}', '[A-Za-z]+', $pattern);
    $pattern = str_replace('\{LAST\}', '[A-Za-z]+', $pattern);
    $pattern = str_replace('\{F\}', '[A-Z]', $pattern);
    $pattern = str_replace('\{L\}', '[A-Z]', $pattern);
    $pattern = str_replace('\{PREFIX\}', '[A-Z0-9]*', $pattern);
    return '/^' . $pattern . '$/';
}

$action = isset($_GET['action']) ? $_GET['action'] : 'get_application';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($action === 'get_user_id_config') {
        // Return user's ID configuration
        $config = get_user_id_config($pdo, $_SESSION['user_id']);
        echo json_encode(['success' => true, 'data' => $config]);
    }
    elseif ($action === 'generate_id') {
        // Generate a new student ID
        $first_name = isset($_GET['first_name']) ? trim($_GET['first_name']) : '';
        $last_name = isset($_GET['last_name']) ? trim($_GET['last_name']) : '';
        $admission_date = isset($_GET['admission_date']) ? trim($_GET['admission_date']) : '';
        $fill_gaps = isset($_GET['fill_gaps']) ? $_GET['fill_gaps'] === 'true' : false;
        
        if (empty($first_name) || empty($last_name) || empty($admission_date)) {
            echo json_encode(['success' => false, 'message' => 'first_name, last_name, and admission_date required']);
            exit;
        }
        
        $config = get_user_id_config($pdo, $_SESSION['user_id']);
        if (empty($config['student_id_format'])) {
            // If no format configured, return error
            echo json_encode(['success' => false, 'message' => 'No student ID format configured']);
            exit;
        }
        
        $seq_number = get_next_sequential_number($pdo, $_SESSION['user_id'], $config['student_id_format'], 
                                                 $first_name, $last_name, $admission_date, 
                                                 $config['student_id_prefix'], $fill_gaps);
        
        $student_id = replace_format_keywords($config['student_id_format'], $first_name, $last_name, 
                                             $admission_date, $seq_number, $config['student_id_prefix'], $fill_gaps);
        
        echo json_encode(['success' => true, 'data' => ['student_id' => $student_id, 'seq_number' => $seq_number]]);
    }
    elseif ($action === 'get_all_ids') {
        // Get all student IDs for gap-fill feature
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ? ORDER BY student_id");
        $stmt->execute([$_SESSION['user_id']]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'data' => $ids]);
    } 
    elseif ($action === 'search_student') {
        // Search for student by ID
        $student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';
        if (empty($student_id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID required']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT student_id, first_name, last_name, email, phone, course, class_type, admission_date, fee_amount FROM students WHERE student_id = ? AND user_id = ?");
        $stmt->execute([$student_id, $_SESSION['user_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($student) {
            echo json_encode(['success' => true, 'data' => $student]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
        }
    }
    else {
        // Get application by form code (default)
        $form_code = '';
        if ($method === 'GET') {
            $form_code = isset($_GET['form_code']) ? trim($_GET['form_code']) : '';
        } else {
            $form_code = isset($_POST['form_code']) ? trim($_POST['form_code']) : '';
        }

        if (empty($form_code)) {
            echo json_encode(['success' => false, 'message' => 'form_code required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM application WHERE form_code = ? LIMIT 1");
        $stmt->execute([$form_code]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($app) {
            $transformedData = [
                'first_name' => '',
                'last_name' => '',
                'email' => $app['email'] ?? '',
                'phone' => $app['mobile'] ?? '',
                'course' => '',
                'class_type' => '',
                'admission_fees' => '',
                'admission_date' => $app['date_field'] ?? '',
                'fee_amount' => 0
            ];
            
            if (!empty($app['name'])) {
                $nameParts = explode(' ', trim($app['name']), 2);
                $transformedData['first_name'] = $nameParts[0];
                $transformedData['last_name'] = $nameParts[1] ?? '';
            }
            
            if (!empty($app['class_type'])) {
                $courseString = trim($app['class_type']);
                if (strpos($courseString, ',') !== false) {
                    [$courseName, $classType] = array_map('trim', explode(',', $courseString, 2));
                    $transformedData['course'] = $courseName;
                    if ($classType !== '') {
                        $transformedData['class_type'] = $classType;
                    }
                } else {
                    $transformedData['course'] = $courseString;
                }
            }
            
            if (!empty($app['class_type']) && empty($transformedData['class_type'])) {
                $transformedData['class_type'] = trim($app['class_type']);
            }
            
            if (!empty($app['admission_fees'])) {
                $feesString = trim($app['admission_fees']);
                if (strpos($feesString, ',') !== false) {
                    [$admissionFees, $monthlyFees] = array_map('trim', explode(',', $feesString, 2));
                    $transformedData['admission_fees'] = $admissionFees;
                    $transformedData['fee_amount'] = $monthlyFees;
                } else {
                    $transformedData['admission_fees'] = $feesString;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $transformedData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Application not found']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
