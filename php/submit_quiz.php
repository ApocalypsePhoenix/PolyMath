<?php
/**
 * Robust Submit Quiz Handler
 * Fixed: Saves Level ID and Chosen Options to update history properly
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
        
        // Fetch level_id to satisfy foreign key constraints
        $stmtLvl = $pdo->prepare("SELECT level_id FROM quizzes WHERE quiz_id = ?");
        $stmtLvl->execute([$quiz_id]);
        $level_id = $stmtLvl->fetchColumn();

        // Insert mission attempt
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, level_id, score, total_questions, time_taken_seconds) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $quiz_id, $level_id, $score, $total, $time]);
        $attempt_id = $pdo->lastInsertId();

        // Insert individual answer data for Intelligence Report
        $stmtAnswer = $pdo->prepare("INSERT INTO user_answers (attempt_id, question_id, is_correct, chosen_option) VALUES (?, ?, ?, ?)");
        foreach($answers as $ans) {
            $stmtAnswer->execute([
                $attempt_id, 
                $ans['qid'], 
                $ans['correct'] ? 1 : 0,
                $ans['chosen']
            ]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'attempt_id' => $attempt_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>