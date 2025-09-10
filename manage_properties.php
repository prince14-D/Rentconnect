<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Approve / Decline / Delete
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $conn->query("UPDATE properties SET status='approved' WHERE id=$id");
    header("Location: manage_properties.php");
    exit;
}
if (isset($_GET['decline'])) {
    $id = intval($_GET['decline']);
    $conn->query("UPDATE properties SET status='declined' WHERE id=$id");
    header("Location: manage_properties.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM properties WHERE id=$id");
    header("Location: manage_properties.php");
    exit;
}

// Fetch properties
$properties = $conn->query("
    SELECT p.id, p.title, p.location, p.price, p.status, u.name AS owner
    FROM properties p
    JOIN users u ON p.owner_id = u.id
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Properties - Admin</title>
<style>
body { font-family:Arial,sans-serif; background:#f4f6f9; margin:0; padding:20px; }
h2 { color:#2E7D32; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { border:1px solid #ddd; padding:10px; text-align:center; }
th { background:#f0f0f0; }
a.button { padding:5px 10px; border-radius:5px; text-decoration:none; color:white; font-size:0.9em; margin:2px; }
.approve { background:#4CAF50; }
.decline { background:#f44336; }
.delete { background:#555; }
.back { margin-top:20px; display:inline-block; color:#2196F3; text-decoration:none; }
</style>
</head>
<body>
<h2>Manage Properties</h2>
<?php if($properties->num_rows > 0): ?>
<table>
<tr>
<th>ID</th>
<th>Title</th>
<th>Location</th>
<th>Price</th>
<th>Owner</th>
<th>Status</th>
<th>Actions</th>
</tr>
<?php while($prop = $properties->fetch_assoc()): ?>
<tr>
<td><?php echo $prop['id']; ?></td>
<td><?php echo htmlspecialchars($prop['title']); ?></td>
<td><?php echo htmlspecialchars($prop['location']); ?></td>
<td>$<?php echo number_format($prop['price']); ?></td>
<td><?php echo htmlspecialchars($prop['owner']); ?></td>
<td><?php echo ucfirst($prop['status']); ?></td>
<td>
<?php if($prop['status'] == 'pending'): ?>
<a href="?approve=<?php echo $prop['id']; ?>" class="button approve">Approve</a>
<a href="?decline=<?php echo $prop['id']; ?>" class="button decline">Decline</a>
<?php endif; ?>
<a href="?delete=<?php echo $prop['id']; ?>" class="button delete" onclick="return confirm('Delete this property?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No properties found.</p>
<?php endif; ?>
<a href="admin_dashboard.php" class="back">⬅ Back to Dashboard</a>
</body>
</html>
