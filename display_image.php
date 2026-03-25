<?php
include "db.php";
include "image_response_helper.php";

$img_id = rc_requirePositiveIntQueryParam("img_id");

$stmt = $conn->prepare("SELECT image, mime_type FROM property_images WHERE id=? LIMIT 1");
if (!$stmt) {
    rc_outputFallbackImage();
}

$stmt->bind_param("i", $img_id);
if (!$stmt->execute()) {
    rc_outputFallbackImage();
}

$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;

if (!$row || empty($row['image'])) {
    rc_outputFallbackImage();
}

rc_outputImageBinary($row['image'], $row['mime_type'] ?? null);
?>
