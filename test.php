<?php
$host = "localhost";
$user = "root";        // XAMPP default MySQL user
$pass = "";            // leave empty unless you set a password
$db   = "rentconnect_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
