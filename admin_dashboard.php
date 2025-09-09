<?php
session_start();
include "../db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Handle approve/decline property
if (isset($_GET['approve_property'])) {
    $id = intval($_GET['approve_property']);
    $conn->query("UPDATE properties SET status='approved' WHERE id=$id");
    header("Location: admin_dashboard.php");
    exit;
}

if (isset($_GET['decline_property'])) {
    $id = intval($_GET['decline_property']);
    $conn->query("UPDATE properties SET status='declined' WHERE id=$id");
    header("Location: admin_dashboard.php");
    exit;
}

// Stats
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_properties = $conn->query("SELECT COUNT(*) AS c FROM properties")->fetch_assoc()['c'];
$total_requests = $conn->query("SELECT COUNT(*) AS c FROM requests")->fetch_assoc()['c'];
$pending_requests = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE status='pending'")->fetch_assoc()['c'];

// Pending properties
$pending_properties = $conn->query("
    SELECT p.id, p.title, p.location, p.price, u.name AS owner_name, p.created_at
    FROM properties p
    JOIN users u ON p.owner_id = u.id
    WHERE p.status='pending'
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; }
header { background:#2E7D32; color:white; padding:15px; text-align:center; font-size:1.3em; }
nav { background:#fff; display:flex; flex-wrap:wrap; justify-content:space-around; padding:10px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
nav a { text-decoration:none; color:#2E7D32; font-weight:bold; margin:5px; }
.dashboard { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px; padding:20px; }
.card { background:white; border-radius:10px; padding:20px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.card h2 { margin:0; color:#2E7D32; font-size:2em; }
.card p { margin:5px 0 0 0; color:#555; }
table { width:100%; border-collapse:collapse; margin:20px 0; background:white; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
th, td { padding:10px; border-bottom:1px solid #eee; text-align:center; }
th { background:#f0f0f0; color:#333; }
a.button { padding:6px 12px; border-radius:5px; text-decoration:none; color:white; font-size:0.9em; margin:2px; display:inline-block; }
.approve { background:#4CAF50; }
.decline { background:#f44336; }
.logout { float:right; margin-top:-40px; color:#f44336; text-decoration:none; font-weight:bold; }
@media(max-width:600px){ .dashboard { grid-template-columns:1fr; } }
</style>
</head>
<body>
<header>
👨‍💻 Admin Dashboard
<a href="../logout.php" class="logout">Logout</a>
</header>

<nav>
    <a href="admin_dashboard.php">Dashboard</a>
    <a href="manage_users.php">Users</a>
    <a href="manage_properties.php">Properties</a>
    <a href="manage_requests.php">Requests</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>
</nav>

<div class="dashboard">
    <div class="card"><h2><?php echo $total_users; ?></h2><p>Total Users</p></div>
    <div class="card"><h2><?php echo $total_properties; ?></h2><p>Total Properties</p></div>
    <div class="card"><h2><?php echo $total_requests; ?></h2><p>Total Requests</p></div>
    <div class="card"><h2><?php echo $pending_requests; ?></h2><p>Pending Requests</p></div>
</div>

<h3 style="padding:0 20px;">Pending Properties</h3>
<?php if ($pending_properties->num_rows > 0): ?>
<table>
<tr>
    <th>Title</th>
    <th>Location</th>
    <th>Price</th>
    <th>Landlord</th>
    <th>Uploaded At</th>
    <th>Actions</th>
</tr>
<?php while ($row = $pending_properties->fetch_assoc()): ?>
<tr>
    <td><?php echo htmlspecialchars($row['title']); ?></td>
    <td><?php echo htmlspecialchars($row['location']); ?></td>
    <td>$<?php echo number_format($row['price']); ?></td>
    <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
    <td><?php echo $row['created_at']; ?></td>
    <td>
        <a href="?approve_property=<?php echo $row['id']; ?>" class="button approve">✅ Approve</a>
        <a href="?decline_property=<?php echo $row['id']; ?>" class="button decline">❌ Decline</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p style="padding:0 20px;">No pending properties.</p>
<?php endif; ?>
</body>
</html>
