<?php
require_once 'db.php';

// Access Control: Ensure only admins can download reports
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if ($quiz_id === 0) {
    die("Invalid Quiz ID. Please export from a specific quiz.");
}

try {
    // Fetch Quiz Name for a clean filename
    $stmtQuiz = $pdo->prepare("SELECT quiz_name FROM quizzes WHERE quiz_id = ?");
    $stmtQuiz->execute([$quiz_id]);
    $quizName = $stmtQuiz->fetchColumn();
    
    if (!$quizName) die("Quiz not found.");
    
    $cleanQuizName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $quizName);

    // Master query - Left Join on classes ensures no students are dropped if they lack a class
    $stmt = $pdo->prepare("
        SELECT 
            u.student_name,
            u.matric_number,
            c.class_name, 
            q.quiz_name,
            l.level_name as quiz_difficulty, 
            qa.completed_at as datetime,
            qa.score,
            qa.total_questions,
            (
                SELECT COUNT(*) 
                FROM quiz_attempts qa2 
                WHERE qa2.quiz_id = qa.quiz_id 
                  AND qa2.user_id = qa.user_id 
                  AND qa2.completed_at <= qa.completed_at
            ) as attempt_number
        FROM quiz_attempts qa
        JOIN users u ON qa.user_id = u.user_id
        LEFT JOIN classes c ON u.class_id = c.class_id
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        JOIN levels l ON qa.level_id = l.level_id
        WHERE qa.quiz_id = ?
        ORDER BY c.class_name ASC, u.student_name ASC, qa.completed_at ASC
    ");
    $stmt->execute([$quiz_id]);
    $data = $stmt->fetchAll();

    // Set headers to trigger an immediate file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Report_' . $cleanQuizName . '_' . date('Ymd_Hi') . '.csv');

    // Open the output stream
    $output = fopen('php://output', 'w');

    // Add the CSV Header Row (Split Score and Total Questions for easy charting in Excel)
    fputcsv($output, [
        'Student Name', 
        'Matrix No', 
        'Class', 
        'Quiz Name', 
        'Quiz Difficulty', 
        'Datetime', 
        'Score',
        'Total Questions',
        'Phase'
    ]);

    // Add the Data Rows
    foreach ($data as $row) {
        $phase = ($row['attempt_number'] == 1) ? 'Pre-Lesson' : 'Post-Lesson';
        
        // Clean up the datetime string to be more human-readable
        $formatted_datetime = date('d M Y, h:i A', strtotime($row['datetime']));
        
        fputcsv($output, [
            $row['student_name'] ?: 'Unknown',
            $row['matric_number'] ?: 'Unknown',
            $row['class_name'] ?: 'No Class',
            $row['quiz_name'],
            $row['quiz_difficulty'],
            $formatted_datetime,
            $row['score'],
            $row['total_questions'],
            $phase
        ]);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    // In case of database error, stop and show the message
    die("Export failed: " . $e->getMessage());
}
?>