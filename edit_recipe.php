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
$query = "SELECT * FROM recipes WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

// If recipe doesn't exist, redirect
if (!$recipe) {
    header('Location: index.php');
    exit;
}

// Check if user has permission to edit this recipe
if (!canEditRecipe($recipe['user_id'])) {
    $_SESSION['error_message'] = 'You do not have permission to edit this recipe.';
    header('Location: recipe.php?id=' . $recipe_id);
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
                <?php 
                // Get the current page name
                $current_page = basename($_SERVER['PHP_SELF']);
                // Only show Introduction link if we're not on the introduction page
                if ($current_page !== 'introduction.php'): 
                ?>
                    <a href="introduction.php">Introduction</a>
                <?php endif; ?>
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
        <div class="recipe-form">
            <form id="edit-recipe-form" action="api/update_recipe.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                <input type="hidden" name="return_to" value="recipe-<?php echo $recipe['id']; ?>">
                
                <div class="form-actions form-actions-top">
                    <button type="submit" class="btn btn-primary">Save Recipe</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='recipe.php?id=<?php echo $recipe['id']; ?>'">Cancel</button>
                </div>

                <div class="form-header">
                    <h1>Edit Recipe</h1>
                </div>

                <div class="form-group">
                    <label for="title">Recipe Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Categories</label>
                    <div class="category-selection">
                        <select id="category" name="category_ids[]" multiple required>
                            <?php 
                            // Fetch current categories for this recipe
                            $recipe_categories_query = "SELECT category_id FROM recipe_categories WHERE recipe_id = ?";
                            $stmt = $conn->prepare($recipe_categories_query);
                            $stmt->bind_param("i", $recipe_id);
                            $stmt->execute();
                            $recipe_categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            $selected_categories = array_column($recipe_categories, 'category_id');
                            
                            foreach ($categories as $category): 
                            ?>
                                <option value="<?php echo $category['id']; ?>" 
                                    <?php echo in_array($category['id'], $selected_categories) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl/Cmd to select multiple categories</small>
                        <button type="button" class="btn btn-secondary" onclick="showNewCategoryModal()">+ Add New Category</button>
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
                    <input type="file" id="image" name="image" accept="image/jpeg, image/png, image/webp">
                    <small>Accepted formats: JPG, PNG, WEBP</small>
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <div class="instructions-container">
                        <div class="instruction-steps">
                            <?php
                            // Get the instructions text
                            $instructions_text = trim($recipe['instructions']);
                            
                            // Fetch existing instruction images
                            $images_query = "SELECT * FROM instruction_images WHERE recipe_id = ? ORDER BY step_number";
                            $stmt = $conn->prepare($images_query);
                            $stmt->bind_param("i", $recipe_id);
                            $stmt->execute();
                            $instruction_images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            
                            // Create lookup array for images
                            $step_images = [];
                            foreach ($instruction_images as $img) {
                                $step_images[$img['step_number']] = $img['image_path'];
                            }
                            ?>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addInstructionStep()">Add Another Step</button>
                    </div>
                </div>

                <script>
                // Store step images data in JavaScript
                const stepImages = <?php echo json_encode($step_images); ?>;

                function initializeInstructions() {
                    const instructionsText = `<?php echo str_replace('`', '\`', $instructions_text); ?>`;
                    const container = document.querySelector('.instruction-steps');
                    
                    // Check if it's a single instruction with numbered steps
                    if (!instructionsText.includes('Step 2:')) {
                        const numberedStepPattern = /^\d+[\.\)]\s+/m;
                        if (numberedStepPattern.test(instructionsText)) {
                            // Split by <br> or newline and filter empty lines
                            const steps = instructionsText.split(/\s*<br>\s*|\r\n|\n|\r/).filter(step => step.trim());
                            
                            // Check if all lines are numbered
                            const allNumbered = steps.every(step => numberedStepPattern.test(step.trim()));
                            
                            if (allNumbered) {
                                // Clear container
                                container.innerHTML = '';
                                
                                // Create a step input for each numbered instruction
                                steps.forEach((step, index) => {
                                    const stepText = step.replace(/^\d+[\.\)]\s+/, '').trim();
                                    addInstructionStep(stepText, index + 1);
                                });
                                return;
                            }
                        }
                    }
                    
                    // Handle regular multi-step instructions
                    const steps = instructionsText.split(/Step \d+:\s+/).filter(step => step.trim());
                    if (steps.length > 0) {
                        // Clear container
                        container.innerHTML = '';
                        
                        // Create a step input for each instruction
                        steps.forEach((step, index) => {
                            addInstructionStep(step.trim(), index + 1);
                        });
                    } else {
                        // If no steps found, add one empty step
                        addInstructionStep();
                    }
                }

                function addInstructionStep(stepText = '', stepNumber = null) {
                    const container = document.querySelector('.instruction-steps');
                    const newStep = document.createElement('div');
                    newStep.className = 'instruction-step';
                    
                    let existingImageHtml = '';
                    if (stepNumber && stepImages[stepNumber]) {
                        existingImageHtml = `
                            <div class="current-step-image">
                                <img src="${stepImages[stepNumber]}" alt="Current step image">
                                <input type="hidden" name="existing_instruction_images[]" value="${stepImages[stepNumber]}">
                                <label>
                                    <input type="checkbox" name="remove_instruction_images[]" value="${stepNumber}">
                                    Remove this image
                                </label>
                            </div>
                        `;
                    }
                    
                    newStep.innerHTML = `
                        <textarea name="instructions[]" rows="3" required placeholder="Enter instruction step...">${stepText}</textarea>
                        ${existingImageHtml}
                        <input type="file" name="instruction_images[]" accept="image/jpeg, image/png, image/webp" class="instruction-image">
                        <small>Optional: ${stepNumber && stepImages[stepNumber] ? 'Replace' : 'Add'} image for this step</small>
                        ${container.children.length > 0 ? '<button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()">Remove Step</button>' : ''}
                    `;
                    container.appendChild(newStep);
                }

                // Initialize instructions when the page loads
                document.addEventListener('DOMContentLoaded', initializeInstructions);
                </script>

                <style>
                .instruction-steps {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                    margin-bottom: 1rem;
                }

                .instruction-step {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                    padding: 1rem;
                    background: #f8f9fa;
                    border-radius: 8px;
                }

                .instruction-step textarea {
                    width: 100%;
                    padding: 0.5rem;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }

                .instruction-step small {
                    color: #666;
                }

                .instructions-container {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }

                .current-step-image {
                    margin: 0.5rem 0;
                }

                .current-step-image img {
                    max-width: 200px;
                    border-radius: 4px;
                    margin-bottom: 0.5rem;
                }

                .current-step-image label {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    color: #dc3545;
                }
                </style>

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

                <div class="form-actions form-actions-bottom">
                    <button type="submit" class="btn btn-primary">Save Recipe</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='recipe.php?id=<?php echo $recipe['id']; ?>'">Cancel</button>
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

    // Update form submit handler
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('edit-recipe-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate ingredients
                const hasIngredients = document.querySelectorAll('.ingredient-row input[name$="[name]"]').length > 0;
                if (!hasIngredients) {
                    alert('Please add at least one ingredient');
                    return;
                }

                // Create a new FormData object
                const formData = new FormData(form);

                // Remove existing ingredient data
                for (const pair of formData.entries()) {
                    if (pair[0].startsWith('ingredients[')) {
                        formData.delete(pair[0]);
                    }
                }

                // Add ingredients with proper indexing
                const sections = document.querySelectorAll('.ingredient-section');
                let ingredientIndex = 0;

                sections.forEach(section => {
                    const sectionTitle = section.querySelector('.section-title');
                    const sectionName = sectionTitle ? sectionTitle.value || '' : '';
                    
                    section.querySelectorAll('.ingredient-row').forEach(row => {
                        const amountInput = row.querySelector('input[name$="[amount]"]');
                        const unitInput = row.querySelector('input[name$="[unit]"]');
                        const nameInput = row.querySelector('input[name$="[name]"]');
                        
                        if (nameInput && nameInput.value.trim()) {
                            formData.append(`ingredients[${ingredientIndex}][section]`, sectionName);
                            formData.append(`ingredients[${ingredientIndex}][amount]`, amountInput.value);
                            formData.append(`ingredients[${ingredientIndex}][unit]`, unitInput.value);
                            formData.append(`ingredients[${ingredientIndex}][name]`, nameInput.value);
                            ingredientIndex++;
                        }
                    });
                });

                // Submit the form
                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.action, true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        window.location.href = xhr.responseURL;
                    } else {
                        alert('Error saving recipe');
                    }
                };
                xhr.send(formData);
            });
        }
    });

    function showNewCategoryModal() {
        document.getElementById('newCategoryModal').style.display = 'block';
        document.getElementById('newCategoryName').value = '';
        document.getElementById('newCategoryName').focus();
    }

    function closeNewCategoryModal() {
        document.getElementById('newCategoryModal').style.display = 'none';
        document.getElementById('newCategoryName').value = '';
    }

    function saveNewCategory() {
        const categoryName = document.getElementById('newCategoryName').value.trim();
        if (!categoryName) {
            alert('Please enter a category name');
            return;
        }

        fetch('api/add_category.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: categoryName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new option to select
                const select = document.getElementById('category');
                const option = document.createElement('option');
                option.value = data.category_id;
                option.textContent = categoryName;
                select.appendChild(option);
                
                // Select the new option
                const currentSelections = Array.from(select.selectedOptions).map(opt => opt.value);
                currentSelections.push(data.category_id);
                currentSelections.forEach(value => {
                    select.querySelector(`option[value="${value}"]`).selected = true;
                });
                
                closeNewCategoryModal();
            } else {
                alert('Error adding category: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding category');
        });
    }

    document.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    function closeModal(modal) {
        modal.style.display = 'none';
        // Clear any input fields in the modal
        modal.querySelectorAll('input, textarea').forEach(input => {
            input.value = '';
        });
    }
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

    <!-- New Category Modal -->
    <div id="newCategoryModal" class="modal">
        <div class="modal-content">
            <h3>Add New Category</h3>
            <div class="form-group">
                <label for="newCategoryName">Category Name</label>
                <input type="text" id="newCategoryName" placeholder="Enter category name">
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="closeNewCategoryModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNewCategory()">Add Category</button>
            </div>
        </div>
    </div>
</body>
</html> 