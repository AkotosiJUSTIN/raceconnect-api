<?php
// Include the Composer autoload file
require_once __DIR__ . '/../../vendor/autoload.php';

// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Start login session
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Initialize error message
$error_message = '';

//Check if admin is already logged in
if (isset($_SESSION['email']) && !empty($_SESSION['email'])){
    // Redirect to main index page if already logged in
    header("Location: index.php");
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $email = $_POST['email'];
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;

    // Prepare and bind
    $stmt = $conn->prepare("SELECT username, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);

    // Execute statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $username = $row['username'];
        $hashed_password = $row['password'];

        // Verify the password
        if (password_verify($password, $hashed_password)) {
            // Set session variables
            $_SESSION['email'] = $email;
            $_SESSION['username'] = $username;

            // Handle "Remember Me" functionality
            if ($remember_me) {
                // Set cookies for 30 days if "Remember Me" is checked
                setcookie('email', $email, time() + (86400 * 30), "/", "", true, true); // 30 days
                setcookie('username', $username, time() + (86400 * 30), "/", "", true, true);
            } else {
                // Clear cookies if "Remember Me" is not checked
                setcookie('email', '', time() - 3600, "/", "", true, true);
                setcookie('username', '', time() - 3600, "/", "", true, true);
            }

            // Redirect to the dashboard
            header("Location: index.php");
            exit();
        } else {
            $error_message = "Invalid email or password";
        }
    } else {
        $error_message = "Invalid email or password";
    }

    // Close connection
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RaceConnect Admin Login</title>
    <link rel="stylesheet" href="assets/css/admin-login.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="icon" href="./assets/RaceConnectLogo.png">
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <img src="./assets/RaceConnectLogo.png" alt="RaceConnect Logo" id="rcLogo">
        </div>
        <div class="header-title">
            Race Connect
        </div>
    </div>

    <!-- Login Form -->
    <div class="login-container">
        <div class="login-form">
            <h2 class="form-title">Ready, Set, Connect!</h2>
            <form action="index_login.php" method="POST">
                <div class="form-group">
                    <label for="email" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='envelope' color="red" class="input-icon"></box-icon>
                        <input type="email" id="email" name="email" required class="text-input" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password" class="input-label">Password</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='lock' color="red" class="input-icon"></box-icon>
                        <input type="password" id="password" name="password" required class="text-input">
                        <button type="button" onclick="togglePassword()" class="password-toggle">
                            <box-icon id="eye-icon" color="red" name='show' type='solid'></box-icon>
                        </button>
                    </div>
                </div>
                <div class="remember-me">
                    <label class="checkbox-wrapper">
                        <input name="remember-me" type="checkbox" class="checkbox-input" hidden>
                        <span class="custom-checkbox"></span>
                        <span class="ml-2">&nbsp;&nbsp; Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>
                <?php if ($error_message): ?>
                    <div class="error-message"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <button type="submit" class="submit-button">Log In</button>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.setAttribute('name', 'hide');
            } else {
                passwordInput.type = 'password';
                eyeIcon.setAttribute('name', 'show');
            }
        }
    </script>
</body>
</html>