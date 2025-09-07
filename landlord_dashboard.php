<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

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
        $target_file = "uploads/" . $image_name;

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
        @unlink("uploads/" . $row['photo']);
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
</head>
<body style="font-family: Arial, sans-serif; margin:20px; background:#f4f6f9;">

    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (Landlord)</h2>
    <a href="logout.php" style="text-decoration:none; color:white; background:#f44336; padding:6px 12px; border-radius:5px;">Logout</a>

    <!-- Upload Property Button -->
    <div style="margin:20px 0;">
        <a href="add_property.php" style="text-decoration:none; background:#4CAF50; color:white; padding:10px 18px; border-radius:5px;">➕ Upload New Property</a>
    </div>

    <!-- Display Message -->
    <?php if($message) echo "<p style='color:green; font-weight:bold;'>$message</p>"; ?>

    <!-- Properties Section -->
    <h3>Your Properties</h3>
    <?php if ($properties->num_rows > 0): ?>
        <div style="display:flex; flex-wrap:wrap; gap:20px;">
            <?php while ($row = $properties->fetch_assoc()): ?>
                <div style="background:white; border-radius:10px; width:280px; box-shadow:0 4px 12px rgba(0,0,0,0.1); overflow:hidden;">
                    <img src="uploads/<?php echo htmlspecialchars($row['photo']); ?>" alt="Property" style="width:100%; height:180px; object-fit:cover;">
                    <div style="padding:15px;">
                        <h4 style="margin:0 0 10px; color:#4CAF50;"><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p style="margin:5px 0;">📍 <?php echo htmlspecialchars($row['location']); ?></p>
                        <p style="margin:5px 0;">💲 $<?php echo $row['price']; ?></p>
                        <p style="margin:5px 0;">📞 <?php echo htmlspecialchars($row['contact']); ?></p>
                        <div style="display:flex; justify-content:space-between; margin-top:10px;">
                            <a href="edit_property.php?id=<?php echo $row['id']; ?>" style="text-decoration:none; background:#FF9800; color:white; padding:6px 12px; border-radius:5px;">✏️ Edit</a>
                            <a href="?delete_property=<?php echo $row['id']; ?>" onclick="return confirm('Delete this property?')" style="text-decoration:none; background:#f44336; color:white; padding:6px 12px; border-radius:5px;">🗑️ Delete</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No properties uploaded yet.</p>
    <?php endif; ?>

    <!-- Rental Requests Section -->
    <h3 style="margin-top:40px;">Rental Requests</h3>
    <?php if ($requests->num_rows > 0): ?>
        <div style="display:flex; flex-direction:column; gap:15px;">
            <?php while ($row = $requests->fetch_assoc()): ?>
                <div style="background:white; border-radius:10px; padding:15px; box-shadow:0 4px 12px rgba(0,0,0,0.1); display:flex; justify-content:space-between; flex-wrap:wrap;">
                    <div>
                        <strong><?php echo htmlspecialchars($row['title']); ?></strong> - $<?php echo $row['price']; ?><br>
                        Renter: <?php echo htmlspecialchars($row['renter_name']); ?> | <?php echo htmlspecialchars($row['renter_email']); ?><br>
                        Status: <?php echo ucfirst($row['status']); ?>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:10px;">
                        <?php if ($row['status'] == 'pending'): ?>
                            <a href="?action=approve&request_id=<?php echo $row['request_id']; ?>" style="text-decoration:none; background:#4CAF50; color:white; padding:6px 12px; border-radius:5px;">✅ Approve</a>
                            <a href="?action=decline&request_id=<?php echo $row['request_id']; ?>" style="text-decoration:none; background:#f44336; color:white; padding:6px 12px; border-radius:5px;">❌ Decline</a>
                        <?php elseif ($row['status'] == 'approved'): ?>
                            <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['renter_id']; ?>" style="text-decoration:none; background:#2196F3; color:white; padding:6px 12px; border-radius:5px;">💬 Message Renter</a>
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

</body>
</html>
