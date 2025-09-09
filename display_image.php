<?php
include "db.php";

if (!isset($_GET['img_id'])) {
    die('No image ID provided.');
}

$img_id = intval($_GET['img_id']);
$stmt = $conn->prepare("SELECT image_path FROM property_images WHERE id=?");
$stmt->bind_param("i", $img_id);
$stmt->execute();
$stmt->bind_result($path);
$stmt->fetch();

if ($path && file_exists($path)) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    header("Content-Type: $mime");
    readfile($path);
} else {
    // fallback image
    header("Content-Type: image/png");
    readfile("images/no-image.png");
}
?>
