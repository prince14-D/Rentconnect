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
   FETCH AVAILABLE PROPERTIES (with search)
------------------------- */
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM properties WHERE title LIKE ? OR location LIKE ? OR price LIKE ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$like = "%$search%";
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$properties = $stmt->get_result();

/* -------------------------
   FETCH RENTER REQUESTS
------------------------- */
$sql = "
    SELECT r.id AS request_id, r.status, r.created_at,
           p.id AS property_id, p.title, p.price, p.location, p.contact, p.owner_id AS landlord_id,
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
        
        /* Cards */
        .property-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; }
        .property-card { border:1px solid #ddd; border-radius:10px; overflow:hidden; background:white; box-shadow:0 2px 6px rgba(0,0,0,0.1); transition:0.3s; }
        .property-card:hover { transform: scale(1.03); }
        .property-card img { width:100%; height:180px; object-fit:cover; }
        .property-card .content { padding:15px; }
        .property-card h4 { margin:0; color:#2E7D32; }
        .property-card p { margin:5px 0; color:#555; font-size:0.9em; }
        .button { display:inline-block; padding:8px 14px; border-radius:6px; text-decoration:none; font-size:0.85em; margin-top:8px; }
        .request { background: #4CAF50; color: white; }
        .cancel { background: #f44336; color: white; }
        .chat { background: #2196F3; color: white; }

        /* Search Bar */
        .search-bar { text-align:center; margin-bottom:20px; }
        .search-bar input { padding:10px; width:60%; max-width:400px; border-radius:6px; border:1px solid #ccc; }
        .search-bar button { padding:10px 15px; border:none; border-radius:6px; background:#2E7D32; color:white; cursor:pointer; }

        /* Modal */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; }
        .modal-content { background:white; padding:20px; border-radius:10px; width:80%; max-width:600px; }
        .modal-content img { width:100%; height:250px; object-fit:cover; border-radius:8px; margin-bottom:15px; }
        .close { float:right; cursor:pointer; font-size:18px; color:#f44336; }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (Renter)</h2>
    <a href="logout.php" class="logout">Logout</a>

    <!-- Property Search -->
    <div class="section search-bar">
        <form method="get">
            <input type="text" name="search" placeholder="Search by title, location, or price..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <!-- Property Listings -->
    <div class="section">
        <h3>🏠 Available Properties</h3>
        <div class="property-grid">
            <?php if ($properties->num_rows > 0): ?>
                <?php while ($row = $properties->fetch_assoc()): ?>
                    <div class="property-card">
                        <?php
                        $img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=? LIMIT 1");
                        $img_stmt->bind_param("i", $row['id']);
                        $img_stmt->execute();
                        $img = $img_stmt->get_result()->fetch_assoc();
                        ?>
                        <?php if ($img): ?>
                            <img src="display_image.php?img_id=<?php echo $img['id']; ?>" alt="Property">
                        <?php else: ?>
                            <img src="images/no-image.png" alt="No Image">
                        <?php endif; ?>
                        
                        <div class="content">
                            <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                            <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                            <p>💲 $<?php echo number_format($row['price']); ?></p>
                            <a href="#" class="button request" onclick="openModal(<?php echo $row['id']; ?>)">View Details</a>
                            <a href="?request_property=<?php echo $row['id']; ?>" class="button request">Request to Rent</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No properties listed yet.</p>
            <?php endif; ?>
        </div>
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

    <!-- Property Detail Modal -->
    <div id="propertyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">✖</span>
            <div id="modal-body">Loading...</div>
        </div>
    </div>

    <script>
        function openModal(id) {
            fetch("property_detail.php?id=" + id)
            .then(res => res.text())
            .then(data => {
                document.getElementById("modal-body").innerHTML = data;
                document.getElementById("propertyModal").style.display = "flex";
            });
        }
        function closeModal() {
            document.getElementById("propertyModal").style.display = "none";
        }
    </script>
</body>
</html>
