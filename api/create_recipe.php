<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Handle new category if provided
    $category_id = $_POST['category_id'];
    if ($category_id === 'new' && !empty($_POST['new_category'])) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $_POST['new_category']);
        $stmt->execute();
        $category_id = $conn->insert_id;
    }

    // Handle image upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
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

    // Insert recipe
    $stmt = $conn->prepare("INSERT INTO recipes (title, instructions, image_path, user_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $_POST['title'], $_POST['instructions'], $image_path, $_SESSION['user_id']);
    $stmt->execute();
    $recipe_id = $conn->insert_id;

    // Insert categories
    if (isset($_POST['category_ids']) && is_array($_POST['category_ids'])) {
        $insert_category = "INSERT INTO recipe_categories (recipe_id, category_id) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_category);
        foreach ($_POST['category_ids'] as $category_id) {
            $stmt->bind_param("ii", $recipe_id, $category_id);
            $stmt->execute();
        }
    }

    // Insert ingredients
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
            error_log("Creating ingredient: " . json_encode([
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
    
    $_SESSION['success_message'] = 'Recipe created successfully';
    header('Location: ../index.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error creating recipe: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error creating recipe: ' . $e->getMessage();
    header('Location: ../add_recipe.php');
    exit;
} 