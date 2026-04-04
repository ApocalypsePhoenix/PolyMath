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
    // Ensure quizzes table has is_published column
    $pdo->exec("ALTER TABLE `quizzes` ADD COLUMN IF NOT EXISTS `is_published` TINYINT(1) DEFAULT 0");
    // Ensure user_answers table has chosen_option for feedback UI
    $pdo->exec("ALTER TABLE `user_answers` ADD COLUMN IF NOT EXISTS `chosen_option` CHAR(1) DEFAULT NULL");

} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>