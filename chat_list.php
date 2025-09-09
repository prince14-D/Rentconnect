<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

if (!isset($_GET['property_id']) || !isset($_GET['with'])) {
    die("Property or renter ID missing.");
}

$property_id = intval($_GET['property_id']);
$renter_id = intval($_GET['with']);
$message = "";

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg !== "") {
        $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $property_id, $landlord_id, $renter_id, $msg);
        $stmt->execute();
    }
}

// Fetch chat messages
$stmt = $conn->prepare("
    SELECT m.*, u.name AS sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.property_id=? AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $property_id, $landlord_id, $renter_id, $renter_id, $landlord_id);
$stmt->execute();
$messages = $stmt->get_result();

// Fetch renter name
$stmt2 = $conn->prepare("SELECT name FROM users WHERE id=?");
$stmt2->bind_param("i", $renter_id);
$stmt2->execute();
$renter_name = $stmt2->get_result()->fetch_assoc()['name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat with <?php echo htmlspecialchars($renter_name); ?></title>
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f7f9f7; display:flex; flex-direction:column; height:100vh; }
header { background:#2e7d32; color:white; padding:15px; text-align:center; font-size:1.2em; }
.chat-container { flex:1; overflow-y:auto; padding:15px; }
.message-box { display:flex; margin-bottom:10px; }
.message-box p { padding:10px 15px; border-radius:12px; max-width:70%; }
.sent { background:#2e7d32; color:white; margin-left:auto; }
.received { background:#e0e0e0; color:#000; margin-right:auto; }
form { display:flex; padding:10px; background:white; border-top:1px solid #ccc; }
form input { flex:1; padding:10px; border-radius:20px; border:1px solid #ccc; margin-right:10px; }
form button { padding:10px 20px; background:#2e7d32; color:white; border:none; border-radius:20px; cursor:pointer; }
form button:hover { background:#1b5e20; }
.back { display:block; text-align:center; margin:10px 0; color:#2e7d32; text-decoration:none; font-weight:bold; }
</style>
</head>
<body>

<header>Chat with <?php echo htmlspecialchars($renter_name); ?></header>

<div class="chat-container" id="chat-container">
    <?php while($row = $messages->fetch_assoc()): ?>
        <div class="message-box">
            <p class="<?php echo ($row['sender_id'] == $landlord_id) ? 'sent' : 'received'; ?>">
                <?php echo htmlspecialchars($row['message']); ?><br>
                <small style="font-size:0.7em; color:#555;"><?php echo date('d M H:i', strtotime($row['created_at'])); ?></small>
            </p>
        </div>
    <?php endwhile; ?>
</div>

<form method="post">
    <input type="text" name="message" placeholder="Type your message..." required>
    <button type="submit">Send</button>
</form>

<a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>

<script>
// Auto-scroll to bottom
const chatContainer = document.getElementById('chat-container');
chatContainer.scrollTop = chatContainer.scrollHeight;
</script>

</body>
</html>
