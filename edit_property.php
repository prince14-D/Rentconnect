<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";
$prop_id = intval($_GET['id']);

// Fetch existing property
$stmt = $conn->prepare("SELECT * FROM properties WHERE id=? AND owner_id=?");
$stmt->bind_param("ii", $prop_id, $landlord_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    die("Property not found.");
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];

    $photo = $property['photo']; // keep old photo by default
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $image_name = time() . '_' . basename($_FILES['photo']['name']);
        $target_dir = "uploads/";
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $image_name)) {
            @unlink("uploads/" . $property['photo']); // delete old photo
            $photo = $image_name;
        }
    }

    $stmt = $conn->prepare("UPDATE properties SET title=?, location=?, price=?, contact=?, photo=? WHERE id=? AND owner_id=?");
    $stmt->bind_param("ssdssii", $title, $location, $price, $contact, $photo, $prop_id, $landlord_id);
    $stmt->execute();
    $message = "✅ Property updated successfully!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Property - RentConnect</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; display:flex; justify-content:center; align-items:center; height:100vh; }
        .form-box { background:white; padding:30px; border-radius:10px; width:400px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align:center; color:#FF9800; }
        input { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:1px solid #ccc; }
        button { width:100%; padding:12px; background:#FF9800; color:white; border:none; border-radius:5px; cursor:pointer; font-size:1.1em; }
        button:hover { background:#e68a00; }
        img { max-width:100%; margin-bottom:10px; border-radius:5px; }
        .message { text-align:center; color:green; }
        a.back { display:block; margin-top:10px; text-align:center; text-decoration:none; color:#2196F3; }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>Edit Property</h2>
        <?php if($message) echo "<p class='message'>$message</p>"; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="text" name="title" value="<?php echo htmlspecialchars($property['title']); ?>" required>
            <input type="text" name="location" value="<?php echo htmlspecialchars($property['location']); ?>" required>
            <input type="number" step="0.01" name="price" value="<?php echo $property['price']; ?>" required>
            <input type="text" name="contact" value="<?php echo htmlspecialchars($property['contact']); ?>" required>
            <img src="uploads/<?php echo $property['photo']; ?>" alt="Current Photo">
            <input type="file" name="photo" accept="image/*">
            <button type="submit">Update Property</button>
        </form>
        <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
    </div>
</body>
</html>
