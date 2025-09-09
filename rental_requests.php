<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

/* -------------------------
   APPROVE / DECLINE REQUEST
------------------------- */
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $action = $_GET['action'];
    $req_id = intval($_GET['request_id']);
    $new_status = ($action === "approve") ? "approved" : "declined";

    $stmt = $conn->prepare("UPDATE requests SET status=? WHERE id=?");
    $stmt->bind_param("si", $new_status, $req_id);
    if ($stmt->execute()) $message = "✅ Request updated!";
    else $message = "❌ Could not update request.";
}

/* -------------------------
   FETCH RENTAL REQUESTS
------------------------- */
$sql = "
    SELECT r.id AS request_id, r.status, r.created_at,
           u.id AS renter_id, u.name AS renter_name, u.email AS renter_email,
           p.id AS property_id, p.title, p.price
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN properties p ON r.property_id = p.id
    WHERE p.owner_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rental Requests - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f7f9f7; }
header { background:#2e7d32; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:900px; margin:30px auto; padding:0 15px; display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; }
.card { background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); padding:15px; transition:0.3s; }
.card:hover { transform:translateY(-5px); box-shadow:0 6px 14px rgba(0,0,0,0.15); }
.card h3 { margin:0 0 8px; color:#2e7d32; font-size:1.2em; }
.card p { margin:4px 0; color:#555; font-size:0.9em; }
.status { padding:5px 10px; border-radius:6px; font-size:0.85em; color:white; }
.pending { background:orange; }
.approved { background:green; }
.declined { background:red; }
.card-actions { margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; }
.btn { padding:8px 12px; border-radius:6px; font-size:0.9em; text-decoration:none; color:white; }
.btn-approve { background:#4CAF50; }
.btn-decline { background:#f44336; }
.btn-message { background:#2196F3; }
.message { text-align:center; color:green; font-weight:bold; margin-bottom:15px; }
a.back { display:block; margin:20px auto; text-align:center; color:#2e7d32; text-decoration:none; font-weight:bold; }
@media(max-width:500px){ .container { grid-template-columns:1fr; } }
</style>
</head>
<body>

<header>Rental Requests</header>
<?php if($message) echo "<p class='message'>$message</p>"; ?>

<div class="container">
<?php if ($requests->num_rows > 0): ?>
    <?php while ($row = $requests->fetch_assoc()): ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($row['title']); ?> - $<?php echo $row['price']; ?></h3>
            <p>👤 <?php echo htmlspecialchars($row['renter_name']); ?> | ✉️ <?php echo htmlspecialchars($row['renter_email']); ?></p>
            <p>Status: <span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></p>
            <div class="card-actions">
                <?php if ($row['status'] == 'pending'): ?>
                    <a href="rental_requests.php?action=approve&request_id=<?php echo $row['request_id']; ?>" class="btn btn-approve">✅ Approve</a>
                    <a href="rental_requests.php?action=decline&request_id=<?php echo $row['request_id']; ?>" class="btn btn-decline">❌ Decline</a>
                <?php elseif ($row['status'] == 'approved'): ?>
                    <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['renter_id']; ?>" class="btn btn-message">💬 Message Renter</a>
                <?php else: ?>
                    <span style="color:#777;">No actions available</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align:center; color:#555;">No rental requests yet.</p>
<?php endif; ?>
</div>

<a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>

</body>
</html>
