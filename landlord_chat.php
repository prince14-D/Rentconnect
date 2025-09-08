<?php
session_start();
include "db.php";

// Ensure only landlords can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

// Handle new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['message'])) {
    $property_id = intval($_POST['property_id']);
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);

    if ($message !== "") {
        $stmt = $conn->prepare("INSERT INTO messages (property_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $property_id, $landlord_id, $receiver_id, $message);
        $stmt->execute();
    }

    header("Location: landlord_chat.php?property_id=$property_id&with=$receiver_id");
    exit;
}

// Get chat property and renter
$property_id = intval($_GET['property_id'] ?? 0);
$chat_with = intval($_GET['with'] ?? 0);

if ($property_id && $chat_with) {
    // Fetch property details
    $stmt = $conn->prepare("SELECT title FROM properties WHERE id=? AND owner_id=? LIMIT 1");
    $stmt->bind_param("ii", $property_id, $landlord_id);
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();

    if (!$property) die("Property not found or you don't own it.");

    // Fetch chat messages
    $sql = "SELECT m.*, u.name AS sender_name 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.property_id=? 
            AND ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
            ORDER BY m.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $property_id, $landlord_id, $chat_with, $chat_with, $landlord_id);
    $stmt->execute();
    $messages = $stmt->get_result();
} else {
    // Fetch all approved requests for this landlord
    $sql = "SELECT r.id AS request_id, r.user_id, r.property_id, r.status, p.title, u.name AS renter_name 
            FROM requests r
            JOIN properties p ON r.property_id = p.id
            JOIN users u ON r.user_id = u.id
            WHERE p.owner_id=? AND r.status='approved'
            ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $landlord_id);
    $stmt->execute();
    $approved_requests = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Landlord Chat - RentConnect</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:20px; }
        h2 { color:#2E7D32; }
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
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #ccc; padding:8px; text-align:left; }
        th { background:#f4f4f4; }
        .button { padding:6px 12px; border-radius:5px; text-decoration:none; background:#2196F3; color:white; }
    </style>
</head>
<body>
<a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>

<?php if(isset($property)): ?>
    <h2>Chat about "<?php echo htmlspecialchars($property['title']); ?>"</h2>
    <div class="chat-box">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
                <div class="msg <?php echo ($msg['sender_id'] == $landlord_id) ? 'me' : 'other'; ?>">
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
        <input type="hidden" name="property_id" value="<?php echo $property_id; ?>">
        <input type="hidden" name="receiver_id" value="<?php echo $chat_with; ?>">
        <button type="submit">Send</button>
    </form>
<?php else: ?>
    <h2>Approved Requests</h2>
    <?php if ($approved_requests->num_rows > 0): ?>
        <table>
            <tr>
                <th>Property</th>
                <th>Renter</th>
                <th>Action</th>
            </tr>
            <?php while ($row = $approved_requests->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo htmlspecialchars($row['renter_name']); ?></td>
                    <td>
                        <a href="landlord_chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['user_id']; ?>" class="button">💬 Chat</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No approved requests yet.</p>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
