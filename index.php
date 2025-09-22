<?php
session_start();
include "db.php";

// --- Search Filters ---
$search_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

// --- User Info ---
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// --- Base SQL ---
$sql = "SELECT p.*, l.name AS landlord_name 
        FROM properties p 
        JOIN users l ON p.landlord_id = l.id 
        WHERE 1=1 ";
$params = [];
$types = "";

// --- Role-based filtering ---
if ($user_role === 'landlord') {
    // Landlord sees their own pending and approved properties
    $sql .= " AND p.landlord_id = ? AND p.status IN ('pending', 'approved') ";
    $types .= "i";
    $params[] = $user_id;
} else {
    // Visitors/renters see only approved properties
    $sql .= " AND p.status = 'approved' ";
}

// --- Apply search filters ---
if (!empty($search_location)) {
    $sql .= " AND p.location LIKE ? ";
    $types .= "s";
    $params[] = "%$search_location%";
}
if ($min_price > 0) {
    $sql .= " AND p.price >= ? ";
    $types .= "d";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $sql .= " AND p.price <= ? ";
    $types .= "d";
    $params[] = $max_price;
}

$sql .= " ORDER BY p.created_at DESC LIMIT 12";

// --- Prepare and execute ---
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
        /* === Global Styles === */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fb; color: #333; }

        /* === Header === */
        header { background: #ffffff; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        header h1 { font-size: 1.6em; color: #2E7D32; font-weight: bold; }
        nav { display: flex; gap: 20px; }
        nav a { text-decoration: none; color: #444; font-weight: 500; transition: 0.3s; }
        nav a:hover { color: #ffffffff; }
        .upload-btn { background: #2E7D32; color: #fff; padding: 8px 14px; border-radius: 6px; }
        .upload-btn:hover { background: #1b5e20; }

        /* === Hamburger Button === */
        .menu-toggle { display: none; background: none; border: none; font-size: 1.8em; cursor: pointer; }
        @media (max-width: 768px) {
            nav { 
                display: none; flex-direction: column; position: absolute; 
                top: 60px; right: 20px; background: #fff; padding: 15px; 
                border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            }
            nav.active { display: flex; }
            .menu-toggle { display: block; }
        }

        /* === Hero Carousel === */
        .hero-carousel { position: relative; width: 100%; height: 70vh; overflow: hidden; }
        .hero-carousel .slide { position: absolute; width: 100%; height: 100%; opacity: 0; transition: opacity 1s ease-in-out; }
        .hero-carousel .slide.active { opacity: 1; }
        .hero-carousel img { width: 100%; height: 100%; object-fit: cover; }
        .hero-carousel .caption { position: absolute; bottom: 20%; left: 50%; transform: translateX(-50%); text-align: center; color: #fff; background: rgba(0,0,0,0.5); padding: 20px; border-radius: 10px; }
        .hero-carousel .caption h2 { font-size: 2.5em; margin-bottom: 10px; }
        .hero-carousel .caption p { font-size: 1.2em; }
        @media(max-width: 768px) {
          .hero-carousel { height: 40vh; }
          .hero-carousel .caption h2 { font-size: 1.6em; }
          .hero-carousel .caption p { font-size: 1em; }
        }

        /* === Search Box === */
        .search-box { background: #fff; padding: 25px; border-radius: 14px; width: 90%; max-width: 950px; margin: -70px auto 40px; box-shadow: 0 6px 18px rgba(0,0,0,0.08); display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }
        .search-box input { flex: 1 1 200px; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1em; }
        .search-box button { padding: 12px 25px; background: #2E7D32; color: white; border: none; border-radius: 8px; font-size: 1em; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .search-box button:hover { background: #1b5e20; }

        /* === Properties Section === */
        .section { padding: 50px 20px; text-align: center; }
        .section h3 { font-size: 2em; margin-bottom: 30px; color: #2E7D32; font-weight: 700; }
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

        /* === Features Section === */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px; }
        .feature { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .feature h4 { color: #2E7D32; margin-bottom: 10px; }

        /* === Footer === */
        footer { background: #222; color: #ccc; padding: 25px; margin-top: 60px; text-align: center; }
        footer a { color: #FF9800; text-decoration: none; }

        .search-box {
    margin: 20px auto 40px;
}

/* Nav links & buttons */
nav a, 
nav .dropbtn {
  text-decoration: none;
  color: #444;
  font-weight: 500;
  padding: 8px 14px;
  border: none;
  background: none;
  cursor: pointer;
  font-size: 1em;
  transition: 0.3s;
}

nav a:hover, 
nav .dropbtn:hover {
  color: #2E7D32;
}

/* Dropdown wrapper */
.dropdown {
  position: relative;
  display: inline-block;
}

/* Dropdown content */
.dropdown-content {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  background: #fff;
  min-width: 160px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  border-radius: 6px;
  z-index: 999;
}

.dropdown-content a {
  display: block;
  padding: 10px;
  text-decoration: none;
  color: #444;
  font-size: 0.95em;
}

.dropdown-content a:hover {
  background: #f5f5f5;
  color: #2E7D32;
}

/* Desktop hover */
@media (min-width: 769px) {
  .dropdown:hover .dropdown-content {
    display: block;
  }
}

/* Mobile click */
.dropdown.active .dropdown-content {
  display: block;
}


    </style>

</head>
<body>

<header>
    <h1>
        <a href="index.php" style="text-decoration: none; color: #2E7D32;">
            RentConnect
        </a>
    </h1>
    <button class="menu-toggle" onclick="toggleMenu()">☰</button>
    <nav id="navMenu">
        <a href="about.php">About Us</a>
        <a href="services.php">Services</a>
        <a href="contact.php">Contact</a>

        <!-- Dropdown -->
        <div class="dropdown">
            <button class="dropbtn" onclick="toggleDropdown(event)">Account ▾</button>
            <div class="dropdown-content">
                <a href="login.php">Login</a>
                <a href="signup.php">Sign Up</a>
            </div>
        </div>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'landlord'): ?>
            <a href="upload_property.php" class="upload-btn">Upload Property</a>
        <?php else: ?>
            <a href="login.php" class="upload-btn">Upload Property</a>
        <?php endif; ?>
    </nav>
</header>


<!-- Hero Carousel -->
<section class="hero-carousel">
  <div class="slide active">
    <img src="images/home-banner.png" alt="Home Banner 1">
    <div class="caption">
      <h2>Find Your Perfect Home</h2>
      <p>Browse verified rental listings across Liberia.</p>
    </div>
  </div>
  <div class="slide">
    <img src="images/home-banner-2.png" alt="Home Banner 2">
    <div class="caption">
      <h2>Secure & Affordable</h2>
      <p>Trusted homes from verified landlords.</p>
    </div>
  </div>
</section>

<!-- Search Box -->
<div class="search-box">
    <form method="get" action="">
        <input type="text" name="location" placeholder="Enter location" value="<?php echo htmlspecialchars($search_location); ?>">
        <input type="number" name="min_price" placeholder="Min Price" value="<?php echo $min_price ?: ''; ?>">
        <input type="number" name="max_price" placeholder="Max Price" value="<?php echo $max_price ?: ''; ?>">
        <button type="submit">Search</button>
    </form>
</div>

<!-- Latest Properties -->
<section class="section">
    <h3>Latest Properties</h3>
    <div class="properties">
        <?php if ($properties->num_rows > 0): ?>
            <?php while($property = $properties->fetch_assoc()): ?>
                <div class="property">
                    <div class="carousel" id="carousel-<?php echo $property['id']; ?>">
                        <?php
                        $img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
                        $img_stmt->bind_param("i", $property['id']);
                        $img_stmt->execute();
                        $images = $img_stmt->get_result();
                        if ($images->num_rows > 0) {
                            $first = true;
                            while ($row = $images->fetch_assoc()) {
                                echo '<img src="display_image.php?img_id='.$row['id'].'" class="'.($first ? 'active' : '').'" alt="Property Image">';
                                $first = false;
                            }
                        } else {
                            echo '<img src="images/no-image.png" class="active" alt="No Image">';
                        }
                        ?>
                        <button class="prev">&#10094;</button>
                        <button class="next">&#10095;</button>
                    </div>
                    <div class="content">
                        <h4><?php echo htmlspecialchars($property['title']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($property['location']); ?></p>
                        <p>💲 $<?php echo number_format($property['price']); ?></p>
                        <?php if ($user_role === 'landlord'): ?>
                            <p>Status: <strong><?php echo ucfirst($property['status']); ?></strong></p>
                        <?php endif; ?>
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


<!-- Why Use RentConnect -->
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

<!-- Footer -->
<footer>
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="contact.php">Contact Us</a></p>
</footer>

<script>
// Toggle menu
function toggleMenu() {
    document.getElementById("navMenu").classList.toggle("active");
}

// Hero Carousel auto slide
document.addEventListener("DOMContentLoaded", () => {
    const heroSlides = document.querySelectorAll(".hero-carousel .slide");
    let heroIndex = 0;
    setInterval(() => {
        heroSlides[heroIndex].classList.remove("active");
        heroIndex = (heroIndex + 1) % heroSlides.length;
        heroSlides[heroIndex].classList.add("active");
    }, 4000);

    // Property carousels
    document.querySelectorAll(".carousel").forEach(carousel => {
        const imgs = carousel.querySelectorAll("img");
        let index = 0;
        function showSlide(newIndex) {
            imgs[index].classList.remove("active");
            index = (newIndex + imgs.length) % imgs.length;
            imgs[index].classList.add("active");
        }
        if (imgs.length > 0) imgs[0].classList.add("active");
        setInterval(() => showSlide(index + 1), 3000);
        carousel.querySelector(".prev").addEventListener("click", () => showSlide(index - 1));
        carousel.querySelector(".next").addEventListener("click", () => showSlide(index + 1));
    });
});
</script>

<script>
function toggleMenu() {
    document.getElementById("navMenu").classList.toggle("active");
}

function toggleDropdown(event) {
    event.preventDefault();
    const dropdown = event.target.closest(".dropdown");
    dropdown.classList.toggle("active");
}
</script>

</body>
</html>
