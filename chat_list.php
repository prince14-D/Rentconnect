<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user = $_SESSION['user_id'];

function fetchConversations($conn, $current_user) {
    return rc_mig_get_chat_conversations($conn, (int) $current_user);
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $conversations = fetchConversations($conn, $current_user);
    foreach ($conversations as $row): ?>
      <li class="chat-item" onclick="window.location.href='chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['with_id']; ?>'">
        <div class="avatar-wrap">
          <?php if (!empty($row['profile_pic'])): ?>
            <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Avatar">
          <?php else: ?>
            <img src="images/default-avatar.png" alt="Avatar">
          <?php endif; ?>
          <?php if ($row['unread_count'] > 0): ?>
            <span class="unread"><?php echo $row['unread_count']; ?></span>
          <?php endif; ?>
        </div>
        <div class="info">
          <h3><?php echo htmlspecialchars($row['with_name']); ?> <small><?php echo htmlspecialchars($row['property_title']); ?></small></h3>
          <p><?php echo htmlspecialchars($row['last_message']); ?></p>
        </div>
        <span class="time"><?php echo date('H:i', strtotime($row['last_time'])); ?></span>
      </li>
    <?php endforeach;
    exit;
}

$conversations = fetchConversations($conn, $current_user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Chats - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --line: rgba(31, 36, 48, 0.12);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background:
    radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.2), transparent 34%),
    radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.18), transparent 30%),
    linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
  min-height: 100vh;
}
.container { width: min(920px, 94vw); margin: 0 auto; }
.header {
  margin-top: 22px;
  border-radius: 18px;
  color: #fff;
  padding: 16px;
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.header h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.35rem, 2.8vw, 1.8rem);
  letter-spacing: -0.02em;
}
.header .back {
  color: #fff;
  text-decoration: none;
  font-weight: 700;
  background: rgba(255, 255, 255, 0.2);
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 0.85rem;
}

.list {
  list-style: none;
  margin-top: 12px;
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
}
.chat-item {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 10px;
  align-items: center;
  padding: 12px;
  border-bottom: 1px solid var(--line);
  cursor: pointer;
}
.chat-item:hover { background: rgba(31, 143, 103, 0.05); }
.chat-item:last-child { border-bottom: none; }

.avatar-wrap {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  position: relative;
}
.avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.unread {
  position: absolute;
  right: -2px;
  bottom: -2px;
  min-width: 20px;
  height: 20px;
  border-radius: 999px;
  background: linear-gradient(140deg, #1f8f67, #15543e);
  color: #fff;
  border: 2px solid #fff;
  display: grid;
  place-items: center;
  font-size: 0.72rem;
  font-weight: 800;
}

.info h3 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 0.96rem;
  margin-bottom: 3px;
}
.info h3 small {
  font-family: 'Manrope', sans-serif;
  font-size: 0.8rem;
  color: #5f6b83;
}
.info p {
  color: var(--muted);
  font-size: 0.9rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 52ch;
}
.time { color: #7b8396; font-size: 0.78rem; }
.no-chats { padding: 26px; text-align: center; color: var(--muted); }
</style>
</head>
<body>
<main class="container">
  <section class="header">
    <h1>Chats</h1>
    <a class="back" href="<?php echo ($_SESSION['role'] === 'landlord') ? 'landlord_dashboard.php' : 'renter_dashboard.php'; ?>">Back</a>
  </section>

  <ul class="list" id="chatList">
    <?php if (count($conversations) > 0): ?>
      <?php foreach ($conversations as $row): ?>
        <li class="chat-item" onclick="window.location.href='chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['with_id']; ?>'">
          <div class="avatar-wrap">
            <?php if (!empty($row['profile_pic'])): ?>
              <img src="<?php echo htmlspecialchars($row['profile_pic']); ?>" alt="Avatar">
            <?php else: ?>
              <img src="images/default-avatar.png" alt="Avatar">
            <?php endif; ?>
            <?php if ($row['unread_count'] > 0): ?>
              <span class="unread"><?php echo $row['unread_count']; ?></span>
            <?php endif; ?>
          </div>
          <div class="info">
            <h3><?php echo htmlspecialchars($row['with_name']); ?> <small><?php echo htmlspecialchars($row['property_title']); ?></small></h3>
            <p><?php echo htmlspecialchars($row['last_message']); ?></p>
          </div>
          <span class="time"><?php echo date('H:i', strtotime($row['last_time'])); ?></span>
        </li>
      <?php endforeach; ?>
    <?php else: ?>
      <li class="no-chats">No conversations yet. Start chatting from a property page.</li>
    <?php endif; ?>
  </ul>
</main>

<script>
setInterval(() => {
  fetch('chat_list.php?ajax=1')
    .then((res) => res.text())
    .then((html) => {
      document.getElementById('chatList').innerHTML = html;
    });
}, 5000);
</script>
</body>
</html>
