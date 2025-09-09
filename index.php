<?php
session_start();
include "db.php";

// Handle search filters
$search_location = isset($_GET['location']) ? $_GET['location'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

$sql = "SELECT p.*, u.name AS landlord_name 
        FROM properties p
        JOIN users u ON p.owner_id = u.id
        WHERE 1 ";
$params = [];
$types = "";

if (!empty($search_location)) {
    $sql .= "AND p.location LIKE ? ";
    $types .= "s";
    $params[] = "%$search_location%";
}
if ($min_price > 0) {
    $sql .= "AND p.price >= ? ";
    $types .= "d";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $sql .= "AND p.price <= ? ";
    $types .= "d";
    $params[] = $max_price;
}

$sql .= "ORDER BY p.created_at DESC LIMIT 12";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$properties = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RentConnect - Find Homes in Liberia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global Styles */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fb; color: #333; }
        
        /* Header */
        header { background: #ffffff; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        header h1 { font-size: 1.6em; color: #2E7D32; font-weight: bold; }
        nav { display: flex; gap: 20px; }
        nav a { text-decoration: none; color: #444; font-weight: 500; transition: 0.3s; }
        nav a:hover { color: #2E7D32; }
        .upload-btn { background: #2E7D32; color: #fff; padding: 8px 14px; border-radius: 6px; }
        .upload-btn:hover { background: #1b5e20; }

        /* Hero */
        .hero { background: linear-gradient(rgba(0,0,0,0.6),rgba(0,0,0,0.6)), url('images/home-bg.png') center/cover no-repeat; color: #fff; text-align: center; padding: 120px 20px; }
        .hero h2 { font-size: 2.8em; margin-bottom: 10px; font-weight: 700; }
        .hero p { font-size: 1.2em; opacity: 0.9; }

        /* Search Box */
        .search-box { background: #fff; padding: 25px; border-radius: 14px; width: 90%; max-width: 950px; margin: -70px auto 40px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }
        .search-box input { flex: 1 1 200px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; }
        .search-box button { padding: 12px 25px; background: #2E7D32; color: white; border: none; border-radius: 8px; font-size: 1em; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .search-box button:hover { background: #1b5e20; }

        /* Section */
        .section { padding: 50px 20px; text-align: center; }
        .section h3 { font-size: 2em; margin-bottom: 30px; color: #2E7D32; font-weight: 700; }

        /* Properties Grid */
        .properties { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
        .property { background: #fff; border-radius: 14px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); transition: transform 0.3s; overflow: hidden; }
        .property:hover { transform: translateY(-6px); }
        .carousel { position: relative; width: 100%; height: 200px; overflow: hidden; }
        .carousel img { width: 100%; height: 200px; object-fit: cover; display: none; }
        .carousel img.active { display: block; }
        .carousel button { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); border: none; color: #fff; padding: 6px 10px; cursor: pointer; border-radius: 50%; }
        .carousel .prev { left: 12px; }
        .carousel .next { right: 12px; }

        .content { padding: 18px; text-align: left; }
        .content h4 { margin-bottom: 6px; font-size: 1.2em; color: #222; }
        .content p { margin: 4px 0; color: #555; font-size: 0.95em; }
        .content a { display: inline-block; margin-top: 12px; padding: 10px 14px; background: #2196F3; color: #fff; border-radius: 8px; text-decoration: none; font-weight: bold; }
        .content a:hover { background: #1565C0; }

        /* Features */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px; }
        .feature { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .feature h4 { color: #2E7D32; margin-bottom: 10px; }

        /* Footer */
        footer { background: #222; color: #ccc; padding: 25px; margin-top: 60px; text-align: center; }
        footer a { color: #FF9800; text-decoration: none; }

        @media (max-width: 600px) {
            .hero h2 { font-size: 2em; }
            .search-box { flex-direction: column; align-items: stretch; }
        }
    </style>
    <script>
        function initCarousel(carouselId) {
            let index = 0;
            const images = document.querySelectorAll(`#${carouselId} img`);
            if (images.length === 0) return;
            images[index].classList.add("active");
            document.querySelector(`#${carouselId} .next`).addEventListener("click", () => {
                images[index].classList.remove("active");
                index = (index + 1) % images.length;
                images[index].classList.add("active");
            });
            document.querySelector(`#${carouselId} .prev`).addEventListener("click", () => {
                images[index].classList.remove("active");
                index = (index - 1 + images.length) % images.length;
                images[index].classList.add("active");
            });
        }
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll(".carousel").forEach(carousel => {
                initCarousel(carousel.id);
            });
        });
    </script>
</head>
<body>

<header>
    <h1>🏠RentConnect</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="signup.php">Sign Up</a>
        <a href="login.php">Login</a>
        <a href="contact.php">Contact</a>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'landlord'): ?>
            <a href="upload_property.php" class="upload-btn">Upload Property</a>
        <?php else: ?>
            <a href="login.php" class="upload-btn">Upload Property</a>
        <?php endif; ?>
    </nav>
<script>
function initCarousel(carouselId) {
    let index = 0;
    const carousel = document.getElementById(carouselId);
    const images = carousel.querySelectorAll("img");
    if (images.length === 0) return;

    images.forEach(img => img.classList.remove("active"));
    images[0].classList.add("active");

    function showSlide(newIndex) {
        images[index].classList.remove("active");
        index = (newIndex + images.length) % images.length;
        images[index].classList.add("active");
    }

    // Manual controls
    carousel.querySelector(".next").addEventListener("click", () => {
        showSlide(index + 1);
    });
    carousel.querySelector(".prev").addEventListener("click", () => {
        showSlide(index - 1);
    });

    // Auto slide every 3s
    setInterval(() => {
        showSlide(index + 1);
    }, 3000);
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".carousel").forEach(carousel => {
        initCarousel(carousel.id);
    });
});
</script>


</header>

<section class="hero">
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
    <br>
</section>

<div class="search-box">
    <form method="get" action="">
        <input type="text" name="location" placeholder="Enter location" value="<?php echo htmlspecialchars($search_location); ?>">
        <input type="number" name="min_price" placeholder="Min Price" value="<?php echo $min_price ?: ''; ?>">
        <input type="number" name="max_price" placeholder="Max Price" value="<?php echo $max_price ?: ''; ?>">
        <button type="submit">🔍 Search</button>
    </form>
</div>

<section class="section">
    <h3>Latest Properties</h3>
    <div class="properties">
        <?php if ($properties->num_rows > 0): ?>
            <?php while($property = $properties->fetch_assoc()): ?>
                <div class="property">
                    <div class="carousel" id="carousel-<?php echo $property['id']; ?>">
                        <?php
                        // Fetch property images
                        $img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
                        $img_stmt->bind_param("i", $property['id']);
                        $img_stmt->execute();
                        $images = $img_stmt->get_result();

                        if ($images->num_rows > 0) {
                            while ($row = $images->fetch_assoc()) {
                                echo '<img src="display_image.php?img_id='.$row['id'].'" alt="Property Image">';
                            }
                        } else {
                            echo '<img src="images/no-image.png" alt="No Image">';
                        }
                        ?>
                        <button class="prev">&#10094;</button>
                        <button class="next">&#10095;</button>
                    </div>

                    <div class="content">
                        <h4><?php echo htmlspecialchars($property['title']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($property['location']); ?></p>
                        <p>💲 $<?php echo number_format($property['price']); ?></p>
                        <p>📞 <?php echo htmlspecialchars($property['contact']); ?></p>
                        <p><i>Landlord: <?php echo htmlspecialchars($property['landlord_name']); ?></i></p>
                        <a href="login.php">View Details</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No properties found.</p>
        <?php endif; ?>
    </div>
</section>

<script>
// Auto-slide carousel with manual prev/next
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".carousel").forEach(carousel => {
        const imgs = carousel.querySelectorAll("img");
        let index = 0;
        if (imgs.length > 0) imgs[0].classList.add("active");

        // Show slide function
        function showSlide(newIndex) {
            imgs[index].classList.remove("active");
            index = (newIndex + imgs.length) % imgs.length;
            imgs[index].classList.add("active");
        }

        // Auto-slide every 3s
        setInterval(() => showSlide(index + 1), 3000);

        // Manual controls
        carousel.querySelector(".prev").addEventListener("click", () => showSlide(index - 1));
        carousel.querySelector(".next").addEventListener("click", () => showSlide(index + 1));
    });
});
</script>


<section class="section">
    <h3>Why Use RentConnect?</h3>
    <div class="features">
        <div class="feature">
            <h4>For Renters</h4>
            <p>Browse verified homes and apartments, save favorites, and send rental requests securely.</p>
        </div>
        <div class="feature">
            <h4>For Landlords</h4>
            <p>Upload property listings with photos and manage rental requests from your dashboard.</p>
        </div>
        <div class="feature">
            <h4>Secure & Easy</h4>
            <p>Simple signup, safe login, and streamlined dashboards for renters and landlords.</p>
        </div>
    </div>
</section>

<footer>
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="contact.php">Contact Us</a></p>
</footer>

</body>
</html>




