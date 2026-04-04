<?php
session_start();
require_once 'db.php';

// Access Control
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit();
}

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $user_id = $_SESSION['user_id'];
    $level_id = (int)$data['level_id'];
    $score = (int)$data['score'];
    $total_questions = (int)$data['total_questions'];
    $time_taken = (int)$data['time_taken'];

    try {
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, level_id, score, total_questions, time_taken_seconds) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $level_id, $score, $total_questions, $time_taken]);
        
        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
}
?>