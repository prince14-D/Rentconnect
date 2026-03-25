<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

if (!isset($_GET['property_id']) || !isset($_GET['with'])) {
    echo "<p>Invalid chat request.</p>";
    exit;
}

$property_id = intval($_GET['property_id']);
$renter_id = intval($_GET['with']);

$sql = "SELECT * FROM properties WHERE id=? AND landlord_id=? AND status='approved' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $property_id, $landlord_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
if (!$property) {
    echo "<p>Property not found.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== '') {
        $stmt = $conn->prepare("INSERT INTO messages (property_id,sender_id,receiver_id,message,created_at) VALUES (?,?,?,?,NOW())");
        $stmt->bind_param("iiis", $property_id, $landlord_id, $renter_id, $msg);
        $stmt->execute();
    }
    exit;
}

$update = $conn->prepare("UPDATE messages SET read_status=1 WHERE sender_id=? AND receiver_id=? AND property_id=?");
$update->bind_param("iii", $renter_id, $landlord_id, $property_id);
$update->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Landlord Chat - <?php echo htmlspecialchars($property['title']); ?></title>
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
.header {
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  color: #fff;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.header img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}
.header h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.02rem;
  letter-spacing: -0.01em;
}
.messages {
  overflow-y: auto;
  padding: 14px;
  background: #f3f7fa;
}
.bubble {
  max-width: min(72%, 520px);
  border-radius: 14px;
  padding: 9px 11px;
  font-size: 0.9rem;
  line-height: 1.45;
  margin-bottom: 10px;
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
.empty-msg {
  text-align: center;
  color: var(--muted);
}
.input {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  padding: 10px;
  border-top: 1px solid var(--line);
  background: #fff;
}
.input input {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 11px 12px;
  font: inherit;
}
.input button {
  border: none;
  border-radius: 10px;
  padding: 11px 14px;
  color: #fff;
  font-weight: 700;
  background: linear-gradient(140deg, var(--brand), var(--brand-deep));
  cursor: pointer;
}
</style>
</head>
<body>
<div class="chat">
  <header class="header">
    <img src="fetch_profile.php?user_id=<?php echo (int) $renter_id; ?>" alt="Profile">
    <h1><?php echo htmlspecialchars($property['title']); ?> - <?php echo htmlspecialchars($_GET['with_name'] ?? 'Renter'); ?></h1>
  </header>

  <section id="messages" class="messages">Loading...</section>

  <form id="chatForm" class="input">
    <input type="text" id="messageInput" placeholder="Type a message" required>
    <button type="submit">Send</button>
  </form>
</div>

<script>
function loadMessages() {
  fetch('fetch_messages.php?property_id=<?php echo (int) $property_id; ?>&with=<?php echo (int) $renter_id; ?>')
    .then((res) => res.text())
    .then((data) => {
      const msgBox = document.getElementById('messages');
      const atBottom = msgBox.scrollTop + msgBox.clientHeight >= msgBox.scrollHeight - 50;
      msgBox.innerHTML = data;
      if (atBottom) {
        msgBox.scrollTop = msgBox.scrollHeight;
      }
    });
}

document.getElementById('chatForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const formData = new FormData();
  formData.append('message', document.getElementById('messageInput').value);
  fetch('landlord_chat.php?property_id=<?php echo (int) $property_id; ?>&with=<?php echo (int) $renter_id; ?>', {
    method: 'POST',
    body: formData
  }).then(() => {
    document.getElementById('messageInput').value = '';
    loadMessages();
  });
});

setInterval(loadMessages, 3000);
loadMessages();
</script>
</body>
</html>
