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

// Check if it's a DELETE request
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get recipe ID
$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch recipe to check ownership
$query = "SELECT * FROM recipes WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

if (!$recipe) {
    http_response_code(404);
    echo json_encode(['error' => 'Recipe not found']);
    exit;
}

// Check if user has permission to delete this recipe
if (!canDeleteRecipe($recipe['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to delete this recipe']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete ingredients
    $stmt = $conn->prepare("DELETE FROM ingredients WHERE recipe_id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    // Delete notes
    $stmt = $conn->prepare("DELETE FROM recipe_notes WHERE recipe_id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    // Delete family stories
    $stmt = $conn->prepare("DELETE FROM family_stories WHERE recipe_id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    // Delete recipe
    $stmt = $conn->prepare("DELETE FROM recipes WHERE id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();

    // Delete image file if exists
    if ($recipe['image_path']) {
        $image_path = '../' . $recipe['image_path'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting recipe: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Error deleting recipe']);
} 