<?php
session_start();
include "db.php";

// Get property ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$property_id = intval($_GET['id']);

// Fetch property
$stmt = $conn->prepare("
    SELECT p.*, u.name AS owner_name, u.email AS owner_email 
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($property['title']); ?> - RentConnect</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f9; color: #333; }
        header { background: #4CAF50; color: white; padding: 20px 40px; text-align: center; }
        header h1 { margin: 0; font-size: 2em; }
        nav { margin-top: 10px; }
        nav a { color: white; text-decoration: none; margin: 0 15px; font-weight: bold; }
        .container { width: 90%; max-width: 1000px; margin: 40px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .property-img { width: 100%; height: 400px; object-fit: cover; border-radius: 10px; margin-bottom: 20px; }
        h2 { margin: 10px 0; color: #333; }
        p { margin: 8px 0; }
        .price { font-size: 1.5em; color: #4CAF50; margin: 10px 0; }
        .actions { margin-top: 20px; }
        .actions a { display: inline-block; padding: 12px 20px; margin-right: 10px; border-radius: 5px; color: white; text-decoration: none; }
        .btn-request { background: #4CAF50; }
        .btn-favorite { background: #FF9800; }
        .btn-login { background: #2196F3; }
        .owner-box { margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 5px solid #4CAF50; }
    </style>
</head>
<body>

<header>
    <h1>RentConnect</h1>
    <nav>
        <a href="index.php">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $_SESSION['role']=='landlord' ? 'landlord_dashboard.php' : 'renter_dashboard.php'; ?>">Dashboard</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="signup.php">Sign Up</a>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
</header>

<div class="container">
    <img src="uploads/<?php echo htmlspecialchars($property['photo']); ?>" alt="Property" class="property-img">
    
    <h2><?php echo htmlspecialchars($property['title']); ?></h2>
    <p class="price">💲 $<?php echo number_format($property['price']); ?></p>
    <p>📍 Location: <?php echo htmlspecialchars($property['location']); ?></p>
    <p><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>

    <div class="actions">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="login.php" class="btn-login">Login to Request</a>
            <a href="signup.php" class="btn-favorite">Sign Up</a>
        <?php elseif ($_SESSION['role'] == 'renter'): ?>
            <a href="renter_dashboard.php?request_property=<?php echo $property['id']; ?>" class="btn-request">Request to Rent</a>
            <a href="renter_dashboard.php?favorite_property=<?php echo $property['id']; ?>" class="btn-favorite">❤ Add to Favorites</a>
        <?php elseif ($_SESSION['role'] == 'landlord' && $_SESSION['user_id'] == $property['owner_id']): ?>
            <p><strong>✅ This is your property.</strong></p>
        <?php endif; ?>
    </div>

    <div class="owner-box">
        <h3>Contact Landlord</h3>
        <p>Name: <?php echo htmlspecialchars($property['owner_name']); ?></p>
        <p>Email: <?php echo htmlspecialchars($property['owner_email']); ?></p>
        <small>(You’ll be able to chat after your request is approved.)</small>
    </div>
</div>

</body>
</html>
