<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("Access denied");
}

$user_id = $_SESSION['user_id'];
$property_id = intval($_GET['property_id'] ?? 0);
$with_user = intval($_GET['with'] ?? 0);

// Fetch messages between these two users for this property
$stmt = $conn->prepare("
    SELECT m.*, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.property_id = ? AND 
          ((m.sender_id = ? AND m.receiver_id = ?) OR 
           (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $property_id, $user_id, $with_user, $with_user, $user_id);
$stmt->execute();
$messages = $stmt->get_result();

// Output messages
while($row = $messages->fetch_assoc()):
?>
    <div class="message <?= ($row['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
        <strong><?= htmlspecialchars($row['sender_name']); ?>:</strong> <?= htmlspecialchars($row['message']); ?>
        <div style="font-size:0.7em; color:#555; margin-top:2px;"><?= $row['created_at']; ?></div>
    </div>
<?php endwhile; ?>
