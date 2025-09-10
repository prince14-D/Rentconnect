<?php
session_start();
include "../db.php";

// Ensure admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle block/unblock/delete
if (isset($_GET['block'])) {
    $id = intval($_GET['block']);
    $conn->query("UPDATE users SET status='blocked' WHERE id=$id");
    header("Location: manage_users.php");
    exit;
}
if (isset($_GET['unblock'])) {
    $id = intval($_GET['unblock']);
    $conn->query("UPDATE users SET status='active' WHERE id=$id");
    header("Location: manage_users.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id=$id");
    header("Location: manage_users.php");
    exit;
}

// Fetch all users
$users = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Admin</title>
    <style>
        body { font-family:Arial,sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { color:#2E7D32; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid #ddd; padding:10px; text-align:center; }
        th { background:#f0f0f0; }
        a.button { padding:5px 10px; border-radius:5px; text-decoration:none; color:white; font-size:0.9em; margin:2px; }
        .block { background:#f44336; }
        .unblock { background:#4CAF50; }
        .delete { background:#555; }
        .back { margin-top:20px; display:inline-block; color:#2196F3; text-decoration:none; }
    </style>
</head>
<body>
<h2>Manage Users</h2>
<?php if($users->num_rows > 0): ?>
<table>
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Role</th>
    <th>Status</th>
    <th>Created At</th>
    <th>Actions</th>
</tr>
<?php while($user = $users->fetch_assoc()): ?>
<tr>
    <td><?php echo $user['id']; ?></td>
    <td><?php echo htmlspecialchars($user['name']); ?></td>
    <td><?php echo htmlspecialchars($user['email']); ?></td>
    <td><?php echo ucfirst($user['role']); ?></td>
    <td><?php echo ucfirst($user['status'] ?? 'active'); ?></td>
    <td><?php echo $user['created_at']; ?></td>
    <td>
        <?php if($user['status'] !== 'blocked'): ?>
            <a href="?block=<?php echo $user['id']; ?>" class="button block">Block</a>
        <?php else: ?>
            <a href="?unblock=<?php echo $user['id']; ?>" class="button unblock">Unblock</a>
        <?php endif; ?>
        <a href="?delete=<?php echo $user['id']; ?>" class="button delete" onclick="return confirm('Delete this user?')">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No users found.</p>
<?php endif; ?>
<a href="admin_dashboard.php" class="back">⬅ Back to Dashboard</a>
</body>
</html>
