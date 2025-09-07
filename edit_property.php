<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: landlord_dashboard.php");
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property details
$stmt = $conn->prepare("SELECT * FROM properties WHERE id=? AND owner_id=?");
$stmt->bind_param("ii", $property_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Property not found.");
}

$property = $result->fetch_assoc();

// Handle form submission
if (isset($_POST['update_property'])) {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];

    // Handle new photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $image_name = time() . '_' . $_FILES['photo']['name'];
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($image_name);

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            @unlink("uploads/" . $property['photo']); // delete old photo
        } else {
            $image_name = $property['photo']; // keep old photo if upload failed
        }
    } else {
        $image_name = $property['photo'];
    }

    // Update database
    $stmt = $conn->prepare("UPDATE properties SET title=?, location=?, price=?, contact=?, photo=? WHERE id=? AND owner_id=?");
    $stmt->bind_param("ssdssii", $title, $location, $price, $contact, $image_name, $property_id, $landlord_id);
    $stmt->execute();

    header("Location: landlord_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Property - RentConnect</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        input, textarea { padding: 5px; margin: 5px 0; width: 100%; }
        img { max-width: 200px; margin-top: 10px; }
    </style>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imgPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</head>
<body>
    <h2>Edit Property</h2>
    <a href="landlord_dashboard.php">← Back to Dashboard</a>
    <form method="post" enctype="multipart/form-data">
        <label>Property Title:</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($property['title']); ?>" required>

        <label>Location:</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($property['location']); ?>" required>

        <label>Price (USD):</label>
        <input type="number" name="price" step="0.01" value="<?php echo $property['price']; ?>" required>

        <label>Contact Info:</label>
        <input type="text" name="contact" value="<?php echo htmlspecialchars($property['contact']); ?>" required>

        <label>Photo:</label>
        <input type="file" name="photo" accept="image/*" onchange="previewImage(this)">
        <br>
        <img id="imgPreview" src="uploads/<?php echo htmlspecialchars($property['photo']); ?>" alt="Property">

        <input type="submit" name="update_property" value="Update Property">
    </form>
</body>
</html>
