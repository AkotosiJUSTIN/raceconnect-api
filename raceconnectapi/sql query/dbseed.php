<?php

require_once 'Config/database.php';

try {
    $conn->beginTransaction();

    // Insert sample users
    $conn->exec("INSERT INTO Users (username, email, password, birthdate, number, address, age, profile_picture, bio, favorite_categories, favorite_marketplace_items, role) VALUES
        ('john_doe', 'john@example.com', 'password123', '1990-01-01', '1234567890', '123 Main St', 30, 'profile1.jpg', 'Bio of John Doe', '[]', '[]', 'user'),
        ('jane_doe', 'jane@example.com', 'password123', '1992-02-02', '0987654321', '456 Main St', 28, 'profile2.jpg', 'Bio of Jane Doe', '[]', '[]', 'admin')
    ");

    // Insert sample posts
    $conn->exec("INSERT INTO Posts (user_id, title, content, img_url, type) VALUES
        (1, 'First Post', 'This is the content of the first post', 'image1.jpg', 'image'),
        (2, 'Second Post', 'This is the content of the second post', 'image2.jpg', 'image')
    ");

    // Insert sample reels
    $conn->exec("INSERT INTO Reels (user_id, title, video_url, description) VALUES
        (1, 'First Reel', 'video1.mp4', 'Description of the first reel'),
        (2, 'Second Reel', 'video2.mp4', 'Description of the second reel')
    ");

    // Insert sample marketplace items
    $conn->exec("INSERT INTO Marketplace_Items (seller_id, title, description, price, category, image_url) VALUES
        (1, 'Item 1', 'Description of item 1', 10.00, 'Category 1', 'item1.jpg'),
        (2, 'Item 2', 'Description of item 2', 20.00, 'Category 2', 'item2.jpg')
    ");

    // Insert sample notifications
    $conn->exec("INSERT INTO Notifications (user_id, content) VALUES
        (1, 'Notification for user 1'),
        (2, 'Notification for user 2')
    ");

    // Insert sample admins
    $conn->exec("INSERT INTO Admins (user_id, role) VALUES
        (2, 'content_moderator')
    ");

    // Insert sample admin analytics
    $conn->exec("INSERT INTO Admin_Analytics (total_users, total_posts, total_reels, total_marketplace_items, report_date) VALUES
        (2, 2, 2, 2, '2023-01-01')
    ");

    // Insert sample post likes
    $conn->exec("INSERT INTO Post_Likes (user_id, post_id) VALUES
        (1, 1),
        (2, 2)
    ");

    // Insert sample reel likes
    $conn->exec("INSERT INTO Reel_Likes (user_id, reel_id) VALUES
        (1, 1),
        (2, 2)
    ");

    // Insert sample marketplace item likes
    $conn->exec("INSERT INTO Marketplace_Item_Likes (user_id, marketplace_item_id) VALUES
        (1, 1),
        (2, 2)
    ");

    // Insert sample post comments
    $conn->exec("INSERT INTO Post_Comments (user_id, post_id, comment) VALUES
        (1, 1, 'Comment on post 1'),
        (2, 2, 'Comment on post 2')
    ");

    // Insert sample reel comments
    $conn->exec("INSERT INTO Reel_Comments (user_id, reel_id, comment) VALUES
        (1, 1, 'Comment on reel 1'),
        (2, 2, 'Comment on reel 2')
    ");

    // Insert sample post reposts
    $conn->exec("INSERT INTO Post_Reposts (user_id, post_id) VALUES
        (1, 1),
        (2, 2)
    ");

    // Insert sample reel reposts
    $conn->exec("INSERT INTO Reel_Reposts (user_id, reel_id) VALUES
        (1, 1),
        (2, 2)
    ");

    $conn->commit();
    echo "Database seeded successfully!";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Failed to seed database: " . $e->getMessage();
}

?>