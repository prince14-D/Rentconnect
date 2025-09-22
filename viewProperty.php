<?php
session_start();
include "db.php";

// Validate property ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p style='color:red;'>❌ Invalid property request.</p>";
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property details
$stmt = $conn->prepare("
    SELECT p.id, p.title, p.location, p.price, p.bedrooms, p.bathrooms, 
           p.description, p.created_at,
           u.name AS landlord_name, u.email AS landlord_email
    FROM properties p
    JOIN users u ON p.owner_id = u.id
    WHERE p.id = ? AND p.status = 'approved'
");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    ?>
    <div style="padding:10px; max-width:600px; margin:auto;">
        <h2 style="color:#2e7d32; margin-top:0; text-align:center;">
            <?php echo htmlspecialchars($row['title']); ?>
        </h2>

        <!-- Image Carousel -->
        <div class="carousel" style="position:relative; width:100%; height:300px; overflow:hidden; border-radius:10px; margin-bottom:15px;">
            <?php
            $img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
            $img_stmt->bind_param("i", $property_id);
            $img_stmt->execute();
            $imgs = $img_stmt->get_result();

            if ($imgs->num_rows > 0) {
                $i = 0;
                while ($img = $imgs->fetch_assoc()) {
                    $active = ($i == 0) ? "active" : "";
                    echo '<img src="display_image.php?img_id='.$img['id'].'" class="carousel-img '.$active.'" 
                          style="width:100%; height:300px; object-fit:cover; display:'.($i==0?'block':'none').'">';
                    $i++;
                }
            } else {
                echo '<img src="images/no-image.png" style="width:100%; height:300px; object-fit:cover;">';
            }
            ?>
            <button class="prev" style="position:absolute;top:50%;left:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);color:#fff;border:none;padding:8px;cursor:pointer;border-radius:4px;">❮</button>
            <button class="next" style="position:absolute;top:50%;right:10px;transform:translateY(-50%);background:rgba(0,0,0,0.5);color:#fff;border:none;padding:8px;cursor:pointer;border-radius:4px;">❯</button>
        </div>

        <p><strong>📍 Location:</strong> <?php echo htmlspecialchars($row['location']); ?></p>
        <p><strong>💲 Price:</strong> $<?php echo number_format($row['price'], 2); ?></p>
        <p><strong>🛏 Bedrooms:</strong> <?php echo (int)$row['bedrooms']; ?></p>
        <p><strong>🛁 Bathrooms:</strong> <?php echo (int)$row['bathrooms']; ?></p>
        <p><strong>📝 Description:</strong><br><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
        <p><strong>👤 Landlord:</strong> <?php echo htmlspecialchars($row['landlord_name']); ?> 
           (<?php echo htmlspecialchars($row['landlord_email']); ?>)</p>
        <p><em>Posted on <?php echo date("F j, Y", strtotime($row['created_at'])); ?></em></p>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'renter'): ?>
            <div style="text-align:center; margin-top:20px;">
                <a href="renter_dashboard.php?request_property=<?php echo $row['id']; ?>" 
                   style="display:inline-block; padding:12px 20px; background:#4CAF50; color:white; text-decoration:none; border-radius:8px; font-weight:bold;">
                   ✅ Request to Rent
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", ()=>{
        let carousel=document.querySelector(".carousel");
        if(!carousel) return;
        let imgs=carousel.querySelectorAll(".carousel-img");
        let index=0;
        function show(i){
            imgs.forEach(img=>img.style.display="none");
            imgs[i].style.display="block";
        }
        if(imgs.length>1){
            carousel.querySelector(".prev").onclick=()=>{ index=(index-1+imgs.length)%imgs.length; show(index); }
            carousel.querySelector(".next").onclick=()=>{ index=(index+1)%imgs.length; show(index); }
            setInterval(()=>{ index=(index+1)%imgs.length; show(index); },4000);
        }
    });
    </script>
    <?php
} else {
    echo "<p style='color:#777;'>❌ Property not found or not approved yet.</p>";
}
