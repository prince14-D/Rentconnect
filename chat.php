<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$property_id = intval($_GET['property_id'] ?? 0);
$with_user = intval($_GET['with'] ?? 0);

// Get other user's info
$stmt = $conn->prepare("SELECT name, role FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $with_user);
$stmt->execute();
$with_res = $stmt->get_result();
if ($with_res->num_rows == 0) die("User not found.");
$with_name = $with_res->fetch_assoc()['name'];

// Validate access
if($role === 'renter'){
    $stmt = $conn->prepare("
        SELECT * FROM requests r
        WHERE r.user_id=? AND r.property_id=?
    ");
    $stmt->bind_param("ii", $user_id, $property_id);
} else if($role === 'landlord'){
    $stmt = $conn->prepare("
        SELECT * FROM properties 
        WHERE id=? AND owner_id=?
    ");
    $stmt->bind_param("ii", $property_id, $user_id);
} else {
    die("Access denied.");
}
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows == 0) die("Access denied to this chat.");

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message != '') {
        $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message) VALUES (?,?,?,?)");
        $stmt->bind_param("iiis", $property_id, $user_id, $with_user, $message);
        $stmt->execute();
    }
}

// Fetch messages
$stmt = $conn->prepare("
    SELECT m.*, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id=u.id
    WHERE m.property_id = ? AND 
          ((m.sender_id = ? AND m.receiver_id = ?) OR 
           (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $property_id, $user_id, $with_user, $with_user, $user_id);
$stmt->execute();
$messages = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat with <?= htmlspecialchars($with_name) ?></title>
<style>
body { font-family: Arial,sans-serif; margin:0; padding:0; background:#f4f6f9; }
.chat-container { max-width:700px; margin:20px auto; background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.1); display:flex; flex-direction:column; height:80vh; }
.chat-header { padding:15px; background:linear-gradient(90deg,#4CAF50,#2E7D32); color:#fff; font-weight:bold; border-radius:12px 12px 0 0; display:flex; align-items:center; justify-content:space-between; }
.chat-header a.back { color:#fff; text-decoration:none; font-weight:bold; font-size:0.9em; background:rgba(255,255,255,0.2); padding:6px 12px; border-radius:6px; }
.chat-messages { flex:1; padding:15px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; background:#f0f2f5; }
.message { max-width:70%; padding:10px 15px; border-radius:20px; font-size:0.9em; word-wrap:break-word; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
.sent { background:#4CAF50; color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.received { background:#e0e0e0; color:#333; align-self:flex-start; border-bottom-left-radius:4px; }
.chat-input { display:flex; padding:10px; border-top:1px solid #ddd; background:#fff; border-radius:0 0 12px 12px; }
.chat-input input { flex:1; padding:12px; border-radius:6px; border:1px solid #ccc; margin-right:10px; }
.chat-input button { padding:12px 18px; border:none; border-radius:6px; background:#2E7D32; color:#fff; cursor:pointer; font-weight:bold; }
.chat-input button:hover { background:#1b4d24; }
@media(max-width:500px){ .message { max-width:90%; font-size:0.85em; } .chat-header { font-size:0.9em; } }
</style>
</head>
<body>
<div class="chat-container">
    <div class="chat-header">
        <span>💬 Chat with <?= htmlspecialchars($with_name) ?></span>
        <a href="<?= ($role==='renter')?'renter_dashboard.php':'landlord_dashboard.php' ?>" class="back">⬅ Back</a>
    </div>
    <div class="chat-messages" id="chatMessages">
        <?php while($row = $messages->fetch_assoc()): ?>
            <div class="message <?= ($row['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                <strong><?= htmlspecialchars($row['sender_name']); ?>:</strong> <?= htmlspecialchars($row['message']); ?>
                <div style="font-size:0.7em; color:#555; margin-top:2px;"><?= $row['created_at']; ?></div>
            </div>
        <?php endwhile; ?>
    </div>
    <form class="chat-input" method="post">
        <input type="text" name="message" placeholder="Type a message..." autocomplete="off">
        <button type="submit">Send</button>
    </form>
</div>

<script>
// Auto scroll to bottom
const chatDiv = document.getElementById('chatMessages');
chatDiv.scrollTop = chatDiv.scrollHeight;

// AJAX refresh every 3 sec
setInterval(() => {
    fetch('chat_refresh.php?property_id=<?= $property_id; ?>&with=<?= $with_user; ?>')
    .then(res => res.text())
    .then(data => { chatDiv.innerHTML = data; chatDiv.scrollTop = chatDiv.scrollHeight; });
}, 3000);
</script>
</body>
</html>
