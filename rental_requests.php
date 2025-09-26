<?php
session_start();
include "db.php";

// Ensure only landlords can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = '';

/* -------------------------
   HANDLE APPROVE / DECLINE
------------------------- */
if (isset($_GET['action'], $_GET['request_id'])) {
    $action = $_GET['action'];
    $request_id = intval($_GET['request_id']);

    if ($action === 'approve' || $action === 'decline') {
        $new_status = ($action === 'approve') ? 'approved' : 'declined';

        $stmt = $conn->prepare("
            UPDATE requests r
            JOIN properties p ON r.property_id = p.id
            SET r.status = ?
            WHERE r.id = ? AND p.owner_id = ? AND r.status = 'pending'
        ");
        $stmt->bind_param("sii", $new_status, $request_id, $landlord_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "✅ Request $new_status successfully.";
        } else {
            $message = "⚠️ Could not update request. Maybe it's already handled or not your property.";
        }
    }
}

/* -------------------------
   FETCH PROPERTIES & REQUESTS
------------------------- */
$sql = "
    SELECT 
        p.id AS property_id,
        p.title AS property_title,
        p.price,
        p.location,
        GROUP_CONCAT(CONCAT(u.name,'|',u.id,'|',r.status,'|',r.id) SEPARATOR '||') AS renters
    FROM properties p
    JOIN requests r ON r.property_id=p.id
    JOIN users u ON r.user_id=u.id
    WHERE p.owner_id = ?
    GROUP BY p.id
    ORDER BY p.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$results = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Landlord Requests - RentConnect</title>
<style>
body { font-family: Arial,sans-serif; background:#f4f6f9; margin:0; padding:20px; }
header { background:#2E7D32; color:white; text-align:center; padding:20px; font-size:1.5em; border-radius:10px; }
.container { display:grid; grid-template-columns: repeat(auto-fit,minmax(300px,1fr)); gap:20px; margin-top:20px; }
.card { background:white; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); padding:15px; transition:0.3s; }
.card:hover { transform:translateY(-5px); box-shadow:0 6px 18px rgba(0,0,0,0.15); }
.card h3 { margin:0 0 8px; font-size:1.2em; color:#2E7D32; }
.card p { margin:4px 0; color:#555; font-size:0.9em; }
.status { display:inline-block; padding:4px 10px; border-radius:6px; font-size:0.85em; color:white; margin-right:5px; }
.pending { background:orange; }
.approved { background:green; }
.declined { background:red; }
.renter-actions { margin-top:8px; display:flex; flex-wrap:wrap; gap:8px; }
.btn { padding:6px 12px; border-radius:6px; font-size:0.9em; text-decoration:none; color:white; text-align:center; }
.btn-approve { background:#4CAF50; }
.btn-decline { background:#f44336; }
.btn-chat { background:#2196F3; }
.message { text-align:center; font-weight:bold; margin-bottom:15px; color:green; }
a.back { display:block; margin:20px auto; text-align:center; color:#2E7D32; font-weight:bold; text-decoration:none; }
@media(max-width:500px){ .container { grid-template-columns:1fr; } }
</style>
</head>
<body>

<header>Requests for Your Properties</header>

<?php if($message): ?>
<div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="container">
<?php if($results->num_rows > 0): ?>
    <?php while($row = $results->fetch_assoc()): ?>
        <div class="card">
            <h3><?= htmlspecialchars($row['property_title']); ?></h3>
            <p>💲 $<?= number_format($row['price']); ?> | 📍 <?= htmlspecialchars($row['location']); ?></p>
            <h4>Renters:</h4>
            <?php 
                $renters = explode('||', $row['renters']);
                foreach($renters as $r){
                    list($r_name, $r_id, $r_status, $r_request_id) = explode('|', $r);
            ?>
                <div>
                    <span class="status <?= $r_status ?>"><?= ucfirst($r_status) ?></span>
                    <?= htmlspecialchars($r_name) ?>
                    <div class="renter-actions">
                        <?php if($r_status === 'pending'): ?>
                            <a href="?action=approve&request_id=<?= $r_request_id ?>" class="btn btn-approve">✅ Approve</a>
                            <a href="?action=decline&request_id=<?= $r_request_id ?>" class="btn btn-decline">❌ Decline</a>
                        <?php elseif($r_status === 'approved'): ?>
                            <a href="chat.php?property_id=<?= $row['property_id'] ?>&with=<?= $r_id ?>" class="btn btn-chat">💬 Chat</a>
                        <?php else: ?>
                            <span style="color:#f44336;">❌ Declined</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align:center; color:#555;">No requests found for your properties.</p>
<?php endif; ?>
</div>

<a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</body>
</html>
