<?php
session_start();
include "../db.php";

// Ensure only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* -------------------------
   Handle Approve / Reject
------------------------- */
if (isset($_GET['approve'])) {
    $pid = intval($_GET['approve']);
    $stmt = $conn->prepare("UPDATE properties SET status='approved' WHERE id=?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    header("Location: manage_properties.php");
    exit;
}

if (isset($_GET['reject'])) {
    $pid = intval($_GET['reject']);
    $stmt = $conn->prepare("UPDATE properties SET status='rejected' WHERE id=?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    header("Location: manage_properties.php");
    exit;
}

/* -------------------------
   Handle Delete Property
------------------------- */
if (isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM properties WHERE id=?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    header("Location: manage_properties.php");
    exit;
}

/* -------------------------
   Fetch All Properties
------------------------- */
$sql = "
    SELECT p.id, p.title, p.location, p.price, p.status, p.created_at,
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
<title>Manage Properties - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
h2 { color:#2E7D32; }
a.back { color:#2E7D32; text-decoration:none; margin-bottom:15px; display:inline-block; }
table { border-collapse: collapse; width:100%; background:white; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
th, td { padding:12px; text-align:center; border-bottom:1px solid #ddd; }
th { background:#f9f9f9; }
.action-btn { padding:6px 12px; border:none; border-radius:5px; cursor:pointer; font-size:0.9em; text-decoration:none; margin:2px; display:inline-block; }
.approve { background:#4CAF50; color:white; }
.reject { background:#f57c00; color:white; }
.delete { background:#e53935; color:white; }
.status { font-weight:bold; }
.pending { color:#f57c00; }
.approved { color:#2E7D32; }
.rejected { color:#e53935; }
</style>
</head>
<body>

<a href="index.php" class="back">⬅ Back to Dashboard</a>
<h2>🏠 Manage Properties</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Location</th>
        <th>Price</th>
        <th>Landlord</th>
        <th>Status</th>
        <th>Created At</th>
        <th>Action</th>
    </tr>
    <?php while ($p = $properties->fetch_assoc()): ?>
    <tr>
        <td><?php echo $p['id']; ?></td>
        <td><?php echo htmlspecialchars($p['title']); ?></td>
        <td><?php echo htmlspecialchars($p['location']); ?></td>
        <td>$<?php echo number_format($p['price']); ?></td>
        <td><?php echo htmlspecialchars($p['landlord_name']); ?><br><small><?php echo htmlspecialchars($p['landlord_email']); ?></small></td>
        <td class="status <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></td>
        <td><?php echo $p['created_at']; ?></td>
        <td>
            <?php if ($p['status'] == 'pending'): ?>
                <a href="?approve=<?php echo $p['id']; ?>" class="action-btn approve">✅ Approve</a>
                <a href="?reject=<?php echo $p['id']; ?>" class="action-btn reject">❌ Reject</a>
            <?php endif; ?>
            <a href="?delete=<?php echo $p['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this property?')">🗑 Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
