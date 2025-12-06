<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "fastfood_system");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session
session_start();
?>