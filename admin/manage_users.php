<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* -------------------------
   Handle Delete User
------------------------- */
if (isset($_GET['delete'])) {
    $uid = intval($_GET['delete']);
    if ($uid != $_SESSION['user_id']) { // prevent admin from deleting self
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
    }
    header("Location: manage_users.php");
    exit;
}

/* -------------------------
   Handle Role Update
------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_update'])) {
    $uid = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
    $stmt->bind_param("si", $new_role, $uid);
    $stmt->execute();
    header("Location: manage_users.php");
    exit;
}

/* -------------------------
   Fetch All Users
------------------------- */
$users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
h2 { color:#2E7D32; }
a.back { color:#2E7D32; text-decoration:none; margin-bottom:15px; display:inline-block; }
table { border-collapse: collapse; width:100%; background:white; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
th, td { padding:12px; text-align:center; border-bottom:1px solid #ddd; }
th { background:#f9f9f9; }
form { display:inline; }
button, .btn { padding:6px 12px; border:none; border-radius:5px; cursor:pointer; font-size:0.9em; }
.delete { background:#e53935; color:white; }
.update { background:#2E7D32; color:white; }
</style>
</head>
<body>

<a href="index.php" class="back">⬅ Back to Dashboard</a>
<h2>👤 Manage Users</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Joined</th>
        <th>Action</th>
    </tr>
    <?php while ($u = $users->fetch_assoc()): ?>
    <tr>
        <td><?php echo $u['id']; ?></td>
        <td><?php echo htmlspecialchars($u['name']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td>
            <form method="post" style="display:flex; justify-content:center; gap:5px;">
                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                <select name="role">
                    <option value="admin" <?php if ($u['role']=='admin') echo "selected"; ?>>Admin</option>
                    <option value="landlord" <?php if ($u['role']=='landlord') echo "selected"; ?>>Landlord</option>
                    <option value="renter" <?php if ($u['role']=='renter') echo "selected"; ?>>Renter</option>
                </select>
                <button type="submit" name="role_update" class="update">Update</button>
            </form>
        </td>
        <td><?php echo $u['created_at']; ?></td>
        <td>
            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <a href="?delete=<?php echo $u['id']; ?>" class="btn delete" onclick="return confirm('Delete this user?')">Delete</a>
            <?php else: ?>
                <i>(You)</i>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
