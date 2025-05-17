<?php
$host = "localhost";
$user = "root"; 
$pass = ""; // Never leave blank in production!
$dbname = "tawasul_test_2";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>