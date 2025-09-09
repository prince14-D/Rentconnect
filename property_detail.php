<?php
session_start();
include "db.php";

if (!isset($_GET['id'])) {
    echo "Invalid property ID.";
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property info
$stmt = $conn->prepare("
    SELECT p.*, u.name AS landlord_name, u.email AS landlord_email 
    FROM properties p
    JOIN users u ON p.owner_id = u.id
    WHERE p.id=?
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    echo "Property not found.";
    exit;
}

// Fetch property images
$img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $property_id);
$img_stmt->execute();
$images = $img_stmt->get_result();
?>

<style>
    .carousel-container { position:relative; width:100%; max-width:600px; margin:auto; overflow:hidden; }
    .carousel-container img { width:100%; height:300px; object-fit:cover; border-radius:8px; display:none; }
    .carousel-container img.active { display:block; }
    .carousel-btn { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.5); color:white; border:none; padding:10px; cursor:pointer; border-radius:50%; }
    .prev { left:10px; }
    .next { right:10px; }
    .detail-info { margin-top:15px; text-align:left; }
    .detail-info h2 { margin:0; color:#2E7D32; }
    .detail-info p { margin:5px 0; color:#444; }
    .request-btn { display:inline-block; margin-top:15px; background:#2E7D32; color:white; padding:10px 15px; border-radius:6px; text-decoration:none; font-weight:bold; }
    .request-btn:hover { background:#1b5e20; }
</style>

<div class="carousel-container" id="carousel-<?php echo $property_id; ?>">
    <?php if ($images->num_rows > 0): ?>
        <?php $first = true; ?>
        <?php while ($row = $images->fetch_assoc()): ?>
            <img src="display_image.php?img_id=<?php echo $row['id']; ?>" class="<?php echo $first ? 'active' : ''; ?>">
            <?php $first = false; ?>
        <?php endwhile; ?>
    <?php else: ?>
        <img src="images/no-image.png" class="active">
    <?php endif; ?>
    <button class="carousel-btn prev">&#10094;</button>
    <button class="carousel-btn next">&#10095;</button>
</div>

<div class="detail-info">
    <h2><?php echo htmlspecialchars($property['title']); ?></h2>
    <p>📍 Location: <?php echo htmlspecialchars($property['location']); ?></p>
    <p>💲 Price: $<?php echo number_format($property['price']); ?></p>
    <p>📞 Contact: <?php echo htmlspecialchars($property['contact']); ?></p>
    <p>👤 Landlord: <?php echo htmlspecialchars($property['landlord_name']); ?> (<?php echo htmlspecialchars($property['landlord_email']); ?>)</p>
    <p><i>Uploaded on: <?php echo $property['created_at']; ?></i></p>

    <!-- Request to Rent Button -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'renter'): ?>
        <a href="renter_dashboard.php?request_property=<?php echo $property_id; ?>" class="request-btn">Request to Rent</a>
    <?php else: ?>
        <p style="color:#888;">🔑 Login as a renter to request this property.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const carousel = document.querySelector("#carousel-<?php echo $property_id; ?>");
    const imgs = carousel.querySelectorAll("img");
    let index = 0;
    if (imgs.length > 0) imgs[0].classList.add("active");

    function showSlide(newIndex) {
        imgs[index].classList.remove("active");
        index = (newIndex + imgs.length) % imgs.length;
        imgs[index].classList.add("active");
    }

    carousel.querySelector(".prev").addEventListener("click", () => showSlide(index - 1));
    carousel.querySelector(".next").addEventListener("click", () => showSlide(index + 1));
});
</script>
