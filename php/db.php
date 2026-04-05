<?php
/**
 * PolyMath Core Configuration
 * Enforces session persistence and auto-migration for new features
 */

// 1. Session Persistence (1 Year)
$session_lifetime = 31536000; 
ini_set('session.gc_maxlifetime', $session_lifetime);
ini_set('session.cookie_lifetime', $session_lifetime);
ini_set('session.use_only_cookies', 1);

session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'domain' => '', 
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', 'https://polymath.arcadiusengine.xyz/php/');

$host = 'localhost'; 
$db   = 'arcadius_polymath';
$user = 'arcadius_owner';
$pass = 'R5#8%DOOg[{Y+z7=';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // --- SELF-HEALING DATABASE MIGRATIONS ---
    // Ensure core tables exist (prevents crashes if they were missing from the SQL dump)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quizzes` (
        `quiz_id` int(11) NOT NULL AUTO_INCREMENT,
        `quiz_name` varchar(255) NOT NULL,
        `level_id` int(11) NOT NULL,
        `is_timed` tinyint(1) DEFAULT 0,
        `time_limit` int(11) DEFAULT 300,
        `is_published` tinyint(1) DEFAULT 0,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`quiz_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_answers` (
        `answer_id` int(11) NOT NULL AUTO_INCREMENT,
        `attempt_id` int(11) NOT NULL,
        `question_id` int(11) NOT NULL,
        `is_correct` tinyint(1) NOT NULL,
        `chosen_option` char(1) DEFAULT NULL,
        PRIMARY KEY (`answer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;");

    // Crucial column fixes to prevent 'submit_quiz.php' from failing
    $pdo->exec("ALTER TABLE `quiz_attempts` ADD COLUMN IF NOT EXISTS `quiz_id` INT(11) DEFAULT NULL AFTER `user_id`");
    $pdo->exec("ALTER TABLE `questions` ADD COLUMN IF NOT EXISTS `quiz_id` INT(11) DEFAULT NULL AFTER `question_id`");
    $pdo->exec("ALTER TABLE `questions` ADD COLUMN IF NOT EXISTS `question_image` varchar(255) DEFAULT NULL AFTER `solution_text`");
    $pdo->exec("ALTER TABLE `questions` ADD COLUMN IF NOT EXISTS `solution_image` varchar(255) DEFAULT NULL AFTER `question_image`");

    // Add JSON column to quiz_attempts to compress all choices into a single row
    $pdo->exec("ALTER TABLE `quiz_attempts` ADD COLUMN IF NOT EXISTS `answers_json` LONGTEXT DEFAULT NULL AFTER `time_taken_seconds`");

    // Existing migrations
    $pdo->exec("ALTER TABLE `quizzes` ADD COLUMN IF NOT EXISTS `is_published` TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE `user_answers` ADD COLUMN IF NOT EXISTS `chosen_option` CHAR(1) DEFAULT NULL");

} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>