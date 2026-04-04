<?php
/**
 * Robust Submit Quiz Handler
 * Correctly handles foreign keys and logs specific student choices
 */
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $quiz_id = (int)$data['quiz_id'];
    $score = (int)$data['score'];
    $total = (int)$data['total_questions'];
    $time = (int)$data['time_taken'];
    $answers = $data['answers']; 

    try {
        $pdo->beginTransaction();
        
        // Fetch level_id to satisfy SQL constraint for quiz_attempts
        $stmtLvl = $pdo->prepare("SELECT level_id FROM quizzes WHERE quiz_id = ?");
        $stmtLvl->execute([$quiz_id]);
        $level_id = $stmtLvl->fetchColumn();

        if (!$level_id) throw new Exception("Invalid Quiz ID");

        // Insert into quiz_attempts
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, level_id, score, total_questions, time_taken_seconds) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $quiz_id, $level_id, $score, $total, $time]);
        $attempt_id = $pdo->lastInsertId();

        // Insert individual question records with chosen_option
        $stmtAnswer = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, is_correct, chosen_option) VALUES (?, ?, ?, ?)");
        foreach($answers as $ans) {
            $stmtAnswer->execute([
                $attempt_id, 
                $ans['qid'], 
                $ans['correct'] ? 1 : 0,
                $ans['chosen'] // Logs A, B, C, or D
            ]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'attempt_id' => $attempt_id]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid session or payload']);
}
?>