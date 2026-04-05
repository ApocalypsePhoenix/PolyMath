<?php
require_once 'db.php';

// Access Control: Ensure only admins can download reports
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all quiz attempts with user, class, and level details
try {
    // Fixed: changed u.u_id to u.user_id to match the database schema
    $stmt = $pdo->query("
        SELECT 
            qa.attempt_id, 
            u.username, 
            c.class_name, 
            l.level_name, 
            qa.score, 
            qa.total_questions, 
            qa.time_taken_seconds, 
            qa.completed_at
        FROM quiz_attempts qa
        JOIN users u ON qa.user_id = u.user_id
        JOIN classes c ON u.class_id = c.class_id
        JOIN levels l ON qa.level_id = l.level_id
        ORDER BY qa.completed_at DESC
    ");
    $data = $stmt->fetchAll();

    // Set headers to trigger an immediate file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=polymath_report_' . date('Y-m-d_H-i') . '.csv');

    // Open the output stream
    $output = fopen('php://output', 'w');

    // Add the CSV Header Row
    fputcsv($output, [
        'Attempt ID', 
        'Student Name', 
        'Class', 
        'Difficulty Level', 
        'Score', 
        'Total Questions', 
        'Time (Seconds)', 
        'Date Completed'
    ]);

    // Add the Data Rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    // In case of database error, stop and show the message
    die("Export failed: " . $e->getMessage());
}
?>