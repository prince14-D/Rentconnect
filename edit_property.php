<?php
session_start();
include "db.php";

// Ensure landlord is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";
$prop_id = intval($_GET['id'] ?? 0);

// Fetch existing property
$stmt = $conn->prepare("SELECT * FROM properties WHERE id=? AND owner_id=?");
$stmt->bind_param("ii", $prop_id, $landlord_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    die("❌ Property not found.");
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = $_POST['description'];

    // Update property info
    $stmt = $conn->prepare("UPDATE properties 
        SET title=?, location=?, price=?, contact=?, bedrooms=?, bathrooms=?, description=? 
        WHERE id=? AND owner_id=?");
    $stmt->bind_param(
        "ssdsiisii", 
        $title, $location, $price, $contact, $bedrooms, $bathrooms, $description, $prop_id, $landlord_id
    );
    if ($stmt->execute()) {
        // Handle new image uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $total_files = count($_FILES['photos']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['photos']['error'][$i] === 0) {
                    $imageData = file_get_contents($_FILES['photos']['tmp_name'][$i]);

                    $img_stmt = $conn->prepare("INSERT INTO property_images (property_id, image) VALUES (?, ?)");
                    $img_stmt->bind_param("ib", $prop_id, $null);
                    $img_stmt->send_long_data(1, $imageData);
                    $img_stmt->execute();
                }
            }
        }
        $message = "✅ Property updated successfully!";
    } else {
        $message = "❌ Database error: " . $stmt->error;
    }
}

// Fetch property images
$img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $prop_id);
$img_stmt->execute();
$images = $img_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Property - RentConnect</title>
<style>
body { font-family: Arial; background:#f4f6f9; display:flex; justify-content:center; align-items:flex-start; padding:40px; }
.form-box { background:white; padding:30px; border-radius:10px; width:500px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#FF9800; }
input, textarea, button { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:1px solid #ccc; }
textarea { height:80px; resize:none; }
button { background:#FF9800; color:white; border:none; cursor:pointer; font-size:1.1em; }
button:hover { background:#e68a00; }
.gallery { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
.gallery img { width:100px; height:100px; object-fit:cover; border-radius:5px; }
.message { text-align:center; color:green; margin-bottom:10px; }
a.back { display:block; margin-top:10px; text-align:center; text-decoration:none; color:#2196F3; }
.carousel { position:relative; width:100%; height:250px; overflow:hidden; margin-bottom:20px; }
.carousel img { width:100%; height:250px; object-fit:cover; display:none; }
.carousel img.active { display:block; }
.carousel button { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.5); color:#fff; border:none; padding:5px 10px; cursor:pointer; border-radius:50%; }
.carousel .prev { left:10px; } .carousel .next { right:10px; }
</style>
</head>
<body>

<div class="form-box">
<h2>Edit Property</h2>

<?php if($message) echo "<p class='message'>$message</p>"; ?>

<!-- Display carousel if images exist -->
<?php if($images->num_rows > 0): ?>
<div class="carousel" id="carousel-<?php echo $prop_id; ?>">
    <?php while($row = $images->fetch_assoc()): ?>
        <img src="display_image.php?img_id=<?php echo $row['id']; ?>" alt="Property Image">
    <?php endwhile; ?>
    <button class="prev">&#10094;</button>
    <button class="next">&#10095;</button>
</div>
<?php else: ?>
<p>No images uploaded yet.</p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="text" name="title" placeholder="Title" value="<?php echo htmlspecialchars($property['title']); ?>" required>
    <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($property['location']); ?>" required>
    <input type="number" name="price" placeholder="Price" step="0.01" value="<?php echo $property['price']; ?>" required>
    <input type="text" name="contact" placeholder="Contact" value="<?php echo htmlspecialchars($property['contact']); ?>" required>
    <input type="number" name="bedrooms" placeholder="Bedrooms" value="<?php echo $property['bedrooms']; ?>">
    <input type="number" name="bathrooms" placeholder="Bathrooms" value="<?php echo $property['bathrooms']; ?>">
    <textarea name="description" placeholder="Description"><?php echo htmlspecialchars($property['description']); ?></textarea>

    <p>Upload New Photos (optional):</p>
    <input type="file" name="photos[]" multiple accept="image/*">

    <button type="submit">Update Property</button>
</form>

<a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</div>

<script>
// Auto-slide carousel
document.addEventListener("DOMContentLoaded", ()=>{
    const carousel = document.getElementById("carousel-<?php echo $prop_id; ?>");
    if(carousel){
        const imgs = carousel.querySelectorAll("img");
        let index = 0;
        if(imgs.length>0) imgs[0].classList.add("active");

        setInterval(()=> {
            imgs[index].classList.remove("active");
            index = (index+1)%imgs.length;
            imgs[index].classList.add("active");
        },3000);

        carousel.querySelector(".prev").addEventListener("click", ()=>{
            imgs[index].classList.remove("active");
            index = (index-1+imgs.length)%imgs.length;
            imgs[index].classList.add("active");
        });
        carousel.querySelector(".next").addEventListener("click", ()=>{
            imgs[index].classList.remove("active");
            index = (index+1)%imgs.length;
            imgs[index].classList.add("active");
        });
    }
});
</script>

</body>
</html>
