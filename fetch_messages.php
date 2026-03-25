<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("You must log in.");
}

$current_user = $_SESSION['user_id'];

if (!isset($_GET['property_id']) || !isset($_GET['with'])) {
    exit("Invalid request.");
}

$property_id = intval($_GET['property_id']);
$with_user   = intval($_GET['with']);

// ✅ Get messages between current user and $with_user for this property
$sql = "SELECT m.*, u.name 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.property_id = ?
          AND ((m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $property_id, $current_user, $with_user, $with_user, $current_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p class='empty-msg'>No messages yet. Start the conversation.</p>";
}

while ($row = $result->fetch_assoc()) {
    $isSent = ($row['sender_id'] == $current_user);
    $class = $isSent ? "sent" : "received";

    echo "<article class='bubble $class'>";
    echo nl2br(htmlspecialchars($row['message']));
    echo "<small>" . date("H:i", strtotime($row['created_at'])) . "</small>";
    echo "</article>";
}
$stmt->close();
?>
