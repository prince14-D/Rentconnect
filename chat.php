<?php
session_start();
include "db.php";

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$other_id = isset($_GET['with']) ? intval($_GET['with']) : 0;

if (!$property_id || !$other_id) {
    echo "Invalid chat.";
    exit;
}

// Handle message send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg != "") {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, property_id, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $user_id, $other_id, $property_id, $msg);
        $stmt->execute();
    }
}

// Fetch property info
$stmt = $conn->prepare("SELECT title FROM properties WHERE id=?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

// Fetch messages
$stmt = $conn->prepare("
    SELECT m.*, u.name AS sender_name 
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.property_id=? AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $property_id, $user_id, $other_id, $other_id, $user_id);
$stmt->execute();
$messages = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chat - <?php echo htmlspecialchars($property['title']); ?></title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; }
    header { background: #4CAF50; color: white; padding: 15px; text-align: center; }
    .container { width: 90%; max-width: 700px; margin: 20px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);}
    .messages { max-height: 400px; overflow-y: auto; margin-bottom: 20px; }
    .msg { padding: 10px; margin: 8px 0; border-radius: 10px; max-width: 70%; }
    .me { background: #4CAF50; color: white; margin-left: auto; text-align: right; }
    .other { background: #ddd; margin-right: auto; }
    form { display: flex; gap: 10px; }
    input[type=text] { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
    button { padding: 10px 15px; background: #4CAF50; border: none; color: white; border-radius: 5px; cursor: pointer; }
</style>
</head>
<body>
<header>
    <h2>Chat about <?php echo htmlspecialchars($property['title']); ?></h2>
</header>

<div class="container">
    <div class="messages">
        <?php while($row = $messages->fetch_assoc()): ?>
            <div class="msg <?php echo $row['sender_id'] == $user_id ? 'me' : 'other'; ?>">
                <strong><?php echo htmlspecialchars($row['sender_name']); ?>:</strong><br>
                <?php echo nl2br(htmlspecialchars($row['message'])); ?><br>
                <small><?php echo $row['created_at']; ?></small>
            </div>
        <?php endwhile; ?>
    </div>

    <form method="post">
        <input type="text" name="message" placeholder="Type your message..." required>
        <button type="submit">Send</button>
    </form>
</div>
</body>
</html>
