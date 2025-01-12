<?php
function getRandomBackground() {
    $backgrounds = [
        'backgrounds/1.jpg',
        'backgrounds/2.jpg',
        'backgrounds/3.jpg',
        'backgrounds/4.jpg'
    ];
    return $backgrounds[array_rand($backgrounds)];
}

function optimizeImage($sourcePath, $targetPath, $maxWidth = 1200, $quality = 80) {
    list($width, $height, $type) = getimagesize($sourcePath);
    
    // Calculate new dimensions
    $ratio = $width / $height;
    $newWidth = min($width, $maxWidth);
    $newHeight = $newWidth / $ratio;
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Handle transparency for PNG
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Load source image
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    // Resize
    imagecopyresampled(
        $newImage, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $width, $height
    );
    
    // Save optimized image
    $result = imagejpeg($newImage, $targetPath, $quality);
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}
?> 