<?php
session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

try {
    // get image path
    $stmt = $pdo->prepare('SELECT course_img FROM courses WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $img = $row ? $row['course_img'] : '';

    // delete row
    $del = $pdo->prepare('DELETE FROM courses WHERE id = ?');
    $del->execute([$id]);

    // remove file if exists and is inside img/ path
    if (!empty($img)) {
        $file = realpath(__DIR__ . '/../' . $img);
        $base = realpath(__DIR__ . '/../');
        if ($file && strpos($file, $base) === 0 && file_exists($file)) {
            @unlink($file);
        }
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
