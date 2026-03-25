<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id'])) {
    exit("You must log in.");
}

$current_user = $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? '');

if (!isset($_GET['property_id']) || !isset($_GET['with'])) {
    exit("Invalid request.");
}

$property_id = intval($_GET['property_id']);
$with_user   = intval($_GET['with']);

if (!rc_mig_can_access_chat($conn, (int) $current_user, $role, $property_id, $with_user)) {
    exit('Access denied.');
}

$messages = rc_mig_get_chat_messages($conn, $property_id, (int) $current_user, $with_user);

if (count($messages) === 0) {
    echo "<p class='empty-msg'>No messages yet. Start the conversation.</p>";
}

foreach ($messages as $row) {
    $isSent = ($row['sender_id'] == $current_user);
    $class = $isSent ? "sent" : "received";

    echo "<article class='bubble $class'>";
    echo nl2br(htmlspecialchars($row['message']));
    echo "<small>" . date("H:i", strtotime($row['created_at'])) . "</small>";
    echo "</article>";
}
?>
