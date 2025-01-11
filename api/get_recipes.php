<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build query
$query = "SELECT r.*, c.name as category_name, 
          (SELECT COUNT(*) FROM ingredients WHERE recipe_id = r.id) as ingredients_count 
          FROM recipes r 
          LEFT JOIN categories c ON r.category_id = c.id 
          WHERE 1=1";

if ($search) {
    $query .= " AND r.title LIKE '%$search%'";
}

if ($category) {
    $query .= " AND r.category_id = $category";
}

$query .= " ORDER BY r.created_at DESC";

$result = $conn->query($query);
$recipes = [];

while ($row = $result->fetch_assoc()) {
    $recipes[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'category_name' => $row['category_name'],
        'ingredients_count' => $row['ingredients_count'],
        'image_path' => $row['image_path']
    ];
}

header('Content-Type: application/json');
echo json_encode($recipes);
?> 