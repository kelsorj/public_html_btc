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
    $upload_dir = '../uploads/';
    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
        $image_path = 'uploads/' . $new_filename;
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
        $stmt->bind_param("sissi", $_POST['title'], $_POST['category_id'], $_POST['instructions'], $image_path, $recipe_id);
    } else {
        $stmt->bind_param("sssi", $_POST['title'], $_POST['category_id'], $_POST['instructions'], $recipe_id);
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