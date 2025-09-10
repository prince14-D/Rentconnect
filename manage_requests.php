<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch all requests
$requests = $conn->query("
SELECT r.id AS request_id, r.status, r.created_at,
       p.title AS property_title, p.price AS property_price,
       u.name AS renter_name, u.email AS renter_email
FROM requests r
JOIN users u ON r.user_id = u.id
JOIN properties p ON r.property_id = p.id
ORDER BY r.created_at DESC
");
?>
<!DOCTYPE html>
<html>
<head>
<title>Manage Requests - Admin</title>
<style>
body { font-family:Arial,sans-serif; background:#f4f6f9; margin:0; padding:20px; }
h2 { color:#2E7D32; }
table { width:100%; border-collapse:collapse; margin-top:20px; }
th, td { border:1px solid #ddd; padding:10px; text-align:center; }
th { background:#f0f0f0; }
.back { margin-top:20px; display:inline-block; color:#2196F3; text-decoration:none; }
</style>
</head>
<body>
<h2>All Rental Requests</h2>
<?php if($requests->num_rows > 0): ?>
<table>
<tr>
<th>ID</th>
<th>Renter</th>
<th>Email</th>
<th>Property</th>
<th>Price</th>
<th>Status</th>
<th>Requested At</th>
</tr>
<?php while($req = $requests->fetch_assoc()): ?>
<tr>
<td><?php echo $req['request_id']; ?></td>
<td><?php echo htmlspecialchars($req['renter_name']); ?></td>
<td><?php echo htmlspecialchars($req['renter_email']); ?></td>
<td><?php echo htmlspecialchars($req['property_title']); ?></td>
<td>$<?php echo number_format($req['property_price']); ?></td>
<td><?php echo ucfirst($req['status']); ?></td>
<td><?php echo $req['created_at']; ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No rental requests found.</p>
<?php endif; ?>
<a href="admin_dashboard.php" class="back">⬅ Back to Dashboard</a>
</body>
</html>
