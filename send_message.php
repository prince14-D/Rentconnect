<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    http_response_code(403);
    exit;
}

$landlord_id = $_SESSION['user_id'];
$property_id = intval($_POST['property_id']);
$receiver_id = intval($_POST['receiver_id']);
$message = trim($_POST['chat_message']);

if ($message !== "") {
    $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $property_id, $landlord_id, $receiver_id, $message);
    $stmt->execute();
}
