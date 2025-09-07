<?php
session_start();
include "db.php";

// Ensure only landlords can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

/* -------------------------
   PROPERTY UPLOAD
------------------------- */
if (isset($_POST['submit_property'])) {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $image_name = time() . '_' . basename($_FILES['photo']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO properties (title, location, price, contact, photo, owner_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdssi", $title, $location, $price, $contact, $image_name, $landlord_id);
            $stmt->execute();
            $message = "✅ Property uploaded successfully!";
        } else {
            $message = "❌ Failed to upload image.";
        }
    } else {
        $message = "❌ Please select an image.";
    }
}

/* -------------------------
   PROPERTY DELETE
------------------------- */
if (isset($_GET['delete_property'])) {
    $prop_id = intval($_GET['delete_property']);
    $stmt = $conn->prepare("SELECT photo FROM properties WHERE id=? AND owner_id=?");
    $stmt->bind_param("ii", $prop_id, $landlord_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        @unlink("uploads/" . $row['photo']); // delete image file
    }
    $stmt = $conn->prepare("DELETE FROM properties WHERE id=? AND owner_id=?");
    $stmt->bind_param("ii", $prop_id, $landlord_id);
    $stmt->execute();
    header("Location: landlord_dashboard.php");
    exit;
}

/* -------------------------
   REQUEST APPROVE / DECLINE
------------------------- */
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    $action = $_GET['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE requests SET status='approved' WHERE id=?");
    } elseif ($action === 'decline') {
        $stmt = $conn->prepare("UPDATE requests SET status='declined' WHERE id=?");
    }
    if (isset($stmt)) {
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
    }
    header("Location: landlord_dashboard.php");
    exit;
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
<html>
<head>
    <title>Landlord Dashboard - RentConnect</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        input, textarea { padding: 5px; margin: 5px 0; width: 100%; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; }
        a.button { padding: 5px 10px; text-decoration: none; border-radius: 5px; color: white; }
        a.approve { background-color: #4CAF50; }
        a.decline { background-color: #f44336; }
        a.delete { background-color: #ff5555; }
        a.chat { background-color: #2196F3; }
        img { max-width: 100px; }
        .section { margin-top: 40px; }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (Landlord)</h2>
    <a href="logout.php">Logout</a>

    <!-- Property Upload -->
    <div class="section">
        <h3>Upload New Property</h3>
        <?php if(isset($message)) echo "<p>$message</p>"; ?>
        <form method="post" enctype="multipart/form-data">
            <label>Title:</label>
            <input type="text" name="title" required>
            <label>Location:</label>
            <input type="text" name="location" required>
            <label>Price (USD):</label>
            <input type="number" name="price" step="0.01" required>
            <label>Contact Info:</label>
            <input type="text" name="contact" required>
            <label>Photo:</label>
            <input type="file" name="photo" accept="image/*" required>
            <input type="submit" name="submit_property" value="Upload Property">
        </form>
    </div>

    <!-- Property List -->
    <div class="section">
        <h3>Your Properties</h3>
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
                        <td><img src="uploads/<?php echo htmlspecialchars($row['photo']); ?>"></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td>$<?php echo $row['price']; ?></td>
                        <td><?php echo htmlspecialchars($row['contact']); ?></td>
                        <td>
                            <a href="?delete_property=<?php echo $row['id']; ?>" class="button delete" onclick="return confirm('Delete this property?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No properties yet.</p>
        <?php endif; ?>
    </div>

    <!-- Rental Requests -->
    <div class="section">
        <h3>Rental Requests</h3>
        <?php if ($requests->num_rows > 0): ?>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Price</th>
                    <th>Renter</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td>$<?php echo $row['price']; ?></td>
                        <td><?php echo htmlspecialchars($row['renter_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['renter_email']); ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                                <a href="?action=approve&request_id=<?php echo $row['request_id']; ?>" class="button approve">✅ Approve</a>
                                <a href="?action=decline&request_id=<?php echo $row['request_id']; ?>" class="button decline">❌ Decline</a>
                            <?php elseif ($row['status'] == 'approved'): ?>
                                <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['renter_id']; ?>" class="button chat">💬 Message Renter</a>
                            <?php else: ?>
                                No action
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No requests yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
