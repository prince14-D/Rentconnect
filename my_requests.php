<?php
session_start();
include "db.php";

// Ensure renter access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];

// Fetch renter’s requests
$sql = "
    SELECT r.id AS request_id, r.status, r.created_at,
           p.title, p.price, u.name AS landlord_name, u.email AS landlord_email
    FROM requests r
    JOIN properties p ON r.property_id = p.id
    JOIN users u ON p.owner_id = u.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $renter_id);
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Rental Requests - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f7f9f7; }
header { background:#2196F3; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:900px; margin:30px auto; padding:0 15px; display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; }
.card { background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); padding:15px; transition:0.3s; }
.card:hover { transform:translateY(-5px); box-shadow:0 6px 14px rgba(0,0,0,0.15); }
.card h3 { margin:0 0 8px; color:#2196F3; font-size:1.2em; }
.card p { margin:4px 0; color:#555; font-size:0.9em; }
.status { padding:5px 10px; border-radius:6px; font-size:0.85em; color:white; }
.pending { background:orange; }
.approved { background:green; }
.rejected { background:red; }
.card-actions { margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; }
.btn { padding:8px 12px; border-radius:6px; font-size:0.9em; text-decoration:none; color:white; }
.btn-message { background:#4CAF50; }
a.back { display:block; margin:20px auto; text-align:center; color:#2196F3; text-decoration:none; font-weight:bold; }
@media(max-width:500px){ .container { grid-template-columns:1fr; } }
</style>
</head>
<body>

<header>My Rental Requests</header>

<div class="container">
<?php if ($requests->num_rows > 0): ?>
    <?php while ($row = $requests->fetch_assoc()): ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($row['title']); ?> - $<?php echo $row['price']; ?></h3>
            <p>Landlord: <?php echo htmlspecialchars($row['landlord_name']); ?> | ✉️ <?php echo htmlspecialchars($row['landlord_email']); ?></p>
            <p>Status: <span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></p>
            <div class="card-actions">
                <?php if ($row['status'] == 'approved'): ?>
                    <a href="chat.php?property_id=<?php echo $row['request_id']; ?>&with=<?php echo $row['landlord_name']; ?>" class="btn btn-message">💬 Message Landlord</a>
                <?php else: ?>
                    <span style="color:#777;">No actions available</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align:center; color:#555;">You haven’t made any rental requests yet.</p>
<?php endif; ?>
</div>

<a href="renter_dashboard.php" class="back">⬅ Back to Dashboard</a>

</body>
</html>
