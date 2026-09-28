<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "sharecab";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Set default timezone for PHP and MySQL session
date_default_timezone_set('Asia/Kolkata');
$conn->query("SET time_zone = '+05:30'");

?>