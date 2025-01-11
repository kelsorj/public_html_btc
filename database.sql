USE wpxcxfmy_burning_to_cook;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('created', 'active', 'inactive') DEFAULT 'created',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE recipes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE ingredients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipe_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount VARCHAR(20) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

-- Add instructions field to recipes table
ALTER TABLE recipes ADD COLUMN instructions TEXT NOT NULL AFTER category_id;

-- Create a new table for recipe notes/comments
CREATE TABLE recipe_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipe_id INT NOT NULL,
    
    user_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create a new table for family stories
CREATE TABLE family_stories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    story TEXT NOT NULL,
    date_of_event DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create table for introduction content
CREATE TABLE introduction (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create table for timeline entries
CREATE TABLE timeline_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    event TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert some basic categories
INSERT INTO categories (name) VALUES 
('Main Dish'),
('Dessert'),
('Appetizer'),
('Soup'),
('Salad'),
('Breakfast'); 

-- Insert a test recipe for user id 1
INSERT INTO recipes (user_id, title, category_id, image_path, created_at) 
VALUES (1, 'Classic Beef Lasagna', 1, 'uploads/test.png', CURRENT_TIMESTAMP);

-- Get the last inserted recipe id (let's say it's 1) and add its ingredients
INSERT INTO ingredients (recipe_id, name, amount, unit) VALUES
(1, 'ground beef', 1, 'pound'),
(1, 'lasagna noodles', 12, 'pieces'),
(1, 'ricotta cheese', 15, 'ounces'),
(1, 'mozzarella cheese', 2, 'cups'),
(1, 'marinara sauce', 24, 'ounces'),
(1, 'eggs', 1, 'piece'),
(1, 'garlic', 3, 'cloves'),
(1, 'Italian seasoning', 1, 'tablespoon'),
(1, 'salt', 1, 'teaspoon'),
(1, 'black pepper', 0.5, 'teaspoon'); 

-- Modify the ingredients table to use VARCHAR for amount instead of DECIMAL
ALTER TABLE ingredients MODIFY amount VARCHAR(20) NOT NULL; 

-- Update the test recipe to include instructions
UPDATE recipes SET instructions = 
'1. Preheat oven to 375°F (190°C).
2. Brown ground beef in a large skillet over medium heat.
3. In a large bowl, mix ricotta cheese with eggs and Italian seasoning.
4. Layer in a 9x13 baking dish:
- Spread 1 cup marinara sauce
- Layer lasagna noodles
- Spread ricotta mixture
- Add meat layer
- Sprinkle mozzarella cheese
- Repeat layers
5. Cover with foil and bake for 25 minutes.
6. Remove foil and bake additional 25 minutes until bubbly and golden.
7. Let rest for 10-15 minutes before serving.'
WHERE id = 1;

-- Add some sample notes
INSERT INTO recipe_notes (recipe_id, user_id, note) VALUES
(1, 1, 'I like to add a layer of fresh spinach between the ricotta and meat layers for extra nutrition.'),
(1, 1, 'Using oven-ready noodles saves time and works just as well!');

-- Add a sample family story
INSERT INTO family_stories (recipe_id, user_id, story, date_of_event) VALUES
(1, 1, 'This was the first dish I made for our family Christmas gathering in 2022. Everyone loved it so much that it has become our traditional holiday meal.', '2022-12-25'); 

-- Insert initial introduction content
INSERT INTO introduction (title, content) VALUES (
    'Welcome to Burning to Cook',
    'For many years, our family has dependend upon an old, golden covered Better Homes and Gardens Cookbook as the authoritative source for simple, basic, no-fuss recipes. Sometime in or around 11975, one of the family''s cooks, who shall remain nameless, placed the book face down on a hot burner in the kitched. The circular tattoo which resulted became the humorous trademark of this kitchen''s favorite book, and it has inspired the title for this website.

Burning to Cook was first put together in 1991, with the practical goal of centralizing the blizzard of white recipe cards which float around the house with combinations of ingredients too cherished to be lost. This early version of the book also enabled Laurie and me to carry off a bit of the family culinary wisdom as we departed from home and fended for ourselves in out own kitches.

The 1999 second edition broadens the scope of recipes now included in the family''s repertoire. That edition also delves, for the first time, into the stories associated with these recipes. SGM 1999

The 2024 website edition was inspired by watching the family cook in San Francisco switching back and forth between printed recipes and the touchscreen recipes. Why not put them all online? RJK 2024'
);

-- Insert timeline entries (I'll show a few examples)
INSERT INTO timeline_entries (year, event, created_by) VALUES 
(1953, 'SJM carries to school a 5-pound Crisco shortening can, full of beans for lunch. As a result of this childhood deprivation, Dad is driven into a later-life, meat-eating frenzy.', 1),
(1959, 'LJM gets her Five Roses Cookbook. Ordered when she was taking her home economics class at Nelson High School, Burlington, Ontario, Canada.', 1);
-- Continue with other timeline entries... 