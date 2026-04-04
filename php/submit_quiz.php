<?php
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $quiz_id = (int)$data['quiz_id'];
    $score = (int)$data['score'];
    $total = (int)$data['total_questions'];
    $time = (int)$data['time_taken'];
    
    // We also need the individual answers list from the JS
    $answers = $data['answers']; // Expected format: [{qid: 1, correct: true}, ...]

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, total_questions, time_taken_seconds) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $quiz_id, $score, $total, $time]);
        $attempt_id = $pdo->lastInsertId();

        $stmtAnswer = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, is_correct) VALUES (?, ?, ?)");
        foreach($answers as $ans) {
            $stmtAnswer->execute([$attempt_id, $ans['qid'], $ans['correct'] ? 1 : 0]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'attempt_id' => $attempt_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error']);
    }
}
?>