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

// Fetch unique categories used in the user's recipes
$query = "SELECT DISTINCT c.* 
          FROM categories c 
          JOIN recipe_categories rc ON c.id = rc.category_id 
          JOIN recipes r ON rc.recipe_id = r.id 
          WHERE r.user_id = ?
          ORDER BY c.name";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

header('Content-Type: application/json');
echo json_encode($categories); 