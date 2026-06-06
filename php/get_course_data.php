<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$term = isset($_GET['term']) ? trim($_GET['term']) : '';

try {
    if (!empty($term)) {
        // Search multiple courses by term
        $stmt = $pdo->prepare("SELECT id, course_name, course_fees FROM courses WHERE course_name LIKE ? ORDER BY course_name LIMIT 15");
        $stmt->execute(["%$term%"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
    } elseif ($id > 0) {
        // Search single course by ID
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Not found']);
        }
    } elseif (!empty($name)) {
        // Search single course by name
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid id, name or term']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
