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
   DELETE PROPERTY
------------------------- */
if (isset($_GET['delete_property'])) {
    $prop_id = intval($_GET['delete_property']);
    $stmt = $conn->prepare("DELETE FROM properties WHERE id=? AND owner_id=?");
    $stmt->bind_param("ii", $prop_id, $landlord_id);
    if ($stmt->execute()) {
        $message = "✅ Property deleted.";
    } else {
        $message = "❌ Could not delete property.";
    }
}

/* -------------------------
   APPROVE / DECLINE REQUEST
------------------------- */
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $action = $_GET['action'];
    $req_id = intval($_GET['request_id']);
    $new_status = ($action === "approve") ? "approved" : "declined";

    $stmt = $conn->prepare("UPDATE requests SET status=? WHERE id=?");
    $stmt->bind_param("si", $new_status, $req_id);
    $stmt->execute();
}

/* -------------------------
   FETCH PROPERTIES
------------------------- */
$stmt = $conn->prepare("SELECT * FROM properties WHERE owner_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$properties = $stmt->get_result();

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
    <title>Landlord Dashboard - RentConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; font-family:Arial, sans-serif; background:#f4f6f9; display:flex; }
        a { text-decoration:none; }

        /* Sidebar */
        .sidebar {
            width:250px; background:#2E7D32; color:white; 
            height:100vh; position:sticky; top:0; 
            display:flex; flex-direction:column; transition:all 0.3s;
        }
        .sidebar h2 { text-align:center; padding:20px; margin:0; border-bottom:1px solid rgba(255,255,255,0.2); }
        .sidebar h2 a { color:white; }
        .sidebar a { padding:15px 20px; color:white; display:block; transition:0.3s; }
        .sidebar a:hover { background:#1B5E20; }
        .logout { margin-top:auto; background:#f44336; text-align:center; }

        /* Responsive sidebar */
        @media(max-width:768px) {
            .sidebar { width:70px; }
            .sidebar a { text-align:center; padding:15px 0; font-size:0.8em; }
            .sidebar h2 { font-size:1em; padding:10px; }
        }

        /* Main */
        .main { flex:1; padding:20px; }
        h2 { color:#2E7D32; margin-bottom:15px; }
        .message { color:red; margin-bottom:15px; }

        /* Buttons */
        .btn { padding:8px 14px; border-radius:6px; font-size:0.9em; display:inline-block; }
        .btn-upload { background:#4CAF50; color:white; }
        .btn-edit { background:#FF9800; color:white; }
        .btn-delete { background:#f44336; color:white; }
        .btn-approve { background:green; color:white; }
        .btn-reject { background:red; color:white; }
        .btn-message { background:#2196F3; color:white; }

        /* Cards */
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; }
        .card { background:white; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); overflow:hidden; transition:0.3s; }
        .card:hover { transform:translateY(-4px); }
        .card img { width:100%; height:180px; object-fit:cover; }
        .card-body { padding:15px; }
        .card-body h3 { margin:0 0 8px; color:#2E7D32; font-size:1.2em; }
        .card-body p { margin:4px 0; font-size:0.9em; color:#555; }
        .card-actions { display:flex; justify-content:space-between; margin-top:10px; }

        /* Requests */
        .request-card { background:white; padding:15px; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
        .request-info strong { color:#2E7D32; }
        .status { padding:5px 10px; border-radius:5px; font-size:0.85em; color:white; }
        .pending { background:orange; }
        .approved { background:green; }
        .declined { background:red; }
        .request-actions { margin-top:10px; display:flex; gap:10px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2><a href="index.php">🏠 RentConnect</a></h2>
    <a href="#properties">📋 My Properties</a>
    <a href="#requests">📨 Rental Requests</a>
    <a href="add_property.php">➕ Upload Property</a>
    <a href="logout.php" class="logout">🚪 Logout</a>
</div>

<!-- Main -->
<div class="main">
    <?php if($message): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <!-- Properties -->
    <h2 id="properties">Your Properties</h2>
    <?php if ($properties->num_rows > 0): ?>
        <div class="grid">
            <?php while ($row = $properties->fetch_assoc()): ?>
                <div class="card">
                    <img src="get_image.php?id=<?php echo $row['id']; ?>" alt="Property">
                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                        <p>💲 $<?php echo $row['price']; ?></p>
                        <p>📞 <?php echo htmlspecialchars($row['contact']); ?></p>
                        <div class="card-actions">
                            <a href="edit_property.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                            <a href="landlord_dashboard.php?delete_property=<?php echo $row['id']; ?>" onclick="return confirm('Delete this property?')" class="btn btn-delete">🗑️ Delete</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No properties uploaded yet.</p>
    <?php endif; ?>

    <!-- Rental Requests -->
    <h2 id="requests" style="margin-top:40px;">Rental Requests</h2>
    <?php if ($requests->num_rows > 0): ?>
        <div class="grid">
            <?php while ($row = $requests->fetch_assoc()): ?>
                <div class="request-card">
                    <div class="request-info">
                        <strong><?php echo htmlspecialchars($row['title']); ?></strong> - $<?php echo $row['price']; ?><br>
                        👤 <?php echo htmlspecialchars($row['renter_name']); ?> | ✉️ <?php echo htmlspecialchars($row['renter_email']); ?><br>
                        Status: <span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                    </div>
                    <div class="request-actions">
                        <?php if ($row['status'] == 'pending'): ?>
                            <a href="landlord_dashboard.php?action=approve&request_id=<?php echo $row['request_id']; ?>" class="btn btn-approve">✅ Approve</a>
                            <a href="landlord_dashboard.php?action=decline&request_id=<?php echo $row['request_id']; ?>" class="btn btn-reject">❌ Reject</a>
                        <?php elseif ($row['status'] == 'approved'): ?>
                            <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['renter_id']; ?>" class="btn btn-message">💬 Message Renter</a>
                        <?php else: ?>
                            <span style="color:#777;">No action</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No rental requests yet.</p>
    <?php endif; ?>
</div>
</body>
</html>
