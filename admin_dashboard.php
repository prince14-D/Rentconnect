<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Stats
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
$total_properties = $conn->query("SELECT COUNT(*) AS c FROM properties")->fetch_assoc()['c'];
$total_requests = $conn->query("SELECT COUNT(*) AS c FROM requests")->fetch_assoc()['c'];
$pending_requests = $conn->query("SELECT COUNT(*) AS c FROM requests WHERE status='pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - RentConnect</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; }
header { background:#2E7D32; color:white; padding:15px; text-align:center; font-size:1.3em; }
nav { background:#fff; display:flex; justify-content:space-around; padding:10px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
nav a { text-decoration:none; color:#2E7D32; font-weight:bold; }
.dashboard { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; padding:20px; }
.card { background:white; border-radius:10px; padding:20px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.card h2 { margin:0; color:#2E7D32; }
</style>
</head>
<body>
<header>👨‍💻 Admin Dashboard</header>

<nav>
    <a href="manage_users.php">Users</a>
    <a href="manage_properties.php">Properties</a>
    <a href="manage_requests.php">Requests</a>
    <a href="reports.php">Reports</a>
    <a href="settings.php">Settings</a>
    <a href="../logout.php" style="color:red;">Logout</a>
</nav>

<div class="dashboard">
    <div class="card"><h2><?php echo $total_users; ?></h2><p>Total Users</p></div>
    <div class="card"><h2><?php echo $total_properties; ?></h2><p>Total Properties</p></div>
    <div class="card"><h2><?php echo $total_requests; ?></h2><p>Total Requests</p></div>
    <div class="card"><h2><?php echo $pending_requests; ?></h2><p>Pending Requests</p></div>
</div>
</body>
</html>
