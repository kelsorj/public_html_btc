<?php
ob_start(); // Start output buffering
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate required fields
if (!isset($_POST['recipe_id']) || !isset($_POST['title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$recipe_id = (int)$_POST['recipe_id'];

// Verify recipe belongs to user
$check_query = "SELECT id FROM recipes WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Handle image upload if provided
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    
    if (!in_array($file_extension, $allowed_types) || !in_array(mime_content_type($file['tmp_name']), $allowed_mimes)) {
        $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG and WEBP are allowed.';
        header('Location: ../edit_recipe.php?id=' . $recipe_id);
        exit;
    }
    
    // Generate unique filename using content hash and timestamp
    $content_hash = hash('sha256', file_get_contents($file['tmp_name']) . time());
    $new_filename = $content_hash . '.jpg'; // Always save as JPG
    $upload_path = '../uploads/' . $new_filename;
    
    // Check if file exists
    while (file_exists($upload_path)) {
        $content_hash = hash('sha256', file_get_contents($file['tmp_name']) . time() . rand(1000, 9999));
        $new_filename = $content_hash . '.jpg';
        $upload_path = '../uploads/' . $new_filename;
    }
    
    // Optimize and save image
    if (optimizeImage($file['tmp_name'], $upload_path)) {
        $image_path = 'uploads/' . $new_filename;
        
        // Delete old image if exists
        if ($old_image_path) {
            $old_file = '../' . $old_image_path;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
    } else {
        $_SESSION['error_message'] = 'Error processing image.';
        header('Location: ../edit_recipe.php?id=' . $recipe_id);
        exit;
    }
}

// Add this before updating the recipe
if (isset($_POST['category_ids']) && !is_array($_POST['category_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Category IDs must be an array']);
    exit;
}

// Update recipe
$conn->begin_transaction();

try {
    // Update recipe details
    $stmt = $conn->prepare("UPDATE recipes SET title = ?, instructions = ?, image_path = ? WHERE id = ? AND user_id = ?");
    
    // Combine instructions into a single string with step numbers, preserving line breaks
    $instructions_array = isset($_POST['instructions']) ? $_POST['instructions'] : [];
    $formatted_instructions = '';
    foreach ($instructions_array as $index => $step) {
        // Replace single newlines with <br> and preserve paragraph breaks
        $formatted_step = trim($step);
        $formatted_step = str_replace("\r\n", "\n", $formatted_step); // Normalize line endings
        $formatted_step = str_replace("\n\n", "<paragraph>", $formatted_step); // Preserve paragraphs
        $formatted_step = str_replace("\n", "<br>", $formatted_step); // Convert single line breaks
        $formatted_step = str_replace("<paragraph>", "\n\n", $formatted_step); // Restore paragraphs
        
        // Only add step numbers for multi-step recipes
        if (count($instructions_array) > 1) {
            $formatted_step = "Step " . ($index + 1) . ": " . $formatted_step;
        }
        $formatted_instructions .= $formatted_step . "\n\n";
    }
    
    if ($image_path) {
        // If new image uploaded, update everything including image_path
        $stmt = $conn->prepare("UPDATE recipes SET title = ?, instructions = ?, image_path = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sssis", $_POST['title'], $formatted_instructions, $image_path, $recipe_id, $_SESSION['user_id']);
    } else {
        // If no new image, keep existing image_path
        $stmt = $conn->prepare("UPDATE recipes SET title = ?, instructions = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssii", $_POST['title'], $formatted_instructions, $recipe_id, $_SESSION['user_id']);
    }
    $stmt->execute();

    // Handle instruction images
    if (isset($_FILES['instruction_images'])) {
        error_log("Processing instruction images");
        $instruction_images = $_FILES['instruction_images'];
        
        // Handle image removals first
        if (isset($_POST['remove_instruction_images'])) {
            foreach ($_POST['remove_instruction_images'] as $step_number) {
                // Delete the image file
                $stmt = $conn->prepare("SELECT image_path FROM instruction_images WHERE recipe_id = ? AND step_number = ?");
                $stmt->bind_param("ii", $recipe_id, $step_number);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $file_path = '../' . $row['image_path'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                
                // Delete the database record
                $stmt = $conn->prepare("DELETE FROM instruction_images WHERE recipe_id = ? AND step_number = ?");
                $stmt->bind_param("ii", $recipe_id, $step_number);
                $stmt->execute();
            }
        }
        
        // Handle new/updated images
        $stmt = $conn->prepare("INSERT INTO instruction_images (recipe_id, image_path, step_number) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)");
        
        foreach ($instruction_images['tmp_name'] as $index => $tmp_name) {
            if ($instruction_images['error'][$index] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $instruction_images['name'][$index],
                    'type' => $instruction_images['type'][$index],
                    'tmp_name' => $tmp_name,
                    'error' => $instruction_images['error'][$index],
                    'size' => $instruction_images['size'][$index]
                ];
                
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate file type
                if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'webp']) || 
                    !in_array(mime_content_type($tmp_name), ['image/jpeg', 'image/png', 'image/webp'])) {
                    continue; // Skip invalid files
                }
                
                // Generate unique filename for instruction image
                $content_hash = hash('sha256', file_get_contents($tmp_name) . time() . $index);
                $new_filename = "instruction_{$content_hash}.jpg";
                $upload_path = '../uploads/' . $new_filename;
                
                // Optimize and save image
                if (optimizeImage($tmp_name, $upload_path)) {
                    // Delete old image if it exists
                    $stmt_old = $conn->prepare("SELECT image_path FROM instruction_images WHERE recipe_id = ? AND step_number = ?");
                    $step_number = $index + 1;
                    $stmt_old->bind_param("ii", $recipe_id, $step_number);
                    $stmt_old->execute();
                    $result = $stmt_old->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $old_path = '../' . $row['image_path'];
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    
                    $image_path = 'uploads/' . $new_filename;
                    $stmt->bind_param("isi", $recipe_id, $image_path, $step_number);
                    $stmt->execute();
                    error_log("Saved instruction image for step {$step_number}: {$image_path}");
                }
            }
        }
    }

    // Update categories
    $delete_categories = "DELETE FROM recipe_categories WHERE recipe_id = ?";
    $stmt = $conn->prepare($delete_categories);
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
        $insert_category = "INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_category);
        foreach ($_POST['category_ids'] as $category_id) {
            $stmt->bind_param("ii", $recipe_id, $category_id);
            $stmt->execute();
        }
    }

    // Delete existing ingredients
    $delete_query = "DELETE FROM ingredients WHERE recipe_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    // Insert new ingredients
    if (isset($_POST['ingredients']) && is_array($_POST['ingredients'])) {
        $stmt = $conn->prepare("INSERT INTO ingredients (recipe_id, name, amount, unit, section) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($_POST['ingredients'] as $ingredient) {
            // Skip if ingredient name is empty
            if (empty($ingredient['name'])) {
                continue;
            }
            
            // Clean and prepare values
            $name = trim($ingredient['name']);
            $amount = isset($ingredient['amount']) ? trim($ingredient['amount']) : '';
            $unit = isset($ingredient['unit']) ? trim($ingredient['unit']) : '';
            $section = isset($ingredient['section']) ? trim($ingredient['section']) : '';
            
            // Debug log
            error_log("Processing ingredient: " . json_encode([
                'recipe_id' => $recipe_id,
                'name' => $name,
                'amount' => $amount,
                'unit' => $unit,
                'section' => $section
            ]));
            
            if (!$stmt->bind_param("issss", $recipe_id, $name, $amount, $unit, $section)) {
                throw new Exception("Error binding parameters: " . $stmt->error);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error inserting ingredient: " . $stmt->error);
            }
        }
    } else {
        throw new Exception("No ingredients provided or invalid format");
    }

    $conn->commit();
    
    // Clean output buffer before sending headers
    ob_end_clean();
    
    // Redirect with success message
    $_SESSION['success_message'] = 'Recipe updated successfully';
    
    // Redirect back to recipe page or index with anchor if specified
    $return_anchor = isset($_POST['return_to']) ? '#' . $_POST['return_to'] : '';
    header('Location: ../recipe.php?id=' . $recipe_id . $return_anchor);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating recipe: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error updating recipe: ' . $e->getMessage();
    
    // Clean output buffer before sending headers
    ob_end_clean();
    
    header('Location: ../edit_recipe.php?id=' . $recipe_id);
    exit;
}
?> 