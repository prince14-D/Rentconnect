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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];  // ✅ corrected
    $price = $_POST['price'];
    $contact = $_POST['contact'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = $_POST['description'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        // store image as BLOB
        $imageData = file_get_contents($_FILES['photo']['tmp_name']);

        $stmt = $conn->prepare("INSERT INTO properties 
            (title, location, price, contact, bedrooms, bathrooms, description, photo_blob, owner_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsiissi", 
            $title, 
            $location,   // ✅ fixed
            $price, 
            $contact, 
            $bedrooms, 
            $bathrooms, 
            $description, 
            $imageData, 
            $landlord_id
        );

        if ($stmt->execute()) {
            $message = "✅ Property uploaded successfully!";
        } else {
            $message = "❌ Database error: " . $stmt->error;
        }
    } else {
        $message = "❌ Please upload an image.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Property - RentConnect</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; display:flex; justify-content:center; align-items:center; height:100vh; }
        .form-box { background:white; padding:30px; border-radius:10px; width:420px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        h2 { text-align:center; color:#4CAF50; }
        input, textarea { width:100%; padding:10px; margin:10px 0; border-radius:5px; border:1px solid #ccc; }
        textarea { height:80px; resize:none; }
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
            <input type="number" name="bedrooms" placeholder="Number of Bedrooms">
            <input type="number" name="bathrooms" placeholder="Number of Bathrooms">
            <textarea name="description" placeholder="Property Description"></textarea>
            <input type="file" name="photo" accept="image/*" required>
            <button type="submit">Upload Property</button>
        </form>
        <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
    </div>
</body>
</html>
