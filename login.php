<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT id, username, password_hash, status FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ($user['status'] === 'created') {
            $error = 'Your account is pending approval. Please wait for admin activation.';
        } else if ($user['status'] === 'inactive') {
            $error = 'Your account is inactive. Please contact the administrator.';
        } else if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Add debugging
            error_log("Login successful for user: " . $username);
            error_log("Session user_id: " . $_SESSION['user_id']);
            
            // Make sure the header redirect is working
            if (!headers_sent()) {
                header('Location: index.php');
                exit;
            } else {
                echo '<script>window.location.href = "index.php";</script>';
                exit;
            }
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found';
    }
}

// Add debugging at the top of the file
error_log("Session status: " . session_status());
error_log("POST data: " . print_r($_POST, true));
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
                <div class="error-message"><?php echo $error; ?></div>
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