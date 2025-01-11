<?php
session_start();
require_once 'config/database.php';

// Check if user is admin (user_id 1)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: introduction.php');
    exit;
}

// Fetch current content
$intro_query = "SELECT * FROM introduction WHERE id = 1";
$intro = $conn->query($intro_query)->fetch_assoc();

// Fetch timeline entries
$timeline_query = "SELECT * FROM timeline_entries ORDER BY year ASC";
$timeline_entries = $conn->query($timeline_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Introduction - Burning to Cook</title>
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
        <div class="edit-introduction">
            <h1>Edit Introduction</h1>
            
            <form method="POST" action="api/update_introduction.php">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo htmlspecialchars($intro['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" rows="10" required><?php echo htmlspecialchars($intro['content']); ?></textarea>
                </div>

                <h2>Timeline Entries</h2>
                <div id="timeline-entries">
                    <?php foreach ($timeline_entries as $entry): ?>
                        <div class="timeline-entry-edit">
                            <input type="number" name="entries[<?php echo $entry['id']; ?>][year]" 
                                   value="<?php echo $entry['year']; ?>" required>
                            <textarea name="entries[<?php echo $entry['id']; ?>][event]" required><?php echo htmlspecialchars($entry['event']); ?></textarea>
                            <button type="button" class="btn btn-secondary" 
                                    onclick="deleteTimelineEntry(<?php echo $entry['id']; ?>)">Delete</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-secondary" onclick="addTimelineEntry()">Add Timeline Entry</button>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="introduction.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <script>
    let newEntryCount = 0;

    function addTimelineEntry() {
        const container = document.getElementById('timeline-entries');
        const div = document.createElement('div');
        div.className = 'timeline-entry-edit';
        div.innerHTML = `
            <input type="number" name="new_entries[${newEntryCount}][year]" placeholder="Year" required>
            <textarea name="new_entries[${newEntryCount}][event]" placeholder="Event description" required></textarea>
            <button type="button" class="btn btn-secondary" onclick="this.parentElement.remove()">Remove</button>
        `;
        container.appendChild(div);
        newEntryCount++;
    }

    function deleteTimelineEntry(id) {
        if (confirm('Are you sure you want to delete this timeline entry?')) {
            fetch('api/delete_timeline_entry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting entry');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting entry');
            });
        }
    }
    </script>
</body>
</html> 