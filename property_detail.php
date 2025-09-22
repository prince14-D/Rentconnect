<?php
session_start();
include "db.php";

if (!isset($_GET['id'])) {
    echo "<p>❌ Property not found.</p>";
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property info
$sql = "SELECT p.*, u.name AS landlord_name, u.email AS landlord_email, u.phone AS landlord_phone
        FROM properties p
        JOIN users u ON p.owner_id = u.id
        WHERE p.id = ? AND p.status = 'approved'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p>❌ Property not found or not approved.</p>";
    exit;
}

$property = $result->fetch_assoc();

// Fetch property images
$img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $property_id);
$img_stmt->execute();
$imgs = $img_stmt->get_result();
?>
<div>
    <h2><?php echo htmlspecialchars($property['title']); ?></h2>
    <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($property['location']); ?></p>
    <p><strong>💲 Price:</strong> $<?php echo number_format($property['price']); ?></p>
    <p><strong>📝 Description:</strong><br><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
    <p><strong>👤 Landlord:</strong> <?php echo htmlspecialchars($property['landlord_name']); ?> (<?php echo htmlspecialchars($property['landlord_email']); ?>)</p>
    <p><strong>📞 Contact:</strong> <?php echo htmlspecialchars($property['landlord_phone']); ?></p>

    <!-- Image Gallery -->
    <?php if ($imgs->num_rows > 0): ?>
        <div style="display:flex; gap:10px; overflow-x:auto; margin-top:15px;">
            <?php while ($img = $imgs->fetch_assoc()): ?>
                <img src="display_image.php?img_id=<?php echo $img['id']; ?>" 
                     style="width:200px; height:150px; object-fit:cover; border-radius:8px;">
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No images available.</p>
    <?php endif; ?>

    <!-- Request button (only for renters) -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'renter'): ?>
        <div style="margin-top:20px;">
            <a href="renter_dashboard.php?request_property=<?php echo $property_id; ?>" 
               style="background:#4CAF50; color:white; padding:10px 15px; border-radius:6px; text-decoration:none;">
               ✅ Request to Rent
            </a>
        </div>
    <?php endif; ?>
</div>
