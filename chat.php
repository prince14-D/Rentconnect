<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$property_id = intval($_GET['property_id'] ?? 0);
$with_user = intval($_GET['with'] ?? 0);

if ($property_id <= 0 || $with_user <= 0) {
  die('Invalid chat request.');
}

$with_row = rc_mig_get_user_by_id($conn, $with_user);
if (!$with_row) {
    die("User not found.");
}
$with_name = (string) ($with_row['name'] ?? 'User');

if (!rc_mig_can_access_chat($conn, (int) $user_id, (string) $role, $property_id, $with_user)) {
    die("Access denied to this chat.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if ($message !== '') {
    rc_mig_create_chat_message($conn, $property_id, (int) $user_id, $with_user, $message);
    }
}

$messages = rc_mig_get_chat_messages($conn, $property_id, (int) $user_id, $with_user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Chat about rental properties.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Chat with <?php echo htmlspecialchars($with_name); ?></title>
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
  display: grid;
  place-items: center;
  padding: 14px;
}
.chat {
  width: min(920px, 96vw);
  height: min(88vh, 780px);
  display: grid;
  grid-template-rows: auto 1fr auto;
  border-radius: 16px;
  overflow: hidden;
  background: rgba(255,255,255,0.95);
  border: 1px solid rgba(255,255,255,0.9);
  box-shadow: 0 16px 34px rgba(20, 31, 44, 0.16);
}
.chat-header {
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  color: #fff;
  padding: 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.chat-header h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.08rem;
  letter-spacing: -0.01em;
}
.back {
  color: #fff;
  text-decoration: none;
  font-weight: 700;
  background: rgba(255,255,255,0.2);
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 0.85rem;
}
.messages {
  overflow-y: auto;
  padding: 14px;
  background: #f3f7fa;
  display: grid;
  gap: 10px;
}
.bubble {
  max-width: min(72%, 520px);
  border-radius: 14px;
  padding: 9px 11px;
  font-size: 0.9rem;
  line-height: 1.45;
}
.sent {
  margin-left: auto;
  background: linear-gradient(140deg, #1f8f67, #15543e);
  color: #fff;
  border-bottom-right-radius: 4px;
}
.received {
  margin-right: auto;
  background: #fff;
  border: 1px solid var(--line);
  color: #2b3344;
  border-bottom-left-radius: 4px;
}
.bubble small {
  display: block;
  margin-top: 4px;
  font-size: 0.72rem;
  opacity: 0.82;
}
.chat-input {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  padding: 10px;
  border-top: 1px solid var(--line);
  background: #fff;
}
.chat-input input {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 11px 12px;
  font: inherit;
}
.chat-input button {
  border: none;
  border-radius: 10px;
  padding: 11px 14px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(140deg, var(--brand), var(--brand-deep));
  cursor: pointer;
}
@media (max-width: 640px) {
  .bubble { max-width: 90%; }
}
</style>
</head>
<body>
<div class="chat">
  <header class="chat-header">
    <h1>Chat with <?php echo htmlspecialchars($with_name); ?></h1>
    <a href="<?php echo ($role === 'renter') ? 'renter_dashboard.php' : 'landlord_dashboard.php'; ?>" class="back">Back</a>
  </header>

  <section class="messages" id="chatMessages">
    <?php foreach ($messages as $row): ?>
      <article class="bubble <?php echo ($row['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
        <strong><?php echo htmlspecialchars($row['sender_name']); ?>:</strong>
        <?php echo htmlspecialchars($row['message']); ?>
        <small><?php echo htmlspecialchars($row['created_at']); ?></small>
      </article>
    <?php endforeach; ?>
  </section>

  <form class="chat-input" method="post">
    <input type="text" name="message" placeholder="Type a message" autocomplete="off">
    <button type="submit">Send</button>
  </form>
</div>

<script>
const chatDiv = document.getElementById('chatMessages');
chatDiv.scrollTop = chatDiv.scrollHeight;

setInterval(() => {
  fetch('chat_refresh.php?property_id=<?php echo (int) $property_id; ?>&with=<?php echo (int) $with_user; ?>')
    .then((res) => res.text())
    .then((data) => {
      chatDiv.innerHTML = data;
      chatDiv.scrollTop = chatDiv.scrollHeight;
    });
}, 3000);
</script>
</body>
</html>
