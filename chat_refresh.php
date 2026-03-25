<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id'])) {
    exit("Access denied");
}

$user_id = $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? '');
$property_id = intval($_GET['property_id'] ?? 0);
$with_user = intval($_GET['with'] ?? 0);

if (!rc_mig_can_access_chat($conn, (int) $user_id, $role, $property_id, $with_user)) {
    exit('Access denied');
}

$messages = rc_mig_get_chat_messages($conn, $property_id, (int) $user_id, $with_user);

// Output messages
foreach ($messages as $row):
?>
    <article class="bubble <?= ($row['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
        <strong><?= htmlspecialchars($row['sender_name']); ?>:</strong>
        <?= htmlspecialchars($row['message']); ?>
        <small><?= htmlspecialchars($row['created_at']); ?></small>
    </article>
<?php endforeach; ?>
