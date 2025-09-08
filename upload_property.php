<?php
session_start();
include "db.php";

// Check if landlord is logged in (optional)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];

    // Handle photo upload
    $photo = $_FILES['photo']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($photo);

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO properties (title, location, price, photo, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssds", $title, $location, $price, $photo);

        if ($stmt->execute()) {
            $message = "✅ Property uploaded successfully!";
        } else {
            $message = "❌ Error: " . $stmt->error;
        }
    } else {
        $message = "❌ Failed to upload photo.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Property - RentConnect</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        h2 { color: #2E7D32; text-align: center; }
        form { display: flex; flex-direction: column; gap: 15px; }
        input, button { padding: 12px; border: 1px solid #ccc; border-radius: 8px; }
        button { background: #4CAF50; color: white; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #388E3C; }
        .message { text-align: center; font-weight: bold; margin-bottom: 15px; }
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
            <input type="number" name="price" placeholder="Price (USD)" required>
            <input type="file" name="photo" accept="image/*" required>
            <button type="submit">Upload Property</button>
        </form>
    </div>
</body>
</html>
