<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

if (!isset($_GET['property_id']) || !isset($_GET['with'])) {
    echo "<p>Invalid chat request.</p>"; exit;
}

$property_id = intval($_GET['property_id']);
$renter_id   = intval($_GET['with']);

// Fetch property
$sql = "SELECT * FROM properties WHERE id=? AND landlord_id=? AND status='approved' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $property_id, $landlord_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
if(!$property){ echo "<p>Property not found.</p>"; exit; }

// Handle sending messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);
    if($msg!==''){
        $stmt = $conn->prepare("INSERT INTO messages (property_id,sender_id,receiver_id,message,created_at) VALUES (?,?,?,?,NOW())");
        $stmt->bind_param("iiii", $property_id, $landlord_id, $renter_id, $msg);
        $stmt->execute();
    }
    exit;
}

// Mark messages from renter as read
$update = $conn->prepare("UPDATE messages SET read_status=1 WHERE sender_id=? AND receiver_id=? AND property_id=?");
$update->bind_param("iii", $renter_id, $landlord_id, $property_id);
$update->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat - <?= htmlspecialchars($property['title']) ?></title>
<style>
body { margin:0; font-family:sans-serif; display:flex; flex-direction:column; height:100vh; background:#f0f2f5; }
.chat-header { background:#075E54; color:white; padding:15px; display:flex; align-items:center; position:sticky; top:0; z-index:10; }
.chat-header img { width:40px; height:40px; border-radius:50%; margin-right:10px; object-fit:cover; }
.chat-header h2 { margin:0; font-size:1rem; }
.chat-box { flex:1; display:flex; flex-direction:column; }
.messages { flex:1; overflow-y:auto; padding:15px; display:flex; flex-direction:column; }
.message { margin-bottom:10px; padding:10px 14px; border-radius:20px; max-width:70%; line-height:1.4; font-size:0.95rem; }
.sent { background:#dcf8c6; align-self:flex-end; border-bottom-right-radius:4px; }
.received { background:white; align-self:flex-start; border-bottom-left-radius:4px; }
.chat-input { display:flex; padding:10px; background:white; border-top:1px solid #ddd; }
.chat-input input { flex:1; padding:12px; border-radius:25px; border:1px solid #ccc; font-size:1rem; outline:none; }
.chat-input button { margin-left:8px; padding:0 20px; border:none; border-radius:25px; background:#075E54; color:white; font-size:1rem; cursor:pointer; }
.chat-input button:hover { background:#0a7c66; }
@media(max-width:600px){ .chat-header h2{ font-size:0.9rem; } .chat-input input, .chat-input button{ font-size:0.9rem; } }
</style>
</head>
<body>

<div class="chat-header">
    <img src="fetch_profile.php?user_id=<?= $renter_id ?>" alt="Profile">
    <h2><?= htmlspecialchars($property['title']) ?> - <?= htmlspecialchars($_GET['with_name'] ?? 'Renter') ?></h2>
</div>

<div class="chat-box">
    <div id="messages" class="messages">Loading...</div>

    <form id="chatForm" class="chat-input">
        <input type="text" id="messageInput" placeholder="Type a message..." required>
        <button type="submit">➤</button>
    </form>
</div>

<script>
function loadMessages(){
    fetch("fetch_messages.php?property_id=<?= $property_id ?>&with=<?= $renter_id ?>")
    .then(res=>res.text())
    .then(data=>{
        const msgBox = document.getElementById("messages");
        const atBottom = msgBox.scrollTop + msgBox.clientHeight >= msgBox.scrollHeight - 50;
        msgBox.innerHTML = data;
        if(atBottom) msgBox.scrollTop = msgBox.scrollHeight;
    });
}

// Send message
document.getElementById("chatForm").addEventListener("submit", function(e){
    e.preventDefault();
    let formData = new FormData();
    formData.append('message', document.getElementById("messageInput").value);
    fetch("landlord_chat_room.php?property_id=<?= $property_id ?>&with=<?= $renter_id ?>", {
        method:'POST', body:formData
    }).then(()=>{ document.getElementById("messageInput").value=''; loadMessages(); });
});

// Auto-refresh every 3s
setInterval(loadMessages, 3000);
loadMessages();
</script>

</body>
</html>
