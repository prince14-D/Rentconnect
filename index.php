<?php
session_start();
include "db.php";

// Handle search filters
$search_location = isset($_GET['location']) ? $_GET['location'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

$sql = "SELECT * FROM properties WHERE 1 ";
$params = [];
$types = "";

if (!empty($search_location)) {
    $sql .= "AND location LIKE ? ";
    $types .= "s";
    $params[] = "%$search_location%";
}
if ($min_price > 0) {
    $sql .= "AND price >= ? ";
    $types .= "d";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $sql .= "AND price <= ? ";
    $types .= "d";
    $params[] = $max_price;
}

$sql .= "ORDER BY created_at DESC LIMIT 6";
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
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background: #f9fbfd; color: #333; }

        /* Header */
        header { background: white; box-shadow: 0 2px 6px rgba(0,0,0,0.1); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }
        header h1 { margin: 0; font-size: 1.5em; color: #2E7D32; }
        nav { display: flex; gap: 20px; }
        nav a { color: #333; text-decoration: none; font-weight: bold; transition: 0.3s; }
        nav a:hover { color: #2E7D32; }
        .menu-toggle { display: none; font-size: 26px; cursor: pointer; }

        /* Mobile Nav */
        .mobile-nav { display: none; flex-direction: column; background: #2E7D32; padding: 10px; }
        .mobile-nav a { padding: 12px; color: white; border-bottom: 1px solid rgba(255,255,255,0.2); }

        /* Hero */
        .hero { 
            background: linear-gradient(rgba(0,0,0,0.6),rgba(0,0,0,0.6)), url('images/home-bg.png') center/cover no-repeat;
            color: white; text-align: center; padding: 120px 20px 180px;
        }
        .hero h2 { font-size: 2.8em; margin-bottom: 15px; }
        .hero p { font-size: 1.2em; max-width: 650px; margin: auto; }

        /* Floating Search */
        .search-box { 
            background: white; padding: 25px; border-radius: 16px; width: 90%; max-width: 900px; 
            margin: -80px auto 40px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); 
        }
        .search-box form { display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; }
        .search-box input { padding: 12px; border: 1px solid #ddd; border-radius: 10px; flex: 1 1 160px; font-size: 1em; }
        .search-box button { padding: 12px 25px; background: #2E7D32; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .search-box button:hover { background: #1b5e20; }

        /* Properties */
        .section { padding: 50px 20px; text-align: center; }
        .section h3 { font-size: 1.8em; margin-bottom: 25px; color: #2E7D32; }
        .properties { display: flex; flex-wrap: wrap; justify-content: center; gap: 25px; }
        .property { background: white; border-radius: 14px; box-shadow: 0 6px 14px rgba(0,0,0,0.1); width: 100%; max-width: 300px; transition: 0.3s; overflow: hidden; }
        .property:hover { transform: translateY(-5px); }
        .property img { width: 100%; height: 190px; object-fit: cover; }
        .property .content { padding: 15px; text-align: left; }
        .property h4 { margin: 0 0 8px; color: #222; }
        .property p { margin: 4px 0; color: #555; font-size: 0.95em; }
        .property a { display: block; text-align: center; margin-top: 12px; padding: 10px; background: #2196F3; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .property a:hover { background: #1565C0; }

        /* Features */
        .features { display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-top: 30px; }
        .feature { background: white; padding: 25px; border-radius: 12px; width: 280px; box-shadow: 0 6px 14px rgba(0,0,0,0.1); text-align: left; }
        .feature h4 { margin-bottom: 12px; color: #2196F3; }

        /* Footer */
        footer { background: #222; color: #ccc; text-align: center; padding: 25px; margin-top: 50px; }
        footer a { color: #FF9800; text-decoration: none; }

        /* Upload Btn */
        .upload-btn { padding: 10px 16px; background: #4CAF50; color: white; border-radius: 8px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .upload-btn:hover { background: #388E3C; }

        /* Responsive */
        @media (max-width: 768px) {
            nav { display: none; }
            .menu-toggle { display: block; }
        }
        @media (max-width: 480px) {
            .hero h2 { font-size: 2em; }
            .search-box form { flex-direction: column; }
            .property { width: 90%; }
        }
    </style>
</head>
<body>

<header>
    <h1>🏠 RentConnect</h1>
    <span class="menu-toggle" onclick="toggleMenu()">☰</span>
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
</header>

<!-- Mobile Nav -->
<div class="mobile-nav" id="mobileNav">
    <a href="index.php">Home</a>
    <a href="signup.php">Sign Up</a>
    <a href="login.php">Login</a>
    <a href="contact.php">Contact</a>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'landlord'): ?>
        <a href="upload_property.php">Upload Property</a>
    <?php else: ?>
        <a href="login.php">Upload Property</a>
    <?php endif; ?>
</div>

<section class="hero">
   <br>
   <br>
    <br>
    <br>
    
</section>

<!-- Floating Search -->
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
            <?php while($row = $properties->fetch_assoc()): ?>
                <div class="property">
                    <img src="get_image.php?id=<?php echo $row['id']; ?>" alt="Property">
                    <div class="content">
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                        <p>💲 $<?php echo number_format($row['price']); ?></p>
                        <p>📞 <?php echo htmlspecialchars($row['contact']); ?></p>
                        <a href="login.php">View Details</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No properties found.</p>
        <?php endif; ?>
    </div>
</section>

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

<script>
function toggleMenu() {
    var menu = document.getElementById("mobileNav");
    menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
}
</script>

</body>
</html>
