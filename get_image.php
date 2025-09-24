<?php
include "db.php";

if (!isset($_GET['id'])) {
    exit("No image ID");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT photo_blob FROM properties WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($imageData);
    $stmt->fetch();

    if ($imageData) {
        // TODO: If you want to support PNG/GIF too, detect MIME type from data.
        header("Content-Type: image/jpeg");
        echo $imageData;
        exit;
    }
}

// If no image found, send placeholder
header("Content-Type: image/png");
readfile("images/no-image.png"); 
exit;
?>
