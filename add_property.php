<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $image_name = time() . '_' . basename($_FILES['photo']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO properties (title, location, price, contact, photo, owner_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdssi", $title, $location, $price, $contact, $image_name, $landlord_id);
            $stmt->execute();
            $message = "✅ Property uploaded successfully!";
        } else {
            $message = "❌ Failed to upload image.";
        }
    } else {
        $message = "❌ Please select an image.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Property - RentConnect</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; display:flex; justify-content:center; align-items:center; height:100vh; }
        .form-box { background:white; padding:30px; border-radius:10px; width:400px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align:center; color:#4CAF50; }
        input { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:1px solid #ccc; }
        button { width:100%; padding:12px; background:#4CAF50; color:white; border:none; border-radius:5px; cursor:pointer; font-size:1.1em; }
        button:hover { background:#43a047; }
        .message { text-align:center; color:red; }
        a.back { display:block; margin-top:10px; text-align:center; text-decoration:none; color:#2196F3; }
    </style>
</head>
<body>
    <div class="form-box">
        <h2>Upload New Property</h2>
        <?php if($message) echo "<p class='message'>$message</p>"; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="Property Title" required>
            <input type="text" name="location" placeholder="Location" required>
            <input type="number" step="0.01" name="price" placeholder="Price (USD)" required>
            <input type="text" name="contact" placeholder="Contact Info" required>
            <input type="file" name="photo" accept="image/*" required>
            <button type="submit">Upload Property</button>
        </form>
        <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
    </div>
</body>
</html>
