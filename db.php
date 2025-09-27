<?php
$host = "localhost";
$user = "rentuser";
$pass = "StrongPassword123!"; // use your password
$db   = "rentconnect_db";

<<<<<<< HEAD

=======
$conn = new mysqli("localhost", "root", "your_password", "rentconnect");
>>>>>>> 612aa9af99eb0f3160a7956ab380575a1df24107

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
