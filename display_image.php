<?php
include "app_init.php";
include "image_response_helper.php";

$img_id = rc_requirePositiveIntQueryParam("img_id");

$image = rc_mig_get_property_image_by_id($conn, $img_id);
if (!$image || ($image['image'] ?? '') === '') {
    rc_outputFallbackImage();
}

rc_outputImageBinary((string) $image['image'], (string) ($image['mime_type'] ?? ''));
?>
