<?php
// Include the database connection script (if needed later)
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
    <title>RaceConnect Admin Dashboard - Announcements</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/announcements.css">
    <link rel="icon" href="./assets/RaceConnectLogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lalezar&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lalezar&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <script src="assets/javascript/navBar.js" defer></script>
    <script src="assets/javascript/announcement.js" defer></script>
    <script src="assets/javascript/logout_script.js" defer></script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-title">
        <span class="welcomeMsg">System Announcements |<span class="username">&nbsp;<?php echo htmlspecialchars($admin_name); ?></span>!</span>
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
                        <a href="index_user.php" class="nav-item">
                            <box-icon name='user' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>User</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_posts.php" class="nav-item">
                            <box-icon name='pin' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>Posts</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_notifs.php" class="nav-item">
                            <box-icon name='bell' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_marketplace.php" class="nav-item">
                            <box-icon name='store' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>Marketplace</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_announcements.php" class="nav-item active">
                            <box-icon type='solid' name='megaphone' color='white'></box-icon>
                            <span>Announcements</span>
                        </a>
                    </li>
                </ul>
                <div class="relative logout-btn">
                    <a href="logout.php" class="dropdown-item">
                        <box-icon name='log-out' color="#b91c1c" size="md" class="user-icon" id="userIcon" ></box-icon>
                    </a>
                </div>
            </nav>
        </aside>
        <!-- Overlay for mobile -->
        <div id="overlay" class="overlay" aria-hidden="true"></div>
        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Announcement Form -->
            <div class="announcement-form-container">
                <h2>Create Announcement</h2>
                <form id="announcementForm">
                    <label for="announcementTitle">Title:</label>
                    <input type="text" id="announcementTitle" name="announcementTitle" placeholder="Enter announcement title" required>

                    <label for="announcementContent">Content:</label>
                    <textarea id="announcementContent" name="announcementContent" rows="5" placeholder="Enter announcement content" required></textarea>

                    <button type="submit" class="submit-btn">Post Announcement</button>
                </form>
            </div>

            <!-- Announcement Section -->
            <div id="announcementContainer">
                <!-- Announcements will be dynamically added here -->
            </div>
        </div>
    </div>
    <!-- Logout Dialog -->
    <div id="logoutDialog" class="dialog-overlay">
        <div class="dialog">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="dialog-buttons">
                <button id="cancelLogout" class="dialog-button">No</button>
                <button id="confirmLogout" class="dialog-button">Yes</button>
            </div>
        </div>
    </div>
</body>
</html>