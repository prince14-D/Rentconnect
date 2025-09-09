<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $description = $_POST['description'] ?? "";

    // Insert property
    $stmt = $conn->prepare("INSERT INTO properties (owner_id, title, location, price, contact, bedrooms, bathrooms, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issdsdds", $landlord_id, $title, $location, $price, $contact, $bedrooms, $bathrooms, $description);

    if ($stmt->execute()) {
        $property_id = $stmt->insert_id;

       // Handle images
if (!empty($_FILES['photos']['name'][0])) {
    $total_files = count($_FILES['photos']['name']);

    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['photos']['error'][$i] == 0) {
            $imageData = file_get_contents($_FILES['photos']['tmp_name'][$i]);
            $mimeType  = mime_content_type($_FILES['photos']['tmp_name'][$i]);

            // prepare insert
            $img_stmt = $conn->prepare("INSERT INTO property_images (property_id, image, mime_type) VALUES (?, ?, ?)");
            
            // second param must be a string for blob
            $img_stmt->bind_param("iss", $property_id, $null, $mimeType);
            $null = NULL;
            $img_stmt->send_long_data(1, $imageData);

            $img_stmt->execute();
        }
    }
}

        $message = "✅ Property uploaded successfully!";
    } else {
        $message = "❌ Failed to upload property: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Property - RentConnect</title>
<style>
body { font-family: Arial, sans-serif; background:#f7f9f7; margin:0; padding:0; }
header { background:#2e7d32; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:600px; margin:30px auto; padding:20px; background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#2e7d32; margin-bottom:20px; }
input, textarea, button { width:100%; padding:10px; margin:10px 0; border-radius:6px; border:1px solid #ccc; font-size:1em; }
textarea { resize:none; height:100px; }
button { background:#2e7d32; color:white; border:none; cursor:pointer; font-size:1.1em; }
button:hover { background:#1b5e20; }
.message { text-align:center; color:green; font-weight:bold; margin-bottom:15px; }
a.back { display:block; text-align:center; margin-top:15px; color:#2e7d32; text-decoration:none; font-weight:bold; }
</style>
</head>
<body>

<header>Upload Property</header>
<div class="container">
    <?php if($message) echo "<p class='message'>$message</p>"; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Property Title" required>
        <input type="text" name="location" placeholder="Location" required>
        <input type="number" step="0.01" name="price" placeholder="Price" required>
        <input type="text" name="contact" placeholder="Contact Info" required>
        <input type="number" name="bedrooms" placeholder="Bedrooms">
        <input type="number" name="bathrooms" placeholder="Bathrooms">
        <textarea name="description" placeholder="Property Description"></textarea>
        <input type="file" name="photos[]" multiple accept="image/*">
        <button type="submit">Upload Property</button>
    </form>

    <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</div>

</body>
</html>
