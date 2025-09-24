<?php
include "db.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT image FROM property_images WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($imageData);
    if ($stmt->fetch()) {
        header("Content-Type: image/jpeg");
        echo $imageData;
    } else {
        header("Content-Type: image/png");
        readfile("images/no-image.png");
    }
    $stmt->close();
}
?>
