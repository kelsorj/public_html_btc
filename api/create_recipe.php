<?php
session_start();
require_once '../config/database.php';

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
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }
        
        // Generate unique filename
        $file_content = file_get_contents($file['tmp_name']);
        $content_hash = hash('sha256', $file_content . time());
        $new_filename = $content_hash . '.' . $file_extension;
        $upload_path = '../uploads/' . $new_filename;
        
        // Check if file exists
        while (file_exists($upload_path)) {
            $content_hash = hash('sha256', $file_content . time() . rand(1000, 9999));
            $new_filename = $content_hash . '.' . $file_extension;
            $upload_path = '../uploads/' . $new_filename;
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $image_path = 'uploads/' . $new_filename;
        }
    }

    // Insert recipe
    $stmt = $conn->prepare("INSERT INTO recipes (title, category_id, instructions, image_path, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sissi", $_POST['title'], $category_id, $_POST['instructions'], $image_path, $_SESSION['user_id']);
    $stmt->execute();
    $recipe_id = $conn->insert_id;

    // Insert ingredients
    if (isset($_POST['ingredients']) && is_array($_POST['ingredients'])) {
        $stmt = $conn->prepare("INSERT INTO ingredients (recipe_id, name, amount, unit) VALUES (?, ?, ?, ?)");
        
        foreach ($_POST['ingredients'] as $ingredient) {
            if (!empty($ingredient['name'])) {
                $stmt->bind_param("isss", $recipe_id, $ingredient['name'], $ingredient['amount'], $ingredient['unit']);
                $stmt->execute();
            }
        }
    }

    $conn->commit();
    
    $_SESSION['success_message'] = 'Recipe created successfully';
    header('Location: ../recipe.php?id=' . $recipe_id);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error creating recipe: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error creating recipe: ' . $e->getMessage();
    header('Location: ../add_recipe.php');
    exit;
} 