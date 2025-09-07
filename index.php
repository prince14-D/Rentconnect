<?php
session_start();
include "db.php";

// Handle search
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

$sql .= "ORDER BY created_at DESC LIMIT 6"; // latest 6 properties

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
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; background: #f8fafc; color: #333; }
        header { background: linear-gradient(90deg, #4CAF50, #2E7D32); color: white; padding: 20px 50px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { margin: 0; font-size: 2em; }
        nav a { color: white; text-decoration: none; margin: 0 15px; font-weight: bold; transition: 0.3s; }
        nav a:hover { text-decoration: underline; }
        .hero { background: url('images/home-bg.jpg') no-repeat center center/cover; color: white; text-align: center; padding: 100px 20px; position: relative; }
        .hero::after { content: ""; position: absolute; inset: 0; background: rgba(0,0,0,0.4); }
        .hero h2, .hero p, .hero .buttons { position: relative; z-index: 2; }
        .hero h2 { font-size: 2.8em; margin-bottom: 15px; }
        .hero p { font-size: 1.2em; max-width: 600px; margin: auto; }
        .hero .buttons { margin-top: 30px; }
        .hero a { padding: 12px 25px; margin: 0 10px; border-radius: 30px; color: white; text-decoration: none; font-size: 1.1em; transition: 0.3s; }
        .hero a.signup { background: #FF9800; }
        .hero a.login { background: #2196F3; }
        .hero a:hover { opacity: 0.9; }
        .section { padding: 60px 40px; text-align: center; }
        .section h3 { font-size: 2em; margin-bottom: 30px; color: #2E7D32; }
        .features { display: flex; justify-content: center; gap: 40px; flex-wrap: wrap; }
        .feature { background: white; padding: 25px; border-radius: 12px; width: 300px; box-shadow: 0 6px 14px rgba(0,0,0,0.1); transition: 0.3s; }
        .feature:hover { transform: translateY(-5px); }
        .feature h4 { margin-bottom: 15px; color: #2196F3; }
        .search-box { background: white; padding: 25px; border-radius: 12px; width: 80%; margin: 30px auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .search-box form { display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; }
        .search-box input { padding: 12px; border: 1px solid #ccc; border-radius: 8px; width: 200px; }
        .search-box button { padding: 12px 25px; background: #4CAF50; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .search-box button:hover { background: #388E3C; }
        .properties { display: flex; flex-wrap: wrap; justify-content: center; gap: 25px; margin-top: 30px; }
        .property { background: white; padding: 15px; border-radius: 12px; width: 280px; box-shadow: 0 6px 14px rgba(0,0,0,0.1); transition: 0.3s; }
        .property:hover { transform: translateY(-5px); }
        .property img { width: 100%; height: 180px; object-fit: cover; border-radius: 8px; }
        .property h4 { margin: 10px 0; color: #333; }
        .property p { margin: 5px 0; color: #555; }
        .property a { display: inline-block; margin-top: 10px; padding: 10px 18px; background: #2196F3; color: white; border-radius: 8px; text-decoration: none; transition: 0.3s; }
        .property a:hover { background: #1565C0; }
        footer { background: #333; color: white; text-align: center; padding: 20px; margin-top: 40px; }
        footer a { color: #FF9800; text-decoration: none; }
    </style>
</head>
<body>

<header>
    <h1>🏠 RentConnect</h1>
    <nav>
        <a href="index.php">Home</a>
        <a href="signup.php">Sign Up</a>
        <a href="login.php">Login</a>
        <a href="contact.php">Contact</a>
    </nav>
</header>

<section class="hero">
    <h2>Find Your Dream Home in Liberia</h2>
    <p>RentConnect connects renters with landlords across Liberia.  
       Search homes, apartments, and rentals — all in one place.</p>
    <div class="buttons">
        <a href="signup.php" class="signup">Get Started</a>
        <a href="login.php" class="login">Login</a>
    </div>
</section>

<section class="section">
    <h3>Search Properties</h3>
    <div class="search-box">
        <form method="get" action="">
            <input type="text" name="location" placeholder="Enter location" value="<?php echo htmlspecialchars($search_location); ?>">
            <input type="number" name="min_price" placeholder="Min Price" value="<?php echo $min_price ?: ''; ?>">
            <input type="number" name="max_price" placeholder="Max Price" value="<?php echo $max_price ?: ''; ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="properties">
        <?php if ($properties->num_rows > 0): ?>
            <?php while($row = $properties->fetch_assoc()): ?>
                <div class="property">
                    <img src="uploads/<?php echo htmlspecialchars($row['photo']); ?>" alt="Property">
                    <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                    <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                    <p>💲 $<?php echo number_format($row['price']); ?></p>
                    <a href="login.php">View Details</a>
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
            <p>Browse verified homes and apartments in Liberia.  
            Save favorites, send rental requests, and track approvals in your dashboard.</p>
        </div>
        <div class="feature">
            <h4>For Landlords</h4>
            <p>Upload property details with photos, location, and price.  
            Manage rental requests from potential tenants quickly and easily.</p>
        </div>
        <div class="feature">
            <h4>Secure & Easy</h4>
            <p>Simple signup, secure login, and clear dashboards for both renters and landlords.  
            Rent smarter with RentConnect.</p>
        </div>
    </div>
</section>

<footer>
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="contact.php">Contact Us</a></p>
</footer>

</body>
</html>
