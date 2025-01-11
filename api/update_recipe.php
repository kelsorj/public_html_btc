<?php
ob_start(); // Start output buffering
session_start();
require_once '../config/database.php';

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
if (!isset($_POST['recipe_id']) || !isset($_POST['title']) || !isset($_POST['category_id'])) {
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
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
        header('Location: ../edit_recipe.php?id=' . $recipe_id);
        exit;
    }
    
    // Generate unique filename using content hash and timestamp
    $file_content = file_get_contents($file['tmp_name']);
    $content_hash = hash('sha256', $file_content . time());
    $new_filename = $content_hash . '.' . $file_extension;
    $upload_path = '../uploads/' . $new_filename;
    
    // Check if file with same hash already exists
    while (file_exists($upload_path)) {
        // If exists, add random string to hash and try again
        $content_hash = hash('sha256', $file_content . time() . rand(1000, 9999));
        $new_filename = $content_hash . '.' . $file_extension;
        $upload_path = '../uploads/' . $new_filename;
    }
    
    // Move file to uploads directory
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        $image_path = 'uploads/' . $new_filename;
        
        // Delete old image if exists
        if ($old_image_path) {
            $old_file = '../' . $old_image_path;
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
    } else {
        $_SESSION['error_message'] = 'Error uploading file.';
        header('Location: ../edit_recipe.php?id=' . $recipe_id);
        exit;
    }
}

// Add this before updating the recipe
$category_id = $_POST['category_id'];

// Handle new category creation
if ($category_id === 'new' && !empty($_POST['new_category'])) {
    $new_category = trim($_POST['new_category']);
    
    // Check if category already exists
    $check_query = "SELECT id FROM categories WHERE name = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $new_category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Category already exists, use its ID
        $category_id = $result->fetch_assoc()['id'];
    } else {
        // Create new category
        $insert_query = "INSERT INTO categories (name) VALUES (?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("s", $new_category);
        $stmt->execute();
        $category_id = $conn->insert_id;
    }
}

// Update recipe
$conn->begin_transaction();

try {
    // Update recipe details
    $update_query = "UPDATE recipes SET 
                    title = ?, 
                    category_id = ?,
                    instructions = ?
                    " . ($image_path ? ", image_path = ?" : "") . "
                    WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    if ($image_path) {
        $stmt->bind_param("sissi", $_POST['title'], $category_id, $_POST['instructions'], $image_path, $recipe_id);
    } else {
        $stmt->bind_param("sisi", $_POST['title'], $category_id, $_POST['instructions'], $recipe_id);
    }
    $stmt->execute();

    // Delete existing ingredients
    $delete_query = "DELETE FROM ingredients WHERE recipe_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    // Insert new ingredients
    if (isset($_POST['ingredients']) && is_array($_POST['ingredients'])) {
        $insert_query = "INSERT INTO ingredients (recipe_id, name, amount, unit) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        foreach ($_POST['ingredients'] as $ingredient) {
            if (!empty($ingredient['name']) && isset($ingredient['amount']) && !empty($ingredient['unit'])) {
                $stmt->bind_param("isss", $recipe_id, $ingredient['name'], $ingredient['amount'], $ingredient['unit']);
                $stmt->execute();
            }
        }
    }

    $conn->commit();
    
    // Clean output buffer before sending headers
    ob_end_clean();
    
    // Redirect with success message
    $_SESSION['success_message'] = 'Recipe updated successfully';
    header('Location: ../recipe.php?id=' . $recipe_id);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating recipe: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error updating recipe. Please try again.';
    
    // Clean output buffer before sending headers
    ob_end_clean();
    
    header('Location: ../edit_recipe.php?id=' . $recipe_id);
    exit;
}
?> 