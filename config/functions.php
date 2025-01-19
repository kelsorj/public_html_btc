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

function getUserRole($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['role'] : 'viewer';
}

function canEditRecipe($recipe_user_id) {
    if (!isset($_SESSION['user_id'])) return false;
    
    $role = getUserRole($_SESSION['user_id']);
    
    // Admin can edit all recipes
    if ($role === 'admin') return true;
    
    // Editor can edit all recipes
    if ($role === 'editor') return true;
    
    // Recipe owner can edit their own recipes
    if ($role === 'viewer' && $_SESSION['user_id'] === $recipe_user_id) return true;
    
    return false;
}

function canDeleteRecipe($recipe_user_id) {
    if (!isset($_SESSION['user_id'])) return false;
    
    $role = getUserRole($_SESSION['user_id']);
    
    // Only admin can delete any recipe
    if ($role === 'admin') return true;
    
    // Recipe owner can delete their own recipes
    if ($_SESSION['user_id'] === $recipe_user_id) return true;
    
    return false;
}
?> 