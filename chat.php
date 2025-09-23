<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get property and chat participant
$property_id = intval($_GET['property_id'] ?? 0);
$with_user   = intval($_GET['with'] ?? 0);

if(!$property_id || !$with_user){
    echo "Invalid chat request.";
    exit;
}

// Validate property exists
$stmt = $conn->prepare("SELECT * FROM properties WHERE id=? LIMIT 1");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if(!$property){
    echo "Property not found";
    exit;
}

// Allow both renter & landlord to chat
if(($role=='landlord' && $current_user != $property['landlord_id']) ||
   ($role=='renter' && $current_user == $property['landlord_id'])){
    echo "Access denied";
    exit;
}

// Handle sending message (AJAX)
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['message'])){
    $msg = trim($_POST['message']);
    $stmt = $conn->prepare("INSERT INTO messages (property_id,sender_id,receiver_id,message,created_at) VALUES (?,?,?,?,NOW())");
    $stmt->bind_param("iiii", $property_id, $current_user, $with_user, $msg);
    $stmt->execute();
    exit;
}

// Mark received messages as read
$stmt = $conn->prepare("UPDATE messages SET read_status=1 WHERE sender_id=? AND receiver_id=? AND property_id=?");
$stmt->bind_param("iii", $with_user, $current_user, $property_id);
$stmt->execute();
?>
