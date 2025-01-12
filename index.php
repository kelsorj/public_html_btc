<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
error_log("Index.php - Session started");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in, redirecting to login.php");
    header('Location: login.php');
    exit;
}

error_log("User is logged in with ID: " . $_SESSION['user_id']);

require_once 'config/database.php';
require_once 'config/functions.php';

// Fetch all categories for the filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);

// Fetch recipes with their categories
$query = "SELECT DISTINCT r.*, GROUP_CONCAT(TRIM(c.name) SEPARATOR ', ') as category_names 
          FROM recipes r 
          LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id 
          LEFT JOIN categories c ON rc.category_id = c.id 
          WHERE r.user_id = " . $_SESSION['user_id'] . " 
          GROUP BY r.id 
          ORDER BY r.title ASC";

$recipes = $conn->query($query);

if (!$recipes) {
    die("Error fetching recipes: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burning to Cook</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-image: url('<?php echo getRandomBackground(); ?>');
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php">Burning to Cook</a>
            </div>
            <div class="search-bar">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search" placeholder="Search recipes...">
                </div>
                <div class="category-filter">
                    <select id="category-filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="nav-links">
                <a href="introduction.php">Introduction</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="btn btn-primary" onclick="location.href='add_recipe.php'">Add Recipe</button>
                    <button class="btn btn-secondary" onclick="location.href='logout.php'">Logout</button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="location.href='login.php'">Login</button>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <div class="recipe-grid">
            <?php if (empty($recipes)): ?>
                <div class="no-recipes">No recipes found</div>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <!-- Debug output -->
                    <!-- <?php error_log("Categories for " . $recipe['title'] . ": " . $recipe['category_names']); ?> -->
                    <div class="recipe-card" id="recipe-<?php echo $recipe['id']; ?>" data-categories="<?php echo htmlspecialchars($recipe['category_names']); ?>">
                        <a href="recipe.php?id=<?php echo $recipe['id']; ?>">
                            <?php if ($recipe['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($recipe['image_path']); ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                            <?php else: ?>
                                <div class="placeholder-image"></div>
                            <?php endif; ?>
                            <div class="recipe-content">
                                <h3><?php echo htmlspecialchars($recipe['title']); ?></h3>
                                <span class="categories"><?php echo htmlspecialchars($recipe['category_names']); ?></span>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
    // Search and filter functionality
    const searchInput = document.getElementById('search');
    const categoryFilter = document.getElementById('category-filter');
    const recipeCards = document.querySelectorAll('.recipe-card');

    function filterRecipes() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value.toLowerCase();

        recipeCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const categories = card.querySelector('.categories')?.textContent.toLowerCase() || '';
            
            const matchesSearch = title.includes(searchTerm);
            const matchesCategory = !selectedCategory || categories.includes(selectedCategory);

            card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });
    }

    searchInput.addEventListener('input', filterRecipes);
    categoryFilter.addEventListener('change', filterRecipes);

    document.addEventListener('DOMContentLoaded', function() {
        // If there's a hash in the URL, scroll to that recipe card
        if (window.location.hash) {
            const targetCard = document.querySelector(window.location.hash);
            if (targetCard) {
                setTimeout(() => {
                    targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Add a brief highlight effect
                    targetCard.style.transition = 'background-color 0.5s';
                    targetCard.style.backgroundColor = '#fff9e6';
                    setTimeout(() => {
                        targetCard.style.backgroundColor = '';
                    }, 1000);
                }, 100);
            }
        }
    });
    </script>
</body>
</html> 