<?php
include "app_init.php";
include "image_response_helper.php";

$id = rc_requirePositiveIntQueryParam("id");

$image = rc_mig_get_property_image_by_id($conn, $id);
if (!$image || ($image['image'] ?? '') === '') {
    rc_outputFallbackImage();
}

rc_outputImageBinary((string) $image['image'], (string) ($image['mime_type'] ?? ''));
?>
