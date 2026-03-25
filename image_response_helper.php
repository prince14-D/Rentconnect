<?php

function rc_outputFallbackImage(): void {
    $fallbackPath = __DIR__ . "/images/no-image.png";
    if (is_file($fallbackPath)) {
        header("Content-Type: image/png");
        header("Cache-Control: public, max-age=300");
        readfile($fallbackPath);
        exit;
    }

    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Image not found.";
    exit;
}

function rc_requirePositiveIntQueryParam(string $key): int {
    if (!isset($_GET[$key]) || !ctype_digit((string) $_GET[$key])) {
        rc_outputFallbackImage();
    }

    $value = (int) $_GET[$key];
    if ($value <= 0) {
        rc_outputFallbackImage();
    }

    return $value;
}

function rc_safeImageMime(?string $mimeType, string $binary): string {
    $allowedMimeTypes = [
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp"
    ];

    if ($mimeType !== null && in_array($mimeType, $allowedMimeTypes, true)) {
        return $mimeType;
    }

    $detectedMime = null;
    if (class_exists("finfo")) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($binary);
    }

    return in_array($detectedMime, $allowedMimeTypes, true) ? $detectedMime : "application/octet-stream";
}

function rc_outputImageBinary(string $binary, ?string $mimeType = null): void {
    if ($binary === "") {
        rc_outputFallbackImage();
    }

    header("Content-Type: " . rc_safeImageMime($mimeType, $binary));
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: public, max-age=86400");
    echo $binary;
    exit;
}
