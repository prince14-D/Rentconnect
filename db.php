<?php
$host = "localhost";
$user = "rentuser";
$pass = "StrongPassword123!"; // use your password
$db   = "rentconnect_db";


$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
