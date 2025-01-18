<?php
ob_end_clean(); // Clean any existing output buffers
ob_start(); // Start fresh output buffer
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

error_log("Starting create_recipe.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Not a POST request: " . $_SERVER['REQUEST_METHOD']);
    header('Location: ../add_recipe.php');
    exit;
}

// Debug logging
error_log("POST data received: " . print_r($_POST, true));
error_log("FILES data received: " . print_r($_FILES, true));

// Start transaction
$conn->begin_transaction();

try {
    error_log("Starting recipe creation");
    
    // Handle categories
    $category_ids = isset($_POST['category_ids']) ? $_POST['category_ids'] : [];
    error_log("Categories received: " . print_r($category_ids, true));

    // Handle new category if provided
    $category_id = isset($_POST['category_id']) ? $_POST['category_id'] : null;
    if ($category_id === 'new' && !empty($_POST['new_category'])) {
        error_log("Creating new category: " . $_POST['new_category']);
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $_POST['new_category']);
        $stmt->execute();
        $category_id = $conn->insert_id;
    }

    // Handle image upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        error_log("Processing image upload");
        $file = $_FILES['image'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!in_array($file_extension, $allowed_types) || !in_array(mime_content_type($file['tmp_name']), $allowed_mimes)) {
            throw new Exception('Invalid file type. Only JPG, PNG and WEBP are allowed.');
        }
        
        // Generate unique filename
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
        } else {
            throw new Exception('Error processing image.');
        }
    }

    error_log("Inserting recipe");
    // Insert recipe
    $stmt = $conn->prepare("INSERT INTO recipes (title, instructions, image_path, user_id) VALUES (?, ?, ?, ?)");
    
    // Combine instructions into a single string with step numbers, preserving line breaks
    $instructions_array = isset($_POST['instructions']) ? $_POST['instructions'] : [];
    $formatted_instructions = '';
    foreach ($instructions_array as $index => $step) {
        $step_number = $index + 1;
        // Replace single newlines with <br> and preserve paragraph breaks
        $formatted_step = trim($step);
        $formatted_step = str_replace("\r\n", "\n", $formatted_step); // Normalize line endings
        $formatted_step = str_replace("\n\n", "<paragraph>", $formatted_step); // Preserve paragraphs
        $formatted_step = str_replace("\n", "<br>", $formatted_step); // Convert single line breaks
        $formatted_step = str_replace("<paragraph>", "\n\n", $formatted_step); // Restore paragraphs
        $formatted_instructions .= "Step {$step_number}: " . $formatted_step . "\n\n";
    }
    
    $stmt->bind_param("sssi", $_POST['title'], $formatted_instructions, $image_path, $_SESSION['user_id']);
    $stmt->execute();
    $recipe_id = $conn->insert_id;
    error_log("Recipe created with ID: " . $recipe_id);

    // Handle instruction images
    if (isset($_FILES['instruction_images'])) {
        error_log("Processing instruction images");
        $instruction_images = $_FILES['instruction_images'];
        
        // Prepare statement for instruction images
        $stmt = $conn->prepare("INSERT INTO instruction_images (recipe_id, image_path, step_number) VALUES (?, ?, ?)");
        
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
                    $image_path = 'uploads/' . $new_filename;
                    $step_number = $index + 1;
                    $stmt->bind_param("isi", $recipe_id, $image_path, $step_number);
                    $stmt->execute();
                    error_log("Saved instruction image for step {$step_number}: {$image_path}");
                }
            }
        }
    }

    // Insert categories
    if (!empty($category_ids)) {
        error_log("Inserting categories: " . print_r($category_ids, true));
        $insert_category = "INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_category);
        foreach ($category_ids as $category_id) {
            $stmt->bind_param("ii", $recipe_id, $category_id);
            $stmt->execute();
        }
    }

    // Insert ingredients
    if (isset($_POST['ingredients']) && is_array($_POST['ingredients'])) {
        error_log("Processing ingredients");
        $stmt = $conn->prepare("INSERT INTO ingredients (recipe_id, name, amount, unit, section) VALUES (?, ?, ?, ?, ?)");
        
        // Ensure we have at least one valid ingredient
        $has_ingredients = false;
        
        foreach ($_POST['ingredients'] as $index => $ingredient) {
            error_log("Processing ingredient: " . print_r($ingredient, true));
            // Skip if ingredient name is empty or not set
            if (!isset($ingredient['name']) || trim($ingredient['name']) === '') {
                continue;
            }
            
            $has_ingredients = true;
            
            // Clean and prepare values
            $name = trim($ingredient['name']);
            $amount = isset($ingredient['amount']) ? trim($ingredient['amount']) : '';
            $unit = isset($ingredient['unit']) ? trim($ingredient['unit']) : '';
            $section = isset($ingredient['section']) ? trim($ingredient['section']) : '';
            
            if (!$stmt->bind_param("issss", $recipe_id, $name, $amount, $unit, $section)) {
                throw new Exception("Error binding parameters for ingredient: " . $stmt->error);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error inserting ingredient: " . $stmt->error);
            }
        }
        
        if (!$has_ingredients) {
            throw new Exception("Please add at least one ingredient with a name to the recipe");
        }
    } else {
        error_log("No ingredients found in POST data");
        throw new Exception("Please add at least one ingredient to the recipe");
    }

    $conn->commit();
    error_log("Recipe creation successful, redirecting to index.php");
    
    $_SESSION['success_message'] = 'Recipe created successfully';
    ob_end_clean(); // Clean any output before redirect
    header("Location: ../index.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error creating recipe: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error creating recipe: ' . $e->getMessage();
    ob_end_clean(); // Clean any output before redirect
    header("Location: ../add_recipe.php");
    exit();
}
?>