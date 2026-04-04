<?php
/**
 * PolyMath Configuration
 * Linked to: https://polymath.arcadiusengine.xyz/php/
 */

// Prevent session timeouts (Set to 1 year)
$session_lifetime = 31536000; 
ini_set('session.gc_maxlifetime', $session_lifetime);
session_set_cookie_params($session_lifetime);

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
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>