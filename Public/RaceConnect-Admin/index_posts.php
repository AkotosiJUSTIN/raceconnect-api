<?php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email'])) {
    // Redirect to login page if not logged in
    header("Location: index_login.html");
    exit();
}

// Get the logged-in user's email and admin_name
$email = $_SESSION['email'];
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Guest';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-behavior: smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RaceConnect Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/user-posts.css">
    <link rel="icon" href="./assets/RaceConnectLogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lalezar&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="assets/javascript/navBar.js" defer></script>
    <script src="assets/javascript/user-posts.js" defer></script>
    <script src="assets/javascript/logout_script.js" defer></script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-title">
        <span class="welcomeMsg">Manage Reported Posts |<span class="username">&nbsp;<?php echo htmlspecialchars($admin_name); ?></span></span>
        </div>
        <div class="header-menu">
            <!-- Mobile Header -->
            <div id="menuButton" aria-label="Toggle menu" class="menu-button" role="button" tabindex="0">
                <box-icon name='menu' type='solid' color="white" size="md"></box-icon>
            </div>

        </div>
    </div>

    <div class="flex">
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo">
                <img src="./assets/RaceConnectLogo.png" alt="RaceConnect Logo" id="rcLogo">
            </div>
            <span class="logo-text">Race Connect</span>
        </div>

            <!-- Navigation Menu -->
            <nav class="nav-menu">
                <ul class="nav-list">
                    <li>
                        <a href="index.php" class="nav-item">
                            <box-icon type='solid' name='dashboard' color='rgb(185 28 28)'></box-icon>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_marketplace.php" class="nav-item">
                            <box-icon name='store' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>Marketplace</span>
                        </a>
                    </li>
                    <li>
                        <a href="#top" class="nav-item active">
                            <box-icon name='pin' type='solid' color='white'></box-icon>
                            <span>Posts</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_user.php" class="nav-item">
                            <box-icon name='user' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>User</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_notifs.php" class="nav-item">
                            <box-icon name='bell' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_announcements.php" class="nav-item">
                            <box-icon type='solid' name='megaphone' color='rgb(185 28 28)'></box-icon>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="nav-item logout-btn" title="Logout">
                            <box-icon name='log-out' color="#b91c1c" size="md" id="userIcon" ></box-icon>
                            <span>Log Out</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Overlay for mobile -->
        <div id="overlay" class="overlay" aria-hidden="true"></div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Posts Section -->
            <div id="postsContainer">
                <!-- Posts will be dynamically added here -->
            </div>
        </div>
    </div>

    <!-- Logout Dialog -->
    <div id="logoutDialog" class="dialog-overlay">
        <div class="dialog">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="dialog-buttons">
                <button id="confirmLogout" class="dialog-button">Yes</button>
                <button id="cancelLogout" class="dialog-button">No</button>
            </div>
        </div>
    </div>
</body>
</html>