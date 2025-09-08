<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    http_response_code(403);
    exit;
}

$landlord_id = $_SESSION['user_id'];
$property_id = intval($_GET['property_id']);
$renter_id = intval($_GET['renter_id']);

$stmt = $conn->prepare("
    SELECT m.*, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.property_id=? AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $property_id, $landlord_id, $renter_id, $renter_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

header('Content-Type: application/json');
echo json_encode($messages);
