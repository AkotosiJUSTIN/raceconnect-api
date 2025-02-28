-- Users Table
CREATE TABLE Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    birthdate DATE NOT NULL,
    number VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    age INT,
    profile_picture VARCHAR(255),
    bio TEXT,
    favorite_categories JSON,
    favorite_marketplace_items JSON,
    friend_count INT DEFAULT 0,  
    friend_privacy ENUM('Public', 'Only me', 'Friends Only') DEFAULT 'Public',
    last_online TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('Active', 'Banned', 'Suspended') DEFAULT 'Active',
    report ENUM ('None', 'Reported') DEFAULT 'None',
    suspension_end_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE Users
ADD CONSTRAINT chk_favorite_categories CHECK (JSON_VALID(favorite_categories)),
ADD CONSTRAINT chk_favorite_marketplace_items CHECK (JSON_VALID(favorite_marketplace_items));

CREATE UNIQUE INDEX idx_username ON Users(username);
CREATE UNIQUE INDEX idx_email ON Users(email);

-- Table Friends
CREATE TABLE Friends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Blocked') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES Users(id) ON DELETE CASCADE,
    UNIQUE (user_id, friend_id)
);

CREATE INDEX idx_status ON Friends(status);

-- User Tokens Table
CREATE TABLE user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Posts Table (for Social Media)
CREATE TABLE Posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    like_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    repost_count INT DEFAULT 0,
    category ENUM('Formula 1', '24 Hours of Lemans', 'World Rally Championship', 'NASCAR', 'Formula Drift', 'GT Championship') DEFAULT 'Formula 1',
    privacy ENUM('Public', 'Only me', 'Friends Only') DEFAULT 'Public',
    type ENUM('text', 'image', 'video') DEFAULT 'text',
    post_type ENUM('Announcement', 'Normal') DEFAULT 'Normal',
    status ENUM('Active', 'Hidden', 'Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    report ENUM('none', 'reported') DEFAULT 'none',
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Marketplace Items Table
CREATE TABLE Marketplace_Items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    category ENUM('Formula 1', '24 Hours of Lemans', 'World Rally Championship', 'NASCAR', 'Formula Drift', 'GT Championship') DEFAULT 'Formula 1',
    favorite_count INT DEFAULT 0,
    status ENUM('Active', 'Hidden', 'Archived', 'Available', 'Sold', 'Reserved') DEFAULT 'Available',
    report ENUM ('None', 'Reported') DEFAULT 'None',
    reported_at TIMESTAMP NULL,
    previous_status ENUM('Available', 'Sold', 'Reserved') DEFAULT NULL,
    listing_status ENUM('Available', 'Sold', 'Reserved') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE INDEX idx_seller_id ON Marketplace_Items(seller_id);
CREATE INDEX idx_status ON Marketplace_Items(status);

-- Notifications Table (Includes Likes, Comments, Reposts)
CREATE TABLE Notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    post_id INT DEFAULT NULL,
    marketplace_item_id INT DEFAULT NULL,
    type ENUM('post', 'marketplace', 'system', 'report') NOT NULL DEFAULT 'system',
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    report_id INT DEFAULT NULL,
    status ENUM('active', 'archived') DEFAULT 'active',
    INDEX idx_type_status (type, status),
    INDEX idx_created_at (created_at)
);

-- Admins Table
CREATE TABLE Admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('content_moderator', 'community_manager',  'marketplace_manager') DEFAULT 'content_moderator',
    failed_attempts INT DEFAULT 0,
    last_attempt DATETIME,
    remember_token VARCHAR(255),
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Admin Dashboard Analytics Table
CREATE TABLE Admin_Analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_users INT NOT NULL,
    total_posts INT NOT NULL,
    total_reels INT NOT NULL,
    total_marketplace_items INT NOT NULL,
    report_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Post_Likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,  -- User who liked
    post_id INT NOT NULL,  -- Post being liked
    owner_id INT NOT NULL, -- Owner of the post
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Marketplace_Item_Likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,  -- User who liked
    marketplace_item_id INT NOT NULL,  -- Marketplace item being liked
    owner_id INT NOT NULL, -- Seller (original owner of the item)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Post Comments Table (Updated)
CREATE TABLE Post_Comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- User who commented
    post_id INT NOT NULL, -- Post being commented on
    owner_id INT NOT NULL, -- Owner of the post
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Post Reposts Table (Updated)
CREATE TABLE Post_Reposts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- User who reposted
    post_id INT NOT NULL, -- Post being reposted
    owner_id INT NOT NULL, -- Owner of the post
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Password Resets Table
CREATE TABLE Password_Resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE
);

-- Create the password_resets table with correct foreign key
CREATE TABLE password_resets_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (email) REFERENCES admins(email) ON DELETE CASCADE
);

CREATE TABLE User_Profile_Pictures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

CREATE TABLE Post_Images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE
);

CREATE TABLE Marketplace_Item_Images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marketplace_item_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE CASCADE
);

CREATE TABLE Reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT DEFAULT NULL,
    marketplace_item_id INT DEFAULT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Dismissed', 'Resolved', 'Hidden') NOT NULL DEFAULT 'Pending',
    FOREIGN KEY (reporter_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE SET NULL,
    FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE SET NULL,
    INDEX idx_post_id (post_id),
    INDEX idx_marketplace_item_id (marketplace_item_id)
);

ALTER TABLE Reports
MODIFY COLUMN post_id INT NULL,
MODIFY COLUMN marketplace_item_id INT NULL,
ADD FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE SET NULL,
ADD FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE SET NULL,
ADD INDEX idx_post_id (post_id),
ADD INDEX idx_marketplace_item_id (marketplace_item_id);


-- Conversations 
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    last_message TEXT NULL,
    last_message_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (buyer_id, seller_id, product_id),
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES marketplace_items(id) ON DELETE CASCADE
);

-- Messages Table 
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'delivered', 'read') DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Update the report trigger to handle both types
DROP TRIGGER IF EXISTS after_report_insert;

DELIMITER $$
CREATE TRIGGER after_report_insert
AFTER INSERT ON reports
FOR EACH ROW
BEGIN
    -- Update post or marketplace item report status
    IF NEW.post_id IS NOT NULL THEN
        UPDATE posts SET report = 'reported' WHERE id = NEW.post_id;
    ELSEIF NEW.marketplace_item_id IS NOT NULL THEN
        UPDATE marketplace_items SET report = 'reported' WHERE id = NEW.marketplace_item_id;
    END IF;

    -- Create notification for admin
    INSERT INTO notifications (
        user_id,
        post_id,
        marketplace_item_id,
        type,
        content,
        is_read,
        created_at,
        report_id,
        status
    ) VALUES (
        1, -- admin user_id
        NEW.post_id,
        NEW.marketplace_item_id,
        CASE 
            WHEN NEW.post_id IS NOT NULL THEN 'post'
            ELSE 'marketplace'
        END,
        CONCAT('New report: ', NEW.reason),
        0,
        NOW(),
        NEW.id,
        'active'
    );
END$$
DELIMITER ;

CREATE TRIGGER notify_post_repost
AFTER INSERT ON Post_Reposts
FOR EACH ROW
INSERT INTO Notifications (user_id, post_id, type, content, created_at)
VALUES (NEW.owner_id, NEW.post_id, 'repost', 
                CONCAT('User ', NEW.user_id, ' reposted your post'), NOW());

CREATE TRIGGER notify_post_comment
AFTER INSERT ON Post_Comments
FOR EACH ROW
INSERT INTO Notifications (user_id, post_id, type, content, created_at)
VALUES (NEW.owner_id, NEW.post_id, 'comment', 
                CONCAT('User ', NEW.user_id, ' commented on your post'), NOW());

CREATE TRIGGER notify_marketplace_like
AFTER INSERT ON Marketplace_Item_Likes
FOR EACH ROW
INSERT INTO Notifications (user_id, marketplace_item_id, type, content, created_at)
VALUES (NEW.owner_id, NEW.marketplace_item_id, 'marketplace_like', 
                CONCAT('User ', NEW.user_id, ' liked your marketplace item'), NOW());

CREATE TRIGGER notify_post_like
AFTER INSERT ON Post_Likes
FOR EACH ROW
INSERT INTO Notifications (user_id, post_id, type, content, created_at)
VALUES (NEW.owner_id, NEW.post_id, 'like', CONCAT('User ', NEW.user_id, ' liked your post'), NOW());

DELIMITER $$

CREATE TRIGGER after_message_insert
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    -- Insert a notification for the receiver
    INSERT INTO notifications (user_id, marketplace_item_id, type, content, is_read, created_at, status)
    VALUES (
        (SELECT 
            CASE 
                WHEN NEW.sender_id = c.buyer_id THEN c.seller_id 
                ELSE c.buyer_id 
            END 
        FROM conversations c 
        WHERE c.id = NEW.conversation_id),
        (SELECT product_id FROM conversations WHERE id = NEW.conversation_id),
        'marketplace',
        CONCAT('New message from User ', NEW.sender_id),
        0, -- Unread notification
        NOW(),
        'active'
    );
END $$

DELIMITER ;
