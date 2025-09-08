<?php
session_start();
include "db.php";

// Ensure only renters can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];

/* -------------------------
   HANDLE REQUEST SUBMISSION
------------------------- */
if (isset($_GET['request_property'])) {
    $property_id = intval($_GET['request_property']);

    // prevent duplicate request
    $check = $conn->prepare("SELECT id FROM requests WHERE user_id=? AND property_id=? LIMIT 1");
    $check->bind_param("ii", $renter_id, $property_id);
    $check->execute();
    $check_res = $check->get_result();

    if ($check_res->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO requests (user_id, property_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param("ii", $renter_id, $property_id);
        $stmt->execute();
    }
    header("Location: renter_dashboard.php");
    exit;
}

/* -------------------------
   HANDLE CANCEL REQUEST
------------------------- */
if (isset($_GET['cancel_request'])) {
    $request_id = intval($_GET['cancel_request']);
    $stmt = $conn->prepare("DELETE FROM requests WHERE id=? AND user_id=? AND status='pending'");
    $stmt->bind_param("ii", $request_id, $renter_id);
    $stmt->execute();
    header("Location: renter_dashboard.php");
    exit;
}

/* -------------------------
   FETCH AVAILABLE PROPERTIES
------------------------- */
$stmt = $conn->prepare("SELECT * FROM properties ORDER BY created_at DESC");
$stmt->execute();
$properties = $stmt->get_result();

/* -------------------------
   FETCH RENTER REQUESTS
------------------------- */
$sql = "
    SELECT r.id AS request_id, r.status, r.created_at,
           p.id AS property_id, p.title, p.price, p.location, p.contact, p.photo_blob, p.owner_id AS landlord_id,
           u.name AS landlord_name, u.email AS landlord_email
    FROM requests r
    JOIN properties p ON r.property_id = p.id
    JOIN users u ON p.owner_id = u.id
    WHERE r.user_id=?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $renter_id);
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Renter Dashboard - RentConnect</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
        h2 { color:#2E7D32; }
        a.logout { float:right; margin-top:-40px; color:#f44336; text-decoration:none; font-weight:bold; }
        .section { margin-top:40px; background:white; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .section h3 { margin-top:0; color:#333; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align:center; }
        th { background: #f9f9f9; }
        .button { padding: 6px 12px; border-radius: 5px; text-decoration: none; font-size:0.9em; }
        .request { background: #4CAF50; color: white; }
        .cancel { background: #f44336; color: white; }
        .chat { background: #2196F3; color: white; }
        img { max-width: 120px; height:80px; object-fit:cover; border-radius:5px; }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (Renter)</h2>
    <a href="logout.php" class="logout">Logout</a>

    <!-- Property Listings -->
    <div class="section">
        <h3>🏠 Available Properties</h3>
        <?php if ($properties->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Photo</th>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Price</th>
                    <th>Contact</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $properties->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($row['photo_blob']): ?>
                                <img src="display_image.php?id=<?php echo $row['id']; ?>" alt="Property">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td>$<?php echo number_format($row['price']); ?></td>
                        <td><?php echo htmlspecialchars($row['contact']); ?></td>
                        <td>
                            <a href="?request_property=<?php echo $row['id']; ?>" class="button request">Request to Rent</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No properties listed yet.</p>
        <?php endif; ?>
    </div>

    <!-- My Requests -->
    <div class="section">
        <h3>📋 My Rental Requests</h3>
        <?php if ($requests->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Price</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td>$<?php echo number_format($row['price']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                                <a href="?cancel_request=<?php echo $row['request_id']; ?>" class="button cancel">❌ Cancel</a>
                            <?php elseif ($row['status'] == 'approved'): ?>
                                <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['landlord_id']; ?>" class="button chat">💬 Message Landlord</a>
                            <?php else: ?>
                                No action
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>You have not made any requests yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
