<?php
include "db.php";

if (isset($_GET['img_id'])) {
    $img_id = intval($_GET['img_id']);

    $stmt = $conn->prepare("SELECT image, mime_type FROM property_images WHERE id=?");
    $stmt->bind_param("i", $img_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        header("Content-Type: " . $row['mime_type']);
        echo $row['image'];
    } else {
        header("Content-Type: image/png");
        readfile("images/no-image.png");
    }
}
?>
