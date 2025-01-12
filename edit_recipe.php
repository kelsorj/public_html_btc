<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get recipe ID from URL
$recipe_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch recipe details
$query = "SELECT r.* FROM recipes r 
          WHERE r.id = ? AND r.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $recipe_id, $_SESSION['user_id']);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

// If recipe doesn't exist or doesn't belong to user, redirect
if (!$recipe) {
    header('Location: index.php');
    exit;
}

// Fetch ingredients
$ingredients_query = "SELECT * FROM ingredients WHERE recipe_id = ? ORDER BY id";
$stmt = $conn->prepare($ingredients_query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch categories for dropdown
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Recipe - Burning to Cook</title>
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
                <a href="recipes.php">Recipes</a>
                <a href="introduction.php">Introduction</a>
                <button class="btn btn-secondary" onclick="location.href='logout.php'">Logout</button>
            </div>
        </nav>
    </header>

    <main>
        <div class="recipe-form">
            <div class="form-header">
                <h1>Edit Recipe</h1>
                <a href="recipe.php?id=<?php echo $recipe_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>

            <form id="edit-recipe-form" method="POST" action="api/update_recipe.php" enctype="multipart/form-data">
                <input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>">
                
                <div class="form-group">
                    <label for="title">Recipe Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <div class="category-selection">
                        <select id="category" name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($category['id'] == $recipe['category_id']) ? 'selected' : ''; ?>>
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
                    <label>Current Image</label>
                    <?php if ($recipe['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($recipe['image_path']); ?>" 
                             alt="Current recipe image" 
                             class="recipe-current-image">
                    <?php else: ?>
                        <p>No image uploaded</p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="image">Update Image (optional)</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" rows="10" required><?php echo htmlspecialchars($recipe['instructions']); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Ingredients</label>
                    <div id="ingredient-sections">
                        <?php
                        // Fetch ingredient sections
                        $sections_query = "SELECT DISTINCT section FROM ingredients WHERE recipe_id = ? ORDER BY section";
                        $stmt = $conn->prepare($sections_query);
                        $stmt->bind_param("i", $recipe_id);
                        $stmt->execute();
                        $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        
                        // If no sections exist, create a default section
                        if (empty($sections)) {
                            $sections = [['section' => '']];
                        }

                        foreach ($sections as $section):
                            // Fetch ingredients for this section
                            $ingredients_query = "SELECT * FROM ingredients WHERE recipe_id = ? AND section = ? ORDER BY id";
                            $stmt = $conn->prepare($ingredients_query);
                            $stmt->bind_param("is", $recipe_id, $section['section']);
                            $stmt->execute();
                            $section_ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>
                            <div class="ingredient-section">
                                <div class="section-header">
                                    <input type="text" 
                                           class="section-title" 
                                           name="sections[]" 
                                           placeholder="Section Name (optional)" 
                                           value="<?php echo htmlspecialchars($section['section']); ?>">
                                    <?php if (!empty($sections) && count($sections) > 1): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="removeSection(this)">Remove Section</button>
                                    <?php endif; ?>
                                </div>
                                <div class="ingredients-container">
                                    <?php foreach ($section_ingredients as $i => $ingredient): ?>
                                        <div class="ingredient-row">
                                            <input type="text" name="ingredients[<?php echo htmlspecialchars($section['section']); ?>][<?php echo $i; ?>][amount]" 
                                                   value="<?php echo htmlspecialchars($ingredient['amount']); ?>" 
                                                   placeholder="Amount">
                                            <input type="text" name="ingredients[<?php echo htmlspecialchars($section['section']); ?>][<?php echo $i; ?>][unit]" 
                                                   value="<?php echo htmlspecialchars($ingredient['unit']); ?>" 
                                                   placeholder="Unit">
                                            <input type="text" name="ingredients[<?php echo htmlspecialchars($section['section']); ?>][<?php echo $i; ?>][name]" 
                                                   value="<?php echo htmlspecialchars($ingredient['name']); ?>" 
                                                   placeholder="Ingredient" required>
                                            <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="ingredient-buttons">
                                    <button type="button" class="btn btn-secondary" onclick="addIngredientRow(this)">Add Ingredient</button>
                                    <button type="button" class="btn btn-secondary" onclick="showBulkAddModal(this)">Bulk Add Ingredients</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="addNewSection()">Add New Section</button>
                </div>

                <div class="notes-section">
                    <h2>Recipe Notes</h2>
                    <?php
                    $notes_query = "SELECT n.*, u.username FROM recipe_notes n 
                                    LEFT JOIN users u ON n.user_id = u.id 
                                    WHERE n.recipe_id = ? ORDER BY n.created_at DESC";
                    $stmt = $conn->prepare($notes_query);
                    $stmt->bind_param("i", $recipe_id);
                    $stmt->execute();
                    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <div class="notes-list">
                        <?php foreach ($notes as $note): ?>
                            <div class="note">
                                <p class="note-text"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                <p class="note-meta">
                                    Added by <?php echo htmlspecialchars($note['username']); ?> 
                                    on <?php echo date('F j, Y', strtotime($note['created_at'])); ?>
                                    <?php if ($note['user_id'] == $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                onclick="deleteNote(<?php echo $note['id']; ?>)">Delete</button>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="showAddNoteModal()">Add Note</button>
                </div>

                <div class="stories-section">
                    <h2>Family Stories</h2>
                    <?php
                    $stories_query = "SELECT s.*, u.username FROM family_stories s 
                                      LEFT JOIN users u ON s.user_id = u.id 
                                      WHERE s.recipe_id = ? ORDER BY s.date_of_event DESC";
                    $stmt = $conn->prepare($stories_query);
                    $stmt->bind_param("i", $recipe_id);
                    $stmt->execute();
                    $stories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <div class="stories-list">
                        <?php foreach ($stories as $story): ?>
                            <div class="story">
                                <p class="story-text"><?php echo nl2br(htmlspecialchars($story['story'])); ?></p>
                                <p class="story-meta">
                                    Event Date: <?php echo date('F j, Y', strtotime($story['date_of_event'])); ?><br>
                                    Shared by <?php echo htmlspecialchars($story['username']); ?>
                                    <?php if ($story['user_id'] == $_SESSION['user_id']): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                onclick="deleteStory(<?php echo $story['id']; ?>)">Delete</button>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="showAddStoryModal()">Add Story</button>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Category select handler
        const categorySelect = document.getElementById('category');
        if (categorySelect) {
            categorySelect.addEventListener('change', function() {
                const newCategoryInput = document.getElementById('new-category-input');
                if (this.value === 'new') {
                    newCategoryInput.style.display = 'flex';
                    this.style.display = 'none';
                }
            });
        }

        // Initialize existing section title listeners
        const existingTitles = document.querySelectorAll('.section-title');
        existingTitles.forEach(titleInput => {
            titleInput.dataset.oldValue = titleInput.value || '';
            addSectionTitleListener(titleInput);
        });

        // Initialize ingredient container event listeners
        const container = document.getElementById('ingredients-container');
        if (container) {
            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-ingredient')) {
                    e.target.parentElement.remove();
                }
            });
        }

        // Modal click outside handler
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                // Clear any input fields if needed
                const storyText = document.getElementById('storyText');
                const storyDate = document.getElementById('storyDate');
                const noteText = document.getElementById('noteText');
                const bulkIngredients = document.getElementById('bulk-ingredients');
                
                if (storyText) storyText.value = '';
                if (storyDate) storyDate.value = '';
                if (noteText) noteText.value = '';
                if (bulkIngredients) bulkIngredients.value = '';
            }
        };
    });

    function cancelNewCategory() {
        const categorySelect = document.getElementById('category');
        const newCategoryInput = document.getElementById('new-category-input');
        if (categorySelect && newCategoryInput) {
            categorySelect.value = '';
            categorySelect.style.display = 'block';
            newCategoryInput.style.display = 'none';
            const newCategoryName = document.getElementById('new-category-name');
            if (newCategoryName) {
                newCategoryName.value = '';
            }
        }
    }

    // Ingredient Section Management Functions
    function addNewSection() {
        const ingredientSections = document.getElementById('ingredient-sections');
        const newSection = document.createElement('div');
        newSection.className = 'ingredient-section';
        newSection.innerHTML = `
            <div class="section-header">
                <input type="text" class="section-title" name="sections[]" placeholder="Section Name (optional)" data-old-value="">
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

    function addSectionTitleListener(titleInput) {
        titleInput.addEventListener('change', function() {
            const section = this.closest('.ingredient-section');
            const oldValue = this.dataset.oldValue || '';
            const newValue = this.value || '';
            
            // Update all ingredient input names in this section
            section.querySelectorAll('.ingredient-row input').forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(
                        `ingredients[${encodeURIComponent(oldValue)}]`,
                        `ingredients[${encodeURIComponent(newValue)}]`
                    ));
                }
            });
            
            this.dataset.oldValue = newValue;
        });
    }

    function removeSection(button) {
        if (confirm('Are you sure you want to remove this section and all its ingredients?')) {
            button.closest('.ingredient-section').remove();
        }
    }

    function addIngredientRow(button) {
        const container = button.closest('.ingredient-section').querySelector('.ingredients-container');
        const section = button.closest('.ingredient-section').querySelector('.section-title').value || '';
        const ingredientCount = container.children.length;
        
        const row = document.createElement('div');
        row.className = 'ingredient-row';
        row.innerHTML = `
            <input type="text" name="ingredients[${encodeURIComponent(section)}][${ingredientCount}][amount]" placeholder="Amount">
            <input type="text" name="ingredients[${encodeURIComponent(section)}][${ingredientCount}][unit]" placeholder="Unit">
            <input type="text" name="ingredients[${encodeURIComponent(section)}][${ingredientCount}][name]" placeholder="Ingredient" required>
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
        
        const lines = bulkText.split('\n').filter(line => line.trim());
        
        lines.forEach((line, index) => {
            const parts = line.trim().match(/^([\d./]+)?\s*([^\d\s].*?)\s+(.+)$/) || [null, '', '', line.trim()];
            const [, amount, unit, name] = parts;
            
            const row = document.createElement('div');
            row.className = 'ingredient-row';
            row.innerHTML = `
                <input type="text" name="ingredients[${encodeURIComponent(section)}][${container.children.length + index}][amount]" value="${amount || ''}" placeholder="Amount">
                <input type="text" name="ingredients[${encodeURIComponent(section)}][${container.children.length + index}][unit]" value="${unit || ''}" placeholder="Unit">
                <input type="text" name="ingredients[${encodeURIComponent(section)}][${container.children.length + index}][name]" value="${name}" placeholder="Ingredient" required>
                <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(row);
        });
        
        modal.style.display = 'none';
        document.getElementById('bulk-ingredients').value = '';
    }

    // Note Modal Functions
    function showAddNoteModal() {
        const modal = document.getElementById('noteModal');
        if (modal) {
            modal.style.display = 'block';
        }
    }

    function closeNoteModal() {
        const modal = document.getElementById('noteModal');
        const noteText = document.getElementById('noteText');
        if (modal) {
            modal.style.display = 'none';
        }
        if (noteText) {
            noteText.value = '';
        }
    }

    function saveNote() {
        const noteText = document.getElementById('noteText');
        if (!noteText || !noteText.value.trim()) {
            alert('Please enter a note');
            return;
        }

        fetch('api/add_note.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                recipe_id: <?php echo $recipe_id; ?>,
                note: noteText.value.trim()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error saving note: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving note');
        });
    }

    function deleteNote(noteId) {
        if (!confirm('Are you sure you want to delete this note?')) {
            return;
        }

        fetch('api/delete_note.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                note_id: noteId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting note: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting note');
        });
    }

    // Story Modal Functions
    function showAddStoryModal() {
        document.getElementById('storyModal').style.display = 'block';
        document.getElementById('storyDate').valueAsDate = new Date();
    }

    function closeStoryModal() {
        document.getElementById('storyModal').style.display = 'none';
        document.getElementById('storyText').value = '';
        document.getElementById('storyDate').value = '';
    }

    function saveStory() {
        const storyText = document.getElementById('storyText').value.trim();
        const storyDate = document.getElementById('storyDate').value;

        if (!storyText || !storyDate) {
            alert('Please fill in all fields');
            return;
        }

        fetch('api/add_story.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                recipe_id: <?php echo $recipe_id; ?>,
                story: storyText,
                date_of_event: storyDate
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error saving story: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving story');
        });
    }

    function deleteStory(storyId) {
        if (!confirm('Are you sure you want to delete this story?')) {
            return;
        }

        fetch('api/delete_story.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                story_id: storyId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting story: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting story');
        });
    }

    // Add form submit handler
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('edit-recipe-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Ensure empty sections are handled properly
                document.querySelectorAll('.section-title').forEach(title => {
                    if (!title.value.trim()) {
                        const section = title.closest('.ingredient-section');
                        section.querySelectorAll('.ingredient-row input').forEach(input => {
                            const name = input.getAttribute('name');
                            if (name) {
                                input.setAttribute('name', name.replace(
                                    /ingredients\[[^\]]*\]/,
                                    'ingredients[default]'
                                ));
                            }
                        });
                    }
                });
            });
        }
    });
    </script>

    <div id="noteModal" class="modal">
        <div class="modal-content">
            <h3>Add Note</h3>
            <textarea id="noteText" rows="4" placeholder="Enter your note"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNote()">Save Note</button>
            </div>
        </div>
    </div>

    <div id="storyModal" class="modal">
        <div class="modal-content">
            <h3>Add Family Story</h3>
            <input type="date" id="storyDate" required>
            <textarea id="storyText" rows="6" placeholder="Share your story"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeStoryModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveStory()">Save Story</button>
            </div>
        </div>
    </div>

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
</body>
</html> 