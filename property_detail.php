<?php
session_start();
include "db.php";

// Validate property ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p>Invalid property.</p>";
    exit;
}
$property_id = intval($_GET['id']);

// ✅ Fetch property + landlord info (using landlord_id)
$sql = "SELECT p.*, u.name AS landlord_name, u.email AS landlord_email 
        FROM properties p 
        JOIN users u ON p.landlord_id = u.id 
        WHERE p.id = ? AND p.status='approved' LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p>Property not found or not approved yet.</p>";
    exit;
}

$property = $result->fetch_assoc();

// ✅ Fetch images
$img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $property_id);
$img_stmt->execute();
$imgs = $img_stmt->get_result();
?>

<h2><?php echo htmlspecialchars($property['title']); ?></h2>

<div style="display:flex; flex-wrap:wrap; gap:15px;">
    <div style="flex:1; min-width:250px;">
        <?php if ($imgs->num_rows > 0): ?>
            <?php while ($img = $imgs->fetch_assoc()): ?>
                <img src="display_image.php?img_id=<?php echo $img['id']; ?>" 
                     alt="Property Image" 
                     style="width:100%; max-width:400px; margin-bottom:10px; border-radius:8px;">
            <?php endwhile; ?>
        <?php else: ?>
            <img src="images/no-image.png" 
                 alt="No Image" 
                 style="width:100%; max-width:400px; margin-bottom:10px; border-radius:8px;">
        <?php endif; ?>
    </div>

    <div style="flex:1; min-width:250px;">
        <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($property['location']); ?></p>
        <p><strong>💲 Price:</strong> $<?php echo number_format($property['price']); ?></p>
        <p><strong>📝 Description:</strong> <?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
        <p><strong>👤 Landlord:</strong> <?php echo htmlspecialchars($property['landlord_name']); ?> (<?php echo htmlspecialchars($property['landlord_email']); ?>)</p>

        <!-- Request to Rent Button -->
        <a href="renter_dashboard.php?request_property=<?php echo $property['id']; ?>" 
           style="display:inline-block;margin-top:10px;padding:10px 15px;background:#4CAF50;color:white;border-radius:6px;text-decoration:none;">
           ✅ Request to Rent
        </a>

        <!-- Chat with Landlord -->
        <a href="chat.php?property_id=<?php echo $property['id']; ?>&with=<?php echo $property['landlord_id']; ?>" 
           style="display:inline-block;margin-top:10px;padding:10px 15px;background:#2196F3;color:white;border-radius:6px;text-decoration:none;">
           💬 Message Landlord
        </a>
    </div>
</div>
