<?php
session_start();
require_once 'config/database.php';

// Fetch introduction content
$intro_query = "SELECT * FROM introduction WHERE id = 1";
$intro = $conn->query($intro_query)->fetch_assoc();

// Fetch timeline entries
$timeline_query = "SELECT t.*, u.username 
                  FROM timeline_entries t 
                  LEFT JOIN users u ON t.created_by = u.id 
                  ORDER BY t.year ASC";
$timeline_entries = $conn->query($timeline_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($intro['title']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php">Burning to Cook</a>
            </div>
            <div class="nav-links">
                <a href="index.php">Recipes</a>
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
        <div class="introduction-content">
            <h1><?php echo htmlspecialchars($intro['title']); ?></h1>
            <div class="intro-text"><?php echo nl2br(htmlspecialchars($intro['content'])); ?></div>

            <h2>Family Culinary Timeline</h2>
            <div class="timeline">
                <?php foreach ($timeline_entries as $entry): ?>
                    <div class="timeline-entry">
                        <div class="timeline-year"><?php echo $entry['year']; ?></div>
                        <div class="timeline-event">
                            <?php echo nl2br(htmlspecialchars($entry['event'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</body>
</html> 