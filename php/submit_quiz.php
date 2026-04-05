<?php
/**
 * Optimized Submit Quiz Handler
 * Stores the entire user choices payload as a single JSON string
 */
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $quiz_id = (int)$data['quiz_id'];
    $score = (int)$data['score'];
    $total = (int)$data['total_questions'];
    $time = (int)$data['time_taken'];
    // Encode the entire array into a lightweight JSON string
    $answers_json = json_encode($data['answers']); 

    try {
        $stmtLvl = $pdo->prepare("SELECT level_id FROM quizzes WHERE quiz_id = ?");
        $stmtLvl->execute([$quiz_id]);
        $level_id = $stmtLvl->fetchColumn();

        if (!$level_id) throw new Exception("Invalid Quiz ID");

        // Single insert replaces the transaction loop entirely!
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, level_id, score, total_questions, time_taken_seconds, answers_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $quiz_id, $level_id, $score, $total, $time, $answers_json]);
        $attempt_id = $pdo->lastInsertId();

        echo json_encode(['status' => 'success', 'attempt_id' => $attempt_id]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid session or payload']);
}
?>