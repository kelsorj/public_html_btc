<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Recipe - Burning to Cook</title>
    <link rel="stylesheet" href="css/style.css">
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
            <div class="nav-links">
                <a href="introduction.php">Introduction</a>
                <button class="btn btn-secondary" onclick="location.href='logout.php'">Logout</button>
            </div>
        </nav>
    </header>

    <main>
        <div class="recipe-form">
            <div class="form-header">
                <h1>Add New Recipe</h1>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>

            <form id="add-recipe-form" method="POST" action="api/create_recipe.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Recipe Title</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <div class="category-selection">
                        <select id="category" name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="new">+ Add New Category</option>
                        </select>
                        <div id="new-category-input" style="display: none;">
                            <input type="text" id="new-category-name" name="new_category" placeholder="Enter new category name">
                            <button type="button" class="btn btn-secondary" onclick="cancelNewCategory()">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="image">Recipe Image (optional)</label>
                    <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/webp">
                    <small>Accepted formats: JPG, PNG, WEBP</small>
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="10" required></textarea>
                </div>

                <div class="form-group">
                    <label>Ingredients</label>
                    <div id="ingredients-container">
                        <!-- Ingredient rows will be added here -->
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addIngredientRow()">Add Ingredient</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('bulk-input-modal').style.display='block'">
                        Bulk Add Ingredients
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Recipe</button>
                </div>
            </form>
        </div>
    </main>

    <!-- Bulk Ingredients Modal -->
    <div id="bulk-input-modal" class="modal">
        <div class="modal-content">
            <h3>Bulk Add Ingredients</h3>
            <p>Paste your ingredients below, one per line. Format: "amount unit ingredient"<br>
               Examples:<br>
               2 cups flour<br>
               1/2 tsp salt<br>
               3 large eggs<br>
               1 onion, diced</p>
            <textarea id="bulk-ingredients" rows="10" placeholder="Paste ingredients here..."></textarea>
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('bulk-input-modal').style.display='none'">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addBulkIngredients()">Add Ingredients</button>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
        // Add first ingredient row on page load
        document.addEventListener('DOMContentLoaded', function() {
            addIngredientRow();
        });

        function addIngredientRow(amount = '', unit = '', name = '') {
            const container = document.getElementById('ingredients-container');
            const row = document.createElement('div');
            row.className = 'ingredient-row';
            row.innerHTML = `
                <input type="text" name="ingredients[${container.children.length}][amount]" placeholder="Amount" value="${amount}">
                <input type="text" name="ingredients[${container.children.length}][unit]" placeholder="Unit" value="${unit}">
                <input type="text" name="ingredients[${container.children.length}][name]" placeholder="Ingredient" required value="${name}">
                <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(row);
        }

        // Category selection handling
        document.getElementById('category').addEventListener('change', function() {
            const newCategoryInput = document.getElementById('new-category-input');
            if (this.value === 'new') {
                newCategoryInput.style.display = 'flex';
                this.style.display = 'none';
            }
        });

        function cancelNewCategory() {
            const categorySelect = document.getElementById('category');
            const newCategoryInput = document.getElementById('new-category-input');
            categorySelect.value = '';
            categorySelect.style.display = 'block';
            newCategoryInput.style.display = 'none';
            document.getElementById('new-category-name').value = '';
        }
    </script>
</body>
</html> 