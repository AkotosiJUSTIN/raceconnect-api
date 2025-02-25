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

// Check if admin is already logged in
if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
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

    // Prepare and bind for the admin table
    $stmt = $conn->prepare("SELECT admin_name, password FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);

    // Execute statement
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if admin exists
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $admin_name = $row['admin_name'];
        $hashed_password = $row['password'];

        // Verify the password
        if (password_verify($password, $hashed_password)) {
            // Set session variables
            $_SESSION['email'] = $email;
            $_SESSION['admin_name'] = $admin_name;

            // Handle "Remember Me" functionality
            if ($remember_me) {
                // Set cookies for 30 days if "Remember Me" is checked
                setcookie('email', $email, time() + (86400 * 30), "/", "", true, true); // 30 days
                setcookie('admin_name', $admin_name, time() + (86400 * 30), "/", "", true, true);
            } else {
                // Clear cookies if "Remember Me" is not checked
                setcookie('email', '', time() - 3600, "/", "", true, true);
                setcookie('admin_name', '', time() - 3600, "/", "", true, true);
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

// Check if the user has logged out successfully
$logout_message = isset($_GET['logged_out']) && $_GET['logged_out'] == 'true' ? "You have successfully logged out." : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RaceConnect Admin Login</title>
    <link rel="stylesheet" href="assets/css/admin-login.css">
    <link rel="icon" href="./assets/RaceConnectLogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lalezar&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script src="assets/javascript/remember-me.js" defer></script>
    <script src="assets/javascript/forgot-password.js" defer></script>
</head>
<body>
<!-- Split-screen layout -->
    <div class="login-left">
        <img src="./assets/race-bg.jpg" alt="RCBackground">
        <img src="./assets/racecar.png" alt="RCCar" id="rcCar">
        <div class="logo-subtitle-container">
            <div class="logo">
                <img src="./assets/RaceConnectLogo.png" alt="RaceConnect Logo" id="rcLogo">
            </div>
                <h3 class="login-subtitle">
                Welcome to RaceConnect! Log in to manage your account and access exclusive features.
            </h3>
        </div>
    </div>
    <div class="login-right">
        <div class="login-container">
            <div class="header-container">
                <img src="./assets/RaceConnectLogo.png" alt="RaceConnect Logo" class="dashboard-logo">
                <h2 class="dashboard-title">Race Connect Dashboard</h2>
            </div>
            <div class="login-form">
                <h2 class="form-title">Ready, Set, Connect!</h2>
                <form id="loginForm" action="index_login.php" method="POST">
                    <div class="form-group">
                        <label for="email" class="input-label">Email</label>
                        <div class="input-wrapper">
                            <box-icon type='solid' name='envelope' color="#dc2626" class="input-icon"></box-icon>
                            <input type="email" id="email" name="email" required class="text-input" autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password" class="input-label">Password</label>
                        <div class="input-wrapper">
                            <box-icon type='solid' name='lock' color="#dc2626" class="input-icon"></box-icon>
                            <input type="password" id="password" name="password" required class="text-input">
                            <button type="button" onclick="togglePassword()" class="password-toggle">
                                <box-icon id="eye-icon" color="#dc2626" name='show' type='solid'></box-icon>
                            </button>
                        </div>
                    </div>
                    <div class="remember-me">
                        <label class="checkbox-wrapper">
                            <input id="remember-me" name="remember-me" type="checkbox" class="checkbox-input" hidden>
                            <span class="custom-checkbox"></span>
                            <span>&nbsp;&nbsp; Remember me</span>
                        </label>
                        <a href="" class="forgot-password"><span>&nbsp;&nbsp; Forgot Password?</span></a>
                    </div>
                    <?php if ($error_message): ?>
                        <div class="error-message"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <button type="submit" class="submit-button">Log In</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="forgotPasswordModal">&times;</span>
            <h2 class="form-title">Forgot Password</h2>
            <div id="modalMessage"></div>
            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="resetEmail" class="input-label">Email</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='envelope' color="#dc2626" class="input-icon"></box-icon>
                        <input type="email" id="resetEmail" name="email" required class="text-input" autofocus>
                    </div>
                </div>
                <button type="submit" class="submit-button">Send OTP</button>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="resetPasswordModal">&times;</span>
            <h2 class="form-title">Reset Password</h2>
            <div id="resetModalMessage"></div>
            <form id="resetPasswordForm">
                <div class="form-group">
                    <label for="otp" class="input-label">OTP</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='key' color="#dc2626" class="input-icon"></box-icon>
                        <input type="text" id="otp" name="otp" required class="text-input" autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password" class="input-label">New Password</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='lock' color="#dc2626" class="input-icon"></box-icon>
                        <input type="password" id="new_password" name="new_password" required class="text-input">
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="input-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <box-icon type='solid' name='lock' color="#dc2626" class="input-icon"></box-icon>
                        <input type="password" id="confirm_password" name="confirm_password" required class="text-input">
                    </div>
                </div>
                <button type="submit" class="submit-button">Reset Password</button>
            </form>
        </div>
    </div>

    <!-- Floating message -->
    <?php if ($logout_message): ?>
        <div class="floating-message-container">
            <div class="floating-message">
                <?php echo $logout_message; ?>
            </div>
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
        </div>
    <?php endif; ?>

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

        // Replace the existing showFloatingMessage function
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.floating-message-container');
            if (container) {
                // Show message
                requestAnimationFrame(() => {
                    container.style.display = 'block';
                    container.classList.add('show');
                });

                // Hide message after 3 seconds
                setTimeout(() => {
                    container.classList.remove('show');
                    container.classList.add('hide');
                    
                    // Remove element after animation
                    setTimeout(() => {
                        container.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>