<?php
session_start();
require_once 'config/database.php';

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
                    <select id="category" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo ($category['id'] == $recipe['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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

                <div class="ingredients-section">
                    <h2>Ingredients</h2>
                    <div id="ingredients-container">
                        <?php foreach ($ingredients as $index => $ingredient): ?>
                            <div class="ingredient-row">
                                <input type="text" name="ingredients[<?php echo $index; ?>][name]" 
                                       value="<?php echo htmlspecialchars($ingredient['name']); ?>" 
                                       placeholder="Ingredient name" required>
                                <input type="text" name="ingredients[<?php echo $index; ?>][amount]" 
                                       value="<?php echo htmlspecialchars($ingredient['amount']); ?>" 
                                       placeholder="Amount" required>
                                <input type="text" name="ingredients[<?php echo $index; ?>][unit]" 
                                       value="<?php echo htmlspecialchars($ingredient['unit']); ?>" 
                                       placeholder="Unit" required>
                                <button type="button" class="btn btn-secondary remove-ingredient">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-ingredient" class="btn btn-secondary">Add Ingredient</button>
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
        const container = document.getElementById('ingredients-container');
        const addButton = document.getElementById('add-ingredient');
        let ingredientCount = <?php echo count($ingredients); ?>;

        addButton.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'ingredient-row';
            row.innerHTML = `
                <input type="text" name="ingredients[${ingredientCount}][name]" placeholder="Ingredient name" required>
                <input type="text" name="ingredients[${ingredientCount}][amount]" placeholder="Amount" required>
                <input type="text" name="ingredients[${ingredientCount}][unit]" placeholder="Unit" required>
                <button type="button" class="btn btn-secondary remove-ingredient">Remove</button>
            `;
            container.appendChild(row);
            ingredientCount++;
        });

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-ingredient')) {
                e.target.parentElement.remove();
            }
        });
    });

    // Note Modal Functions
    function showAddNoteModal() {
        document.getElementById('noteModal').style.display = 'block';
    }

    function closeNoteModal() {
        document.getElementById('noteModal').style.display = 'none';
        document.getElementById('noteText').value = '';
    }

    function saveNote() {
        const noteText = document.getElementById('noteText').value.trim();
        if (!noteText) {
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
                note: noteText
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

    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
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
</body>
</html> 