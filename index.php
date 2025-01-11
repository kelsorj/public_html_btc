<?php
session_start();
require_once 'config/database.php';

// Add debugging
error_log("Index.php - Session user_id: " . $_SESSION['user_id'] ?? 'not set');

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// If not logged in, redirect to login page
if (!$logged_in) {
    header('Location: login.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burning to Cook</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php">Burning to Cook</a>
            </div>
            <div class="nav-links">
                <a href="recipes.php">Recipes</a>
                <a href="introduction.php">Introduction</a>
                <?php if ($logged_in): ?>
                    <button class="btn btn-primary" onclick="location.href='add_recipe.php'">Add Recipe</button>
                    <button class="btn btn-secondary" onclick="location.href='logout.php'">Logout</button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="location.href='login.php'">Login</button>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <div class="search-container">
            <input type="text" id="recipe-search" placeholder="Search recipes...">
            <select id="category-filter">
                <option value="">Filter by category</option>
                <?php
                $query = "SELECT * FROM categories ORDER BY name";
                $result = $conn->query($query);
                while ($category = $result->fetch_assoc()) {
                    echo "<option value='{$category['id']}'>{$category['name']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="recipe-grid" id="recipe-container">
            <!-- Recipes will be loaded here via JavaScript -->
        </div>
    </main>

    <script src="js/main.js"></script>
</body>
</html> 