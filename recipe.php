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

// Fetch recipe details with categories
$query = "SELECT r.*, GROUP_CONCAT(c.name) as category_names 
          FROM recipes r 
          LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id 
          LEFT JOIN categories c ON rc.category_id = c.id 
          WHERE r.id = ? 
          GROUP BY r.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

// If recipe doesn't exist, redirect
if (!$recipe) {
    header('Location: index.php');
    exit;
}

// Verify recipe belongs to user
if ($recipe['user_id'] != $_SESSION['user_id']) {
    header('Location: index.php');
    exit;
}

// Fetch ingredients
$ingredients_query = "SELECT * FROM ingredients WHERE recipe_id = ? ORDER BY section, id";
$stmt = $conn->prepare($ingredients_query);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group ingredients by section
$grouped_ingredients = [];
foreach ($ingredients as $ingredient) {
    $section = $ingredient['section'] ?: 'default';
    if (!isset($grouped_ingredients[$section])) {
        $grouped_ingredients[$section] = [];
    }
    $grouped_ingredients[$section][] = $ingredient;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> - Burning to Cook</title>
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
        <div class="recipe-details">
            <div class="recipe-header">
                <a href="index.php" class="back-link">← Back to Recipes</a>
                <?php if ($recipe['user_id'] == $_SESSION['user_id']): ?>
                    <div class="recipe-actions">
                        <button onclick="location.href='edit_recipe.php?id=<?php echo $recipe_id; ?>'" class="btn btn-primary">Edit Recipe</button>
                        <button onclick="deleteRecipe(<?php echo $recipe_id; ?>)" class="btn btn-secondary">Delete Recipe</button>
                    </div>
                <?php endif; ?>
            </div>

            <h1><?php echo htmlspecialchars($recipe['title']); ?></h1>
            <div class="recipe-meta">
                <?php if ($recipe['category_names']): ?>
                    <span class="categories">
                        <?php echo htmlspecialchars($recipe['category_names']); ?>
                    </span>
                <?php endif; ?>
                <span class="date">Added on <?php echo date('F j, Y', strtotime($recipe['created_at'])); ?></span>
            </div>

            <?php if ($recipe['image_path']): ?>
                <div class="recipe-image">
                    <img src="<?php echo htmlspecialchars($recipe['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($recipe['title']); ?>">
                </div>
            <?php endif; ?>

            <div class="ingredients-section">
                <h2>Ingredients</h2>
                <?php foreach ($grouped_ingredients as $section => $section_ingredients): ?>
                    <?php if ($section !== 'default'): ?>
                        <h3><?php echo htmlspecialchars($section); ?></h3>
                    <?php endif; ?>
                    <ul class="ingredients-list">
                        <?php foreach ($section_ingredients as $ingredient): ?>
                            <li>
                                <span class="amount"><?php echo $ingredient['amount']; ?></span>
                                <span class="unit"><?php echo htmlspecialchars($ingredient['unit']); ?></span>
                                <span class="ingredient"><?php echo htmlspecialchars($ingredient['name']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div>

            <div class="instructions-section">
                <h2>Instructions</h2>
                <div class="instructions"><?php echo nl2br(htmlspecialchars($recipe['instructions'])); ?></div>
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
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    function deleteRecipe(recipeId) {
        if (confirm('Are you sure you want to delete this recipe?')) {
            fetch(`api/delete_recipe.php?id=${recipeId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    alert('Error deleting recipe');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting recipe');
            });
        }
    }
    </script>
</body>
</html> 