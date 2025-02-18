<?php

require_once '../Config/database.php';

try {
    $conn->beginTransaction();

    // Insert sample users
    $conn->exec("INSERT INTO Users (username, email, password, birthdate, number, address, age, profile_picture, bio, favorite_categories, favorite_marketplace_items, friend_count, friend_privacy) VALUES
        ('john_doe', 'john@example.com', 'password123', '1990-01-01', '1234567890', '123 Main St', 30, 'profile1.jpg', 'Bio of John Doe', '[]', '[]', 5, 'Public'),
        ('jane_doe', 'jane@example.com', 'password123', '1992-02-02', '0987654321', '456 Main St', 28, 'profile2.jpg', 'Bio of Jane Doe', '[]', '[]', 10, 'Friends Only')
    ");

    // Insert sample friends
    $conn->exec("INSERT INTO Friends (user_id, friend_id, status) VALUES
        (1, 2, 'accepted')
    ");

    // Insert sample posts
    $conn->exec("INSERT INTO Posts (user_id, title, content, img_url, category, privacy, type, post_type) VALUES
        (1, 'First Post', 'This is the content of the first post', 'image1.jpg', 'Formula 1', 'Public', 'image', 'normal'),
        (2, 'Second Post', 'This is the content of the second post', 'image2.jpg', 'NASCAR', 'Friends Only', 'image', 'normal')
    ");

    // Insert sample marketplace items
    $conn->exec("INSERT INTO Marketplace_Items (seller_id, title, description, price, category, image_url, status) VALUES
        (1, 'Item 1', 'Description of item 1', 10.00, 'Formula 1', 'item1.jpg', 'available'),
        (2, 'Item 2', 'Description of item 2', 20.00, 'NASCAR', 'item2.jpg', 'available')
    ");

    // Insert sample notifications
    $conn->exec("INSERT INTO Notifications (user_id, post_id, marketplace_item_id, type, content) VALUES
        (1, 1, NULL, 'like', 'User 2 liked your post'),
        (2, NULL, 2, 'comment', 'User 1 commented on your marketplace item')
    ");

    // Insert sample admins
    $conn->exec("INSERT INTO Admins (user_id, role) VALUES
        (2, 'content_moderator')
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

    $conn->commit();
    echo "Database seeded successfully!";
} catch (Exception $e) {
    $conn->rollBack();
    echo "Failed to seed database: " . $e->getMessage();
}

?>
