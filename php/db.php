<?php
/**
 * PolyMath Database Connection Configuration
 * Directory: public_html/polymath.arcadiusengine.xyz/php
 */

// Since files are in the /php/ subdirectory, we update the BASE_URL accordingly
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
    die("Database connection failed. Please contact admin at polymath.arcadiusengine.xyz");
}
?>