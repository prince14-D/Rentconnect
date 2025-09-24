<?php
session_start();
include "db.php";

// Check if landlord is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = $_POST['description'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $photoName = basename($_FILES['photo']['name']);
        $targetDir = "uploads/";
        $targetFile = $targetDir . $photoName;
        $imageData = file_get_contents($_FILES['photo']['tmp_name']); // for BLOB

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
            // Insert into DB
            $stmt = $conn->prepare("INSERT INTO properties 
                (title, location, price, contact, bedrooms, bathrooms, description, photo, photo_blob, owner_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            $null = NULL; // For blob
            $stmt->bind_param("ssdsiissbi", 
                $title, 
                $location, 
                $price, 
                $contact, 
                $bedrooms, 
                $bathrooms, 
                $description, 
                $photoName, 
                $null, 
                $landlord_id
            );
            $stmt->send_long_data(8, $imageData); // bind blob data

            if ($stmt->execute()) {
                $message = "✅ Property uploaded successfully!";
            } else {
                $message = "❌ Error: " . $stmt->error;
            }
        } else {
            $message = "❌ Failed to upload photo.";
        }
    } else {
        $message = "❌ Please select a photo.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Property - RentConnect</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 40px auto; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2 { color: #2E7D32; text-align: center; }
        form { display: flex; flex-direction: column; gap: 15px; }
        input, textarea, button { padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; }
        textarea { resize: none; height: 80px; }
        button { background: #4CAF50; color: white; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #388E3C; }
        .message { text-align: center; font-weight: bold; margin-bottom: 15px; }
        a.back { text-align: center; display:block; margin-top: 10px; color:#2E7D32; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Upload New Property</h2>
        <?php if ($message): ?>
            <p class="message"><?php echo $message; ?></p>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <input type="text" name="title" placeholder="Property Title" required>
            <input type="text" name="location" placeholder="Location" required>
            <input type="number" step="0.01" name="price" placeholder="Price (USD)" required>
            <input type="text" name="contact" placeholder="Contact Info" required>
            <input type="number" name="bedrooms" placeholder="Number of Bedrooms" required>
            <input type="number" name="bathrooms" placeholder="Number of Bathrooms" required>
            <textarea name="description" placeholder="Property Description"></textarea>
            <input type="file" name="photo" accept="image/*" required>
            <button type="submit">Upload Property</button>
        </form>
        
        <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
    </div>
</body>
</html>
