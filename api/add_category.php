<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || empty(trim($data['name']))) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Category name is required']);
    exit;
}

$category_name = trim($data['name']);

// Check if category already exists
$check_query = "SELECT id FROM categories WHERE name = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("s", $category_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Category already exists']);
    exit;
}

// Insert new category
$insert_query = "INSERT INTO categories (name) VALUES (?)";
$stmt = $conn->prepare($insert_query);
$stmt->bind_param("s", $category_name);

if ($stmt->execute()) {
    $category_id = $conn->insert_id;
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'category_id' => $category_id,
        'message' => 'Category added successfully'
    ]);
} else {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error adding category'
    ]);
} 