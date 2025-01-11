<?php
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    require_once 'database.local.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'wpxcxfmy_bprefect');
    define('DB_PASS', 'spWB8Y(=m[mv');
    define('DB_NAME', 'wpxcxfmy_burning_to_cook');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
} 