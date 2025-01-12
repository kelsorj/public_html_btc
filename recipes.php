<?php
session_start();
require_once 'config/database.php';

// Fetch all categories for the filter
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);

// Fetch all recipes
$recipes_query = "SELECT r.*, c.name as category_name, 
                 (SELECT COUNT(*) FROM ingredients WHERE recipe_id = r.id) as ingredient_count 
                 FROM recipes r 
                 LEFT JOIN categories c ON r.category_id = c.id 
                 ORDER BY r.created_at DESC";
$recipes = $conn->query($recipes_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes - Burning to Cook</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <div class="recipe-card" data-category="<?php echo htmlspecialchars($recipe['category_name']); ?>">
                        <a href="recipe.php?id=<?php echo $recipe['id']; ?>">
                            <?php if ($recipe['image_path']): ?>
                                <div class="recipe-image">
                                    <img src="<?php echo htmlspecialchars($recipe['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="recipe-content">
                                <h2><?php echo htmlspecialchars($recipe['title']); ?></h2>
                                <p><?php echo htmlspecialchars($recipe['category_name']); ?></p>
                                <p><?php echo $recipe['ingredient_count']; ?> ingredients</p>
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
            const title = card.querySelector('h2').textContent.toLowerCase();
            const category = card.dataset.category.toLowerCase();
            const matchesSearch = title.includes(searchTerm);
            const matchesCategory = !selectedCategory || category === selectedCategory;

            card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });
    }

    searchInput.addEventListener('input', filterRecipes);
    categoryFilter.addEventListener('change', filterRecipes);
    </script>
</body>
</html> 