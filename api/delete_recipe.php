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

// Check if it's a DELETE request
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get recipe ID from URL
$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$recipe_id) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Recipe ID is required']);
    exit;
}

// Verify recipe belongs to user
$check_query = "SELECT id, image_path FROM recipes WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$recipe = $result->fetch_assoc();

if (!$recipe) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
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