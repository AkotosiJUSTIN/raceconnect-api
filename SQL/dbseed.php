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

    // Insert sample users
    $conn->exec("INSERT INTO Users (username, email, password, birthdate, number, address, age, profile_picture, bio, favorite_categories, favorite_marketplace_items, friends_list, friend_privacy, status) VALUES
        ('john_doe', 'john@example.com', 'password123', '1990-01-01', '1234567890', '123 Main St', 30, 'profile1.jpg', 'Bio of John Doe', '[]', '[]', '[]', 'Public', 'Active'),
        ('jane_doe', 'jane@example.com', 'password123', '1992-02-02', '0987654321', '456 Main St', 28, 'profile2.jpg', 'Bio of Jane Doe', '[]', '[]', '[]', 'Friends Only', 'Active')
    ");

    // Insert sample friends
    $conn->exec("INSERT INTO Friends (user_id, friend_id, status) VALUES
        (1, 2, 'Accepted')
    ");

    // Insert sample posts
    $conn->exec("INSERT INTO Posts (user_id, title, content, category, privacy, type, post_type) VALUES
        (1, 'First Post', 'This is the content of the first post', 'Formula 1', 'Public', 'image', 'Normal'),
        (2, 'Second Post', 'This is the content of the second post', 'NASCAR', 'Friends Only', 'image', 'Normal')
    ");

    // Insert sample post images
    $conn->exec("INSERT INTO Post_Images (post_id, image_url) VALUES
        (1, 'image1.jpg'),
        (2, 'image2.jpg')
    ");

    // Insert sample marketplace items
    $conn->exec("INSERT INTO Marketplace_Items (seller_id, title, description, price, category, status) VALUES
        (1, 'Item 1', 'Description of item 1', 10.00, 'Formula 1', 'Available'),
        (2, 'Item 2', 'Description of item 2', 20.00, 'NASCAR', 'Available')
    ");

    // Insert sample marketplace item images
    $conn->exec("INSERT INTO Marketplace_Item_Images (marketplace_item_id, image_url) VALUES
        (1, 'item1.jpg'),
        (2, 'item2.jpg')
    ");

    // Insert sample notifications
    $conn->exec("INSERT INTO Notifications (user_id, post_id, marketplace_item_id, type, content) VALUES
        (1, 1, NULL, 'post', 'User 2 liked your post'),
        (2, NULL, 2, 'marketplace', 'User 1 commented on your marketplace item')
    ");

    // Insert sample admins
    $conn->exec("INSERT INTO Admins (user_id, admin_name, email, password, role) VALUES
        (2, 'Jane Doe', 'jane_admin@example.com', 'adminpassword', 'content_moderator')
    ");

    // Insert sample admin analytics
    $conn->exec("INSERT INTO Admin_Analytics (total_users, total_posts, total_reels, total_marketplace_items, report_date) VALUES
        (2, 2, 0, 2, '2023-01-01')
    ");

    // Insert sample post likes
    $conn->exec("INSERT INTO Post_Likes (user_id, post_id, owner_id) VALUES
        (1, 2, 2),
        (2, 1, 1)
    ");

    // Insert sample marketplace item likes
    $conn->exec("INSERT INTO Marketplace_Item_Likes (user_id, marketplace_item_id, owner_id) VALUES
        (1, 1, 1),
        (2, 2, 2)
    ");

    // Insert sample post comments
    $conn->exec("INSERT INTO Post_Comments (user_id, post_id, owner_id, comment) VALUES
        (1, 1, 1, 'Great post!'),
        (2, 2, 2, 'Interesting!')
    ");

    // Insert sample post reposts
    $conn->exec("INSERT INTO Post_Reposts (user_id, post_id, owner_id) VALUES
        (1, 1, 1),
        (2, 2, 2)
    ");

    // Commit the transaction
    $conn->commit();

    // Re-enable foreign key checks
    $conn->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "Database seeded successfully!";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Failed to seed database: " . $e->getMessage();
}

?>
