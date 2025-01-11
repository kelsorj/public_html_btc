<?php
$json = file_get_contents('family_recipes.recipes.json');
$recipes = json_decode($json, true);

$sql = "";

// Insert categories first with a default category
$sql .= "INSERT IGNORE INTO categories (name) VALUES ('Uncategorized');\n";

$categories = array_unique(array_filter(array_column($recipes, 'category')));
foreach ($categories as $category) {
    $sql .= sprintf("INSERT IGNORE INTO categories (name) VALUES ('%s');\n", 
        addslashes($category));
}

$sql .= "\n";

// Insert recipes
foreach ($recipes as $recipe) {
    $category = !empty($recipe['category']) ? $recipe['category'] : 'Uncategorized';
    
    $sql .= sprintf(
        "INSERT INTO recipes (title, category_id, instructions, created_at, updated_at, user_id) " .
        "SELECT '%s', (SELECT id FROM categories WHERE name = '%s'), '%s', '%s', '%s', 1;\n",
        addslashes($recipe['title']),
        addslashes($category),
        addslashes($recipe['instructions'] ?? ''),
        date('Y-m-d H:i:s', strtotime($recipe['createdAt']['$date'])),
        date('Y-m-d H:i:s', strtotime($recipe['updatedAt']['$date']))
    );
    
    // Get the recipe ID for ingredients
    $sql .= "SET @last_recipe_id = LAST_INSERT_ID();\n";
    
    // Insert ingredients
    if (isset($recipe['ingredients'])) {
        foreach ($recipe['ingredients'] as $ingredient) {
            if (!empty($ingredient['name'])) {
                // Clean up ingredient name (remove ▢ if present)
                $name = str_replace('▢', '', $ingredient['name']);
                $sql .= sprintf(
                    "INSERT INTO ingredients (recipe_id, name, amount, unit) " .
                    "VALUES (@last_recipe_id, '%s', '%s', '%s');\n",
                    addslashes($name),
                    addslashes($ingredient['amount'] ?? ''),
                    addslashes($ingredient['unit'] ?? '')
                );
            }
        }
    }
    
    // Insert notes if present
    if (!empty($recipe['notes'])) {
        $sql .= sprintf(
            "INSERT INTO recipe_notes (recipe_id, user_id, note) " .
            "VALUES (@last_recipe_id, 1, '%s');\n",
            addslashes($recipe['notes'])
        );
    }
    
    // Insert family story if present
    if (!empty($recipe['familyStory'])) {
        $sql .= sprintf(
            "INSERT INTO family_stories (recipe_id, user_id, story, date_of_event) " .
            "VALUES (@last_recipe_id, 1, '%s', NOW());\n",
            addslashes($recipe['familyStory'])
        );
    }
    
    $sql .= "\n";
}

file_put_contents('migration.sql', $sql);
?> 