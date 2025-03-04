<?php
// filepath: /c:/xampp/htdocs/RaceConnect-Admin/index.php
// Include the database connection script
require_once __DIR__ . '/../../db_connect.php';

// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    if (isset($_COOKIE['email']) && isset($_COOKIE['admin_name'])) {
        $_SESSION['email'] = $_COOKIE['email'];
        $_SESSION['admin_name'] = $_COOKIE['admin_name'];
    } else {
        // Redirect to login page if not logged in
        header("Location: index_login.php");
        exit();
    }
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
    <script src="assets/javascript/announcements.js" defer></script>
    <script src="assets/javascript/logout_script.js" defer></script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-title">
        <span class="welcomeMsg">System Announcements |<span class="username">&nbsp;<?php echo htmlspecialchars($admin_name); ?></span></span>
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
                            <span>Reported Items</span>
                        </a>
                    </li>
                    <li>
                        <a href="index_posts.php" class="nav-item">
                            <box-icon name='pin' type='solid' color='rgb(185 28 28)'></box-icon>
                            <span>Reported Posts</span>
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
                        <a href="index_announcements.php" class="nav-item active">
                            <box-icon type='solid' name='megaphone' color='white'></box-icon>
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
            <!-- Announcement Form -->
            <!-- Replace existing announcement form with this -->
            <div class="announcement-form-container">
                <h2>Create Announcement</h2>
                <form id="announcementForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="announcementTitle">Title</label>
                        <input type="text" id="announcementTitle" name="announcementTitle" required>
                    </div>

                    <div class="form-group">
                        <label for="announcementContent">Content</label>
                        <textarea id="announcementContent" name="announcementContent" required maxlength="5000"></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span>
                            <span>/5000 characters</span>
                        </div>
                    </div>

                    <div class="form-bottom">
                        <div class="submit-btn-container">
                            <button type="submit" class="submit-btn">
                                <span class="btn-text">Post Announcement</span>
                                <div class="loading-spinner" style="display: none;"></div>
                            </button>
                        </div>
                        
                        <div class="file-upload-container">
                            <label for="announcementImage">
                                Upload Image
                            </label>
                            <input type="file" id="announcementImage" name="announcementImage" accept="image/*">
                            <div class="preview-container" style="display: none;">
                                <div class="file-info">
                                    <box-icon name='file' type='solid' size="sm" color="#374151"></box-icon>
                                    <span id="fileName">No file selected</span>
                                </div>
                                <div class="image-preview-wrapper">
                                    <button type="button" id="removeImage" title="Remove image">×</button>
                                    <img id="imagePreview" src="" alt="Preview" style="display: none;">
                                </div>
                            </div>
                        </div>
                    </div>
            </div>

            <!-- Announcement Section -->
            <div id="announcementContainer">
                <!-- Announcements will be dynamically added here -->
            </div>
        </div>
    </div>
    <!-- Floating Message -->
    <div class="floating-message-container">
        <div class="floating-message">
            <span id="floatingMessage"></span>
            <div class="progress-bar">
                <div class="progress"></div>
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