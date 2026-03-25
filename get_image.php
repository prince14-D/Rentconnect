<?php
include "db.php";
include "image_response_helper.php";

$id = rc_requirePositiveIntQueryParam("id");

$stmt = $conn->prepare("SELECT photo_blob FROM properties WHERE id = ?");
if (!$stmt) {
    rc_outputFallbackImage();
}

$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    rc_outputFallbackImage();
}

$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($imageData);
    $stmt->fetch();

    if ($imageData) {
        rc_outputImageBinary($imageData, null);
    }
}

rc_outputFallbackImage();
?>
