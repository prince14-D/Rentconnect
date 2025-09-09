<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* -------------------------
   Handle Approve / Reject
------------------------- */
if (isset($_GET['approve'])) {
    $pid = intval($_GET['approve']);
    $conn->query("UPDATE properties SET status='approved' WHERE id=$pid");
    header("Location: manage_properties.php");
    exit;
}
if (isset($_GET['reject'])) {
    $pid = intval($_GET['reject']);
    $conn->query("UPDATE properties SET status='rejected' WHERE id=$pid");
    header("Location: manage_properties.php");
    exit;
}

/* -------------------------
   Handle Delete Property
------------------------- */
if (isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    $conn->query("DELETE FROM properties WHERE id=$pid");
    header("Location: manage_properties.php");
    exit;
}

/* -------------------------
   Fetch all properties
------------------------- */
$sql = "
    SELECT p.id, p.title, p.price, p.location, p.status, p.created_at,
           u.name AS landlord_name, u.email AS landlord_email
    FROM properties p
    JOIN users u ON p.owner_id = u.id
    ORDER BY p.created_at DESC
";
$properties = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Properties - RentConnect Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; }
header { background:#2E7D32; color:white; padding:15px; text-align:center; font-size:1.3em; }
table { width:95%; margin:20px auto; border-collapse:collapse; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
th, td { padding:12px; text-align:center; border-bottom:1px solid #ddd; }
th { background:#2E7D32; color:white; }
a.btn { padding:6px 12px; text-decoration:none; border-radius:6px; font-size:0.9em; margin:2px; display:inline-block; }
.btn-approve { background:#43a047; color:white; }
.btn-reject { background:#e53935; color:white; }
.btn-delete { background:#b71c1c; color:white; }
</style>
</head>
<body>
<header>🏠 Manage Properties</header>

<table>
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Location</th>
        <th>Price</th>
        <th>Status</th>
        <th>Landlord</th>
        <th>Actions</th>
    </tr>
    <?php while($p = $properties->fetch_assoc()): ?>
    <tr>
        <td><?php echo $p['id']; ?></td>
        <td><?php echo htmlspecialchars($p['title']); ?></td>
        <td><?php echo htmlspecialchars($p['location']); ?></td>
        <td>$<?php echo number_format($p['price']); ?></td>
        <td>
            <?php if ($p['status'] == 'approved'): ?>
                ✅ Approved
            <?php elseif ($p['status'] == 'rejected'): ?>
                ❌ Rejected
            <?php else: ?>
                ⏳ Pending
            <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($p['landlord_name']); ?><br>
            <small><?php echo htmlspecialchars($p['landlord_email']); ?></small>
        </td>
        <td>
            <?php if ($p['status'] != 'approved'): ?>
                <a href="?approve=<?php echo $p['id']; ?>" class="btn btn-approve">Approve</a>
            <?php endif; ?>
            <?php if ($p['status'] != 'rejected'): ?>
                <a href="?reject=<?php echo $p['id']; ?>" class="btn btn-reject">Reject</a>
            <?php endif; ?>
            <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this property?')">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
