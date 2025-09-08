<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

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
           p.id AS property_id, p.title, p.price, p.photo
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
        body { margin:0; font-family: Arial, sans-serif; background:#f4f6f9; display:flex; }
        
        /* Sidebar */
        .sidebar { width:250px; background:#2E7D32; color:white; height:100vh; position:sticky; top:0; display:flex; flex-direction:column; }
        .sidebar h2 { text-align:center; padding:20px; margin:0; border-bottom:1px solid rgba(255,255,255,0.2); }
        .sidebar a { padding:15px 20px; text-decoration:none; color:white; display:block; transition:0.3s; }
        .sidebar a:hover { background:#1B5E20; }
        .logout { margin-top:auto; background:#f44336; text-align:center; }
        
        /* Main Content */
        .main { flex:1; padding:20px; }
        h2 { color:#2E7D32; margin-bottom:15px; }

        /* Buttons */
        .btn { padding:8px 14px; border-radius:6px; text-decoration:none; font-size:0.9em; }
        .btn-upload { background:#4CAF50; color:white; }
        .btn-edit { background:#FF9800; color:white; }
        .btn-delete { background:#f44336; color:white; }
        .btn-approve { background:green; color:white; }
        .btn-reject { background:red; color:white; }
        .btn-message { background:#2196F3; color:white; }

        /* Card Layout */
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; }
        .card { background:white; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); overflow:hidden; }
        .card img { width:100%; height:160px; object-fit:cover; }
        .card-body { padding:15px; }
        .card-body h3 { margin:0 0 8px; color:#2E7D32; font-size:1.2em; }
        .card-body p { margin:4px 0; font-size:0.9em; }
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
    <h2>🏠 RentConnect</h2>
    <a href="#properties">📋 My Properties</a>
    <a href="#requests">📨 Rental Requests</a>
    <a href="add_property.php">➕ Upload Property</a>
    <a href="#" class="logout">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main">
    
    <!-- Properties -->
    <h2 id="properties">Your Properties</h2>
    <?php if ($properties->num_rows > 0): ?>
        <div class="grid">
            <?php while ($row = $properties->fetch_assoc()): ?>
                <div class="card">
                    <img src="uploads/<?php echo htmlspecialchars($row['photo']); ?>" alt="Property">
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
