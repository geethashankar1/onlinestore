<?php
// config/db.php
// Connection settings are read from environment variables (set in docker-compose.yml).
// Fallback defaults keep the file working outside Docker, but no real secrets live here.

$servername = getenv('DB_HOST') ?: 'db';            // Docker service name, NOT localhost
$username   = getenv('DB_USER') ?: 'eshop';
$password   = getenv('DB_PASS') ?: 'eshop_pass';
$dbname     = getenv('DB_NAME') ?: 'ecommerce_db';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character set (good practice)
$conn->set_charset("utf8mb4");

// Start session if not already started - useful for cart, login status
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
