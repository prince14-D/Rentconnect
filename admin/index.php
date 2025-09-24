<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

/* -------------------------
   Fetch statistics
------------------------- */
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_landlords = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='landlord'")->fetch_assoc()['c'];
$total_renters = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='renter'")->fetch_assoc()['c'];

$total_properties = $conn->query("SELECT COUNT(*) as c FROM properties")->fetch_assoc()['c'];
$pending_properties = $conn->query("SELECT COUNT(*) as c FROM properties WHERE status='pending'")->fetch_assoc()['c'];
$approved_properties = $conn->query("SELECT COUNT(*) as c FROM properties WHERE status='approved'")->fetch_assoc()['c'];

$total_requests = $conn->query("SELECT COUNT(*) as c FROM requests")->fetch_assoc()['c'];
$pending_requests = $conn->query("SELECT COUNT(*) as c FROM requests WHERE status='pending'")->fetch_assoc()['c'];
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
.dashboard { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; padding:20px; }
.card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 4px 10px rgba(0,0,0,0.1); text-align:center; }
.card h2 { margin:0; font-size:2em; color:#2E7D32; }
.card p { margin:10px 0 0; color:#555; }
nav { background:white; padding:10px; display:flex; justify-content:center; gap:20px; border-bottom:1px solid #ddd; }
nav a { text-decoration:none; color:#2E7D32; font-weight:bold; }
</style>
</head>
<body>

<header>📊 Admin Dashboard</header>
<nav>
    <a href="index.php">Dashboard</a>
    <a href="manage_users.php">Manage Users</a>
    <a href="manage_properties.php">Manage Properties</a>
    <a href="manage_requests.php">Manage Requests</a>
    <a href="../logout.php" style="color:#e53935;">Logout</a>
</nav>

<div class="dashboard">
    <div class="card">
        <h2><?php echo $total_users; ?></h2>
        <p>Total Users</p>
    </div>
    <div class="card">
        <h2><?php echo $total_landlords; ?></h2>
        <p>Landlords</p>
    </div>
    <div class="card">
        <h2><?php echo $total_renters; ?></h2>
        <p>Renters</p>
    </div>
    <div class="card">
        <h2><?php echo $total_properties; ?></h2>
        <p>Total Properties</p>
    </div>
    <div class="card">
        <h2><?php echo $pending_properties; ?></h2>
        <p>Pending Properties</p>
    </div>
    <div class="card">
        <h2><?php echo $approved_properties; ?></h2>
        <p>Approved Properties</p>
    </div>
    <div class="card">
        <h2><?php echo $total_requests; ?></h2>
        <p>Total Rental Requests</p>
    </div>
    <div class="card">
        <h2><?php echo $pending_requests; ?></h2>
        <p>Pending Requests</p>
    </div>
</div>

</body>
</html>
