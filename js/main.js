document.addEventListener('DOMContentLoaded', function() {
    // Only try to load recipes if we're on the recipes page
    const searchInput = document.getElementById('recipe-search');
    const categoryFilter = document.getElementById('category-filter');
    
    if (searchInput && categoryFilter) {
        loadRecipes();
        searchInput.addEventListener('input', debounce(loadRecipes, 300));
        categoryFilter.addEventListener('change', loadRecipes);
    }
});

function loadRecipes() {
    const searchTerm = document.getElementById('recipe-search').value;
    const categoryId = document.getElementById('category-filter').value;

    fetch(`api/get_recipes.php?search=${encodeURIComponent(searchTerm)}&category=${encodeURIComponent(categoryId)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(recipes => {
            const container = document.getElementById('recipe-container');
            container.innerHTML = '';

            if (recipes.length === 0) {
                container.innerHTML = '<p class="no-recipes">No recipes found</p>';
                return;
            }

            recipes.forEach(recipe => {
                const recipeCard = createRecipeCard(recipe);
                container.appendChild(recipeCard);
            });
        })
        .catch(error => {
            console.error('Error loading recipes:', error);
            const container = document.getElementById('recipe-container');
            container.innerHTML = '<p class="error-message">Error loading recipes. Please try again later.</p>';
        });
}

function createRecipeCard(recipe) {
    const card = document.createElement('div');
    card.className = 'recipe-card';
    card.onclick = () => window.location.href = `recipe.php?id=${recipe.id}`;
    card.style.cursor = 'pointer';
    
    card.innerHTML = `
        <div class="recipe-image">
            ${recipe.image_path ? `<img src="${recipe.image_path}" alt="${recipe.title}">` : ''}
        </div>
        <div class="recipe-content">
            <h3>${recipe.title}</h3>
            <p class="category">${recipe.category_name}</p>
            <p class="ingredients-count">${recipe.ingredients_count} ingredients</p>
            <div class="recipe-actions">
                <button onclick="editRecipe(${recipe.id})" class="edit-btn">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteRecipe(${recipe.id})" class="delete-btn">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;

    // Prevent the buttons from triggering the card click
    const buttons = card.querySelectorAll('button');
    buttons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    });

    return card;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function deleteRecipe(recipeId) {
    if (confirm('Are you sure you want to delete this recipe?')) {
        fetch(`api/delete_recipe.php?id=${recipeId}`, {
            method: 'DELETE',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.php';
            } else {
                alert(data.error || 'Error deleting recipe');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting recipe');
        });
    }
}

function parseBulkIngredients(text) {
    // Split by newlines and filter empty lines
    return text.split('\n')
        .map(line => line.trim())
        .filter(line => line)
        .map(line => {
            // Try to parse amount, unit, and name
            const regex = /^(?:(?:(\d+(?:\/\d+)?|\d*\.?\d+)\s*([A-Za-z]+|\"))?\s*)?(.+)$/;
            const match = line.match(regex);
            
            if (match) {
                const [, amount, unit, name] = match;
                return {
                    amount: amount || '',
                    unit: unit || '',
                    name: name.trim()
                };
            }
            
            // If no pattern match, return just the name
            return {
                amount: '',
                unit: '',
                name: line
            };
        });
}

function addBulkIngredients() {
    const bulkText = document.getElementById('bulk-ingredients').value;
    const ingredients = parseBulkIngredients(bulkText);
    
    const container = document.getElementById('ingredients-container');
    ingredients.forEach(ingredient => {
        addIngredientRow(ingredient.amount, ingredient.unit, ingredient.name);
    });
    
    // Clear the textarea
    document.getElementById('bulk-ingredients').value = '';
    document.getElementById('bulk-input-modal').style.display = 'none';
}

// Add this function to handle the Add Recipe button click
function addRecipe() {
    window.location.href = 'add_recipe.php';
} 