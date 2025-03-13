<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Load Composer dependencies

use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Establish database connection using environment variables
try {
    $conn = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

try {
    // Disable foreign key checks so that child rows can be inserted regardless of order
    $conn->exec("SET FOREIGN_KEY_CHECKS=0");

    // Begin a transaction
    $conn->beginTransaction();

    // Optionally clear existing data (uncomment if you want a fresh start)
    /*
    $conn->exec("TRUNCATE TABLE Posts");
    $conn->exec("TRUNCATE TABLE Users");
    */

    // Check if users exist, insert if they don't
    $users = [
        ['username' => 'john_doe', 'email' => 'john@example.com', 'password' => 'password123', 'birthdate' => '1990-01-01', 'number' => '1234567890', 'address' => '123 Main St', 'age' => 30, 'profile_picture' => 'profile1.jpg', 'bio' => 'Bio of John Doe', 'favorite_categories' => '[]', 'favorite_marketplace_items' => '[]', 'friends_list' => '[]', 'friend_privacy' => 'Public', 'status' => 'Active'],
        ['username' => 'jane_doe', 'email' => 'jane@example.com', 'password' => 'password123', 'birthdate' => '1992-02-02', 'number' => '0987654321', 'address' => '456 Main St', 'age' => 28, 'profile_picture' => 'profile2.jpg', 'bio' => 'Bio of Jane Doe', 'favorite_categories' => '[]', 'favorite_marketplace_items' => '[]', 'friends_list' => '[]', 'friend_privacy' => 'Friends Only', 'status' => 'Active']
    ];

    $userIds = [];
    foreach ($users as $user) {
        $checkStmt = $conn->prepare("SELECT id FROM Users WHERE username = :username");
        $checkStmt->execute([':username' => $user['username']]);
        $existingUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            $userIds[$user['username']] = $existingUser['id'];
        } else {
            $insertStmt = $conn->prepare("INSERT INTO Users (username, email, password, birthdate, number, address, age, profile_picture, bio, favorite_categories, favorite_marketplace_items, friends_list, friend_privacy, status) VALUES
                (:username, :email, :password, :birthdate, :number, :address, :age, :profile_picture, :bio, :favorite_categories, :favorite_marketplace_items, :friends_list, :friend_privacy, :status)");
            $insertStmt->execute($user);
            $userIds[$user['username']] = $conn->lastInsertId();
        }
    }

    // Define categories from your Post model
    $categories = [
        'Formula 1',
        '24 Hours of Lemans',
        'World Rally Championship',
        'NASCAR',
        'Formula Drift',
        'GT Championship'
    ];

    // Prepare the Posts insert statement
    $stmt = $conn->prepare("INSERT INTO Posts (user_id, title, content, category, privacy, type, like_count, comment_count, repost_count) VALUES
        (:user_id, :title, :content, :category, :privacy, :type, :like_count, :comment_count, :repost_count)");

    // Generate 10 posts per category
    foreach ($categories as $category) {
        for ($i = 1; $i <= 10; $i++) {
            $user_id = ($i % 2 == 0) ? $userIds['jane_doe'] : $userIds['john_doe']; // Alternate between users
            $title = "Sample Post $i for $category";
            $content = "This is sample post number $i about $category. Lorem ipsum dolor sit amet.";
            $privacy = ($i % 2 == 0) ? 'Public' : 'Friends Only'; // Alternate privacy settings
            $type = 'text'; // No images, so all posts are text type

            $stmt->execute([
                ':user_id' => $user_id,
                ':title' => $title,
                ':content' => $content,
                ':category' => $category,
                ':privacy' => $privacy,
                ':type' => $type,
                ':like_count' => 0,
                ':comment_count' => 0,
                ':repost_count' => 0
            ]);
        }
    }

    // Commit the transaction
    $conn->commit();

    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "Database seeded successfully with 10 posts per category!";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Failed to seed database: " . $e->getMessage();
}

?>