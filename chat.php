<?php
session_start();
include "db.php";

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$property_id = intval($_GET['property_id']);
$chat_with = intval($_GET['with']); // landlord_id or renter_id

// Handle new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message !== "") {
        $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $property_id, $user_id, $chat_with, $message);
        $stmt->execute();
    }
    header("Location: chat.php?property_id=$property_id&with=$chat_with");
    exit;
}

// Fetch property details
$stmt = $conn->prepare("SELECT title FROM properties WHERE id=? LIMIT 1");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

// Fetch chat messages
$sql = "SELECT m.*, u.name AS sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id
        WHERE m.property_id=? 
        AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
        ORDER BY m.created_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $property_id, $user_id, $chat_with, $chat_with, $user_id);
$stmt->execute();
$messages = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat - <?php echo htmlspecialchars($property['title']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { margin-top:0; color:#2E7D32; }
        .chat-box { background:white; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); height:400px; overflow-y:scroll; }
        .msg { margin:10px 0; padding:10px; border-radius:8px; max-width:60%; }
        .me { background:#DCF8C6; margin-left:auto; }
        .other { background:#E3F2FD; margin-right:auto; }
        .msg small { display:block; font-size:0.8em; color:#555; margin-top:4px; }
        form { margin-top:15px; display:flex; gap:10px; }
        input[type=text] { flex:1; padding:10px; border-radius:6px; border:1px solid #ccc; }
        button { padding:10px 15px; border:none; border-radius:6px; background:#2196F3; color:white; cursor:pointer; }
        button:hover { background:#1976D2; }
        a.back { display:inline-block; margin-bottom:15px; color:#333; text-decoration:none; }
    </style>
</head>
<body>
    <a href="renter_dashboard.php" class="back">⬅ Back</a>
    <h2>Chat about "<?php echo htmlspecialchars($property['title']); ?>"</h2>

    <div class="chat-box">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="msg <?php echo ($msg['sender_id'] == $user_id) ? 'me' : 'other'; ?>">
                    <b><?php echo htmlspecialchars($msg['sender_name']); ?>:</b><br>
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    <small><?php echo $msg['created_at']; ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No messages yet. Start the conversation!</p>
        <?php endif; ?>
    </div>

    <form method="POST">
        <input type="text" name="message" placeholder="Type your message..." required>
        <button type="submit">Send</button>
    </form>
</body>
</html>
