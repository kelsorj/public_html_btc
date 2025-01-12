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
    <base href="/">
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
                    <label for="category">Categories</label>
                    <div class="category-selection">
                        <select id="category" name="category_ids[]" multiple required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl/Cmd to select multiple categories</small>
                        <button type="button" class="btn btn-secondary" onclick="showNewCategoryModal()">+ Add New Category</button>
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
                    <div id="ingredient-sections">
                        <div class="ingredient-section">
                            <div class="section-header">
                                <input type="text" class="section-title" name="sections[]" placeholder="Section Name (optional)" value="" data-old-value="">
                            </div>
                            <div class="ingredients-container">
                                <!-- Ingredient rows will be added here -->
                            </div>
                            <div class="ingredient-buttons">
                                <button type="button" class="btn btn-secondary" onclick="addIngredientRow(this)">Add Ingredient</button>
                                <button type="button" class="btn btn-secondary" onclick="showBulkAddModal(this)">Bulk Add Ingredients</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addNewSection()">Add New Section</button>
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
            // Initialize existing section title listeners
            const existingTitles = document.querySelectorAll('.section-title');
            existingTitles.forEach(titleInput => {
                titleInput.dataset.oldValue = titleInput.value || '';
                addSectionTitleListener(titleInput);
            });
        });

        function addSectionTitleListener(titleInput) {
            titleInput.addEventListener('change', function() {
                const section = this.closest('.ingredient-section');
                const oldValue = this.dataset.oldValue || '';
                const newValue = this.value || '';
                
                section.querySelectorAll('.ingredient-row input').forEach(input => {
                    const name = input.getAttribute('name');
                    if (name) {
                        const newName = name.replace(
                            `ingredients[${encodeURIComponent(oldValue)}]`,
                            `ingredients[${encodeURIComponent(newValue)}]`
                        );
                        input.setAttribute('name', newName);
                    }
                });
                
                this.dataset.oldValue = newValue;
            });
        }

        function addNewSection() {
            const ingredientSections = document.getElementById('ingredient-sections');
            const newSection = document.createElement('div');
            newSection.className = 'ingredient-section';
            newSection.innerHTML = `
                <div class="section-header">
                    <input type="text" class="section-title" name="sections[]" placeholder="Section Name (optional)" value="" data-old-value="">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="removeSection(this)">Remove Section</button>
                </div>
                <div class="ingredients-container"></div>
                <div class="ingredient-buttons">
                    <button type="button" class="btn btn-secondary" onclick="addIngredientRow(this)">Add Ingredient</button>
                    <button type="button" class="btn btn-secondary" onclick="showBulkAddModal(this)">Bulk Add Ingredients</button>
                </div>
            `;
            ingredientSections.appendChild(newSection);
            
            // Add change event listener to the new section title
            const sectionTitle = newSection.querySelector('.section-title');
            addSectionTitleListener(sectionTitle);
        }

        function removeSection(button) {
            if (confirm('Are you sure you want to remove this section and all its ingredients?')) {
                button.closest('.ingredient-section').remove();
            }
        }

        function addIngredientRow(button) {
            const container = button.closest('.ingredient-section').querySelector('.ingredients-container');
            const section = button.closest('.ingredient-section').querySelector('.section-title');
            const sectionName = section ? section.value || '' : '';
            const ingredientCount = container.children.length;
            
            const row = document.createElement('div');
            row.className = 'ingredient-row';
            row.innerHTML = `
                <input type="text" name="ingredients[${encodeURIComponent(sectionName)}][${ingredientCount}][amount]" placeholder="Amount">
                <input type="text" name="ingredients[${encodeURIComponent(sectionName)}][${ingredientCount}][unit]" placeholder="Unit">
                <input type="text" name="ingredients[${encodeURIComponent(sectionName)}][${ingredientCount}][name]" placeholder="Ingredient" required>
                <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(row);
        }

        function showBulkAddModal(button) {
            const modal = document.getElementById('bulk-input-modal');
            const section = button.closest('.ingredient-section').querySelector('.section-title').value || '';
            // Store reference to the current section
            modal.dataset.currentSection = section;
            modal.style.display = 'block';
        }

        function addBulkIngredients() {
            const modal = document.getElementById('bulk-input-modal');
            const bulkText = document.getElementById('bulk-ingredients').value;
            const section = modal.dataset.currentSection;
            const container = document.querySelector(`.ingredient-section:has(input[value="${section}"]) .ingredients-container`);
            
            if (!container) {
                console.error('Container not found for section:', section);
                return;
            }
            
            const lines = bulkText.split('\n').filter(line => line.trim());
            const startIndex = container.children.length;
            
            lines.forEach((line, index) => {
                // Match pattern: amount unit ingredient OR amount ingredient OR just ingredient
                const match = line.trim().match(/^(?:(\d+(?:\/\d+)?(?:\.\d+)?)\s*)?([a-zA-Z]+\s+)?(.+)$/);
                if (!match) return;
                
                const [, amount = '', unit = '', name = line.trim()] = match;
                
                const row = document.createElement('div');
                row.className = 'ingredient-row';
                row.innerHTML = `
                    <input type="text" name="ingredients[${encodeURIComponent(section)}][${startIndex + index}][amount]" value="${amount.trim()}" placeholder="Amount">
                    <input type="text" name="ingredients[${encodeURIComponent(section)}][${startIndex + index}][unit]" value="${unit.trim()}" placeholder="Unit">
                    <input type="text" name="ingredients[${encodeURIComponent(section)}][${startIndex + index}][name]" value="${name.trim()}" placeholder="Ingredient" required>
                    <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
                `;
                container.appendChild(row);
            });
            
            modal.style.display = 'none';
            document.getElementById('bulk-ingredients').value = '';
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