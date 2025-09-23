<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user = $_SESSION['user_id'];

/* -----------------------
   FETCH CONVERSATIONS
------------------------ */
function fetchConversations($conn, $current_user) {
    $sql = "SELECT 
                m.property_id, 
                u.id AS with_id, 
                u.name AS with_name, 
                u.profile_pic, 
                p.title AS property_title,
                MAX(m.created_at) AS last_time,
                (SELECT message FROM messages 
                 WHERE property_id = m.property_id 
                   AND ((sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?))
                 ORDER BY created_at DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM messages 
                 WHERE property_id = m.property_id
                   AND receiver_id = ?
                   AND sender_id = u.id
                   AND is_read = 0) AS unread_count
            FROM messages m
            JOIN properties p ON p.id = m.property_id
            JOIN users u ON 
                (CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id = u.id 
                    ELSE m.sender_id = u.id 
                 END)
            WHERE (m.sender_id = ? OR m.receiver_id = ?)
            GROUP BY m.property_id, u.id
            ORDER BY last_time DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $current_user, $current_user, $current_user, $current_user, $current_user, $current_user);
    $stmt->execute();
    return $stmt->get_result();
}

/* -----------------------
   AJAX REFRESH SUPPORT
------------------------ */
if (isset($_GET['ajax']) && $_GET['ajax'] == "1") {
    $conversations = fetchConversations($conn, $current_user);
    while ($row = $conversations->fetch_assoc()): ?>
        <li class="chat-item" onclick="window.location.href='chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['with_id']; ?>'">
            <div class="chat-avatar">
                <?php if (!empty($row['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Avatar">
                <?php else: ?>
                    <img src="images/default-avatar.png" alt="Avatar">
                <?php endif; ?>
                <?php if ($row['unread_count'] > 0): ?>
                    <span class="unread"><?php echo $row['unread_count']; ?></span>
                <?php endif; ?>
            </div>
            <div class="chat-info">
                <h3><?php echo htmlspecialchars($row['with_name']); ?> (<?php echo htmlspecialchars($row['property_title']); ?>)</h3>
                <p><?php echo htmlspecialchars($row['last_message']); ?></p>
            </div>
            <div class="chat-time"><?php echo date("H:i", strtotime($row['last_time'])); ?></div>
        </li>
    <?php endwhile;
    exit;
}

$conversations = fetchConversations($conn, $current_user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chats</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body {
  margin:0;
  font-family:'Segoe UI', Tahoma, sans-serif;
  background:#f0f2f5;
}
.header {
  background:#075E54;
  color:white;
  padding:15px;
  text-align:center;
  font-size:1.2rem;
  font-weight:500;
}
.chat-list {
  list-style:none;
  padding:0;
  margin:0;
}
.chat-item {
  display:flex;
  align-items:center;
  padding:15px;
  border-bottom:1px solid #ddd;
  background:#fff;
  cursor:pointer;
}
.chat-item:hover {
  background:#f9f9f9;
}
.chat-avatar {
  position:relative;
  width:50px;
  height:50px;
  border-radius:50%;
  overflow:hidden;
  margin-right:15px;
  flex-shrink:0;
}
.chat-avatar img {
  width:100%;
  height:100%;
  object-fit:cover;
}
.chat-avatar .unread {
  position:absolute;
  bottom:0;
  right:0;
  background:#25D366;
  color:white;
  font-size:0.7rem;
  padding:3px 6px;
  border-radius:50%;
  border:2px solid white;
}
.chat-info {
  flex:1;
}
.chat-info h3 {
  margin:0;
  font-size:1rem;
  color:#333;
}
.chat-info p {
  margin:4px 0 0;
  color:#666;
  font-size:0.9rem;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.chat-time {
  font-size:0.8rem;
  color:#999;
  margin-left:10px;
  white-space:nowrap;
}
.no-chats {
  text-align:center;
  padding:50px;
  color:#666;
}
</style>
</head>
<body>
<div class="header">Chats</div>

<ul class="chat-list" id="chatList">
<?php if ($conversations->num_rows > 0): ?>
  <?php while ($row = $conversations->fetch_assoc()): ?>
    <li class="chat-item" onclick="window.location.href='chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['with_id']; ?>'">
      <div class="chat-avatar">
        <?php if (!empty($row['profile_pic'])): ?>
            <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Avatar">
        <?php else: ?>
            <img src="images/default-avatar.png" alt="Avatar">
        <?php endif; ?>
        <?php if ($row['unread_count'] > 0): ?>
            <span class="unread"><?php echo $row['unread_count']; ?></span>
        <?php endif; ?>
      </div>
      <div class="chat-info">
        <h3><?php echo htmlspecialchars($row['with_name']); ?> (<?php echo htmlspecialchars($row['property_title']); ?>)</h3>
        <p><?php echo htmlspecialchars($row['last_message']); ?></p>
      </div>
      <div class="chat-time"><?php echo date("H:i", strtotime($row['last_time'])); ?></div>
    </li>
  <?php endwhile; ?>
<?php else: ?>
  <p class="no-chats">No conversations yet. Start chatting from a property page.</p>
<?php endif; ?>
</ul>

<script>
// Auto-refresh chat list every 5s
setInterval(()=>{
    fetch("chat_list.php?ajax=1")
    .then(res=>res.text())
    .then(html=>{
        document.getElementById("chatList").innerHTML = html;
    });
},5000);
</script>
</body>
</html>
