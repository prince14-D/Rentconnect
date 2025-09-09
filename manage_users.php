<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle suspend/activate
if (isset($_GET['toggle'])) {
    $uid = intval($_GET['toggle']);
    $user = $conn->query("SELECT active FROM users WHERE id=$uid")->fetch_assoc();
    $new_status = $user['active'] ? 0 : 1;
    $conn->query("UPDATE users SET active=$new_status WHERE id=$uid");
    header("Location: manage_users.php");
    exit;
}

// Handle role change
if (isset($_POST['change_role'])) {
    $uid = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    $conn->query("UPDATE users SET role='$new_role' WHERE id=$uid");
    header("Location: manage_users.php");
    exit;
}

// Fetch all users
$users = $conn->query("SELECT id, name, email, role, active FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Users - RentConnect Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; }
header { background:#2E7D32; color:white; padding:15px; text-align:center; font-size:1.3em; }
table { width:90%; margin:20px auto; border-collapse:collapse; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
th, td { padding:12px; text-align:center; border-bottom:1px solid #ddd; }
th { background:#2E7D32; color:white; }
a.btn { padding:6px 12px; text-decoration:none; border-radius:6px; font-size:0.9em; }
.btn-suspend { background:#e53935; color:white; }
.btn-activate { background:#43a047; color:white; }
form { display:inline; }
select { padding:4px; border-radius:6px; }
button { padding:5px 10px; background:#2E7D32; color:white; border:none; border-radius:6px; cursor:pointer; }
</style>
</head>
<body>
<header>👥 Manage Users</header>

<table>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php while($u = $users->fetch_assoc()): ?>
    <tr>
        <td><?php echo $u['id']; ?></td>
        <td><?php echo htmlspecialchars($u['name']); ?></td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td>
            <form method="post" style="margin:0;">
                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                <select name="role">
                    <option value="renter" <?php if($u['role']=='renter') echo 'selected'; ?>>Renter</option>
                    <option value="landlord" <?php if($u['role']=='landlord') echo 'selected'; ?>>Landlord</option>
                    <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>Admin</option>
                </select>
                <button type="submit" name="change_role">Save</button>
            </form>
        </td>
        <td><?php echo $u['active'] ? '✅ Active' : '❌ Suspended'; ?></td>
        <td>
            <a class="btn <?php echo $u['active'] ? 'btn-suspend':'btn-activate'; ?>" 
               href="?toggle=<?php echo $u['id']; ?>">
               <?php echo $u['active'] ? 'Suspend' : 'Activate'; ?>
            </a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
