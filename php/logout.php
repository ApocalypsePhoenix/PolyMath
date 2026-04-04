<?php
session_start();
require_once 'db.php';

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page using the global BASE_URL
header("Location: " . BASE_URL . "login.php");
exit();
?>