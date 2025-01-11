<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
error_log("Starting login process");

// If already logged in, redirect to recipes page
if (isset($_SESSION['user_id'])) {
    error_log("User already logged in, redirecting to index.php");
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing login POST request");
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    error_log("Attempting login for username: " . $username);
    
    $query = "SELECT id, password_hash FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        error_log("Password verified successfully");
        $_SESSION['user_id'] = $user['id'];
        error_log("Session user_id set to: " . $user['id']);
        header('Location: index.php');
        exit;
    } else {
        error_log("Login failed");
        $error = "Invalid username or password";
    }
}

// Check if there's a user in the database at all
$check_query = "SELECT COUNT(*) as count FROM users";
$result = $conn->query($check_query);
$count = $result->fetch_assoc()['count'];
error_log("Total users in database: " . $count);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Burning to Cook</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php">Burning to Cook</a>
            </div>
        </nav>
    </header>

    <main>
        <div class="auth-container">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </main>
</body>
</html> 