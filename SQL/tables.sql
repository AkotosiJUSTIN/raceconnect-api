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
    friends_list JSON DEFAULT NULL,  -- Added friends_list column
    friend_count int(11) DEFAULT 0,
    friend_privacy ENUM('Public', 'Only me', 'Friends Only') DEFAULT 'Public',
    last_online TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('Active', 'Banned', 'Suspended') DEFAULT 'Active',
    report ENUM ('none', 'reported') DEFAULT 'None',
    suspension_end_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE Users
ADD CONSTRAINT chk_favorite_categories CHECK (JSON_VALID(favorite_categories)),
ADD CONSTRAINT chk_favorite_marketplace_items CHECK (JSON_VALID(favorite_marketplace_items)),
ADD CONSTRAINT chk_friends_list CHECK (JSON_VALID(friends_list));  -- Added constraint for friends_list

CREATE UNIQUE INDEX idx_username ON Users(username);
CREATE UNIQUE INDEX idx_email ON Users(email);

-- Friends Table
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
    archived_at timestamp NULL DEFAULT NULL,
    report ENUM('none', 'reported') DEFAULT 'none',
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Announcements Table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    title VARCHAR(255) NOT NULL, 
    content TEXT NOT NULL, 
    file_url varchar(255) DEFAULT NULL,
    file_key varchar(255) DEFAULT NULL,
    image_url varchar(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    status ENUM('active', 'archived') DEFAULT 'active'
);

-- Marketplace Items Table
CREATE TABLE IF NOT EXISTS Marketplace_Items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    category ENUM('Formula 1', '24 Hours of Lemans', 'World Rally Championship', 'NASCAR', 'Formula Drift', 'GT Championship') DEFAULT 'Formula 1',
    listing_status ENUM('Available', 'Sold', 'Reserved') DEFAULT 'Available',
    previous_status ENUM('Available', 'Sold', 'Reserved') DEFAULT NULL,
    favorite_count INT DEFAULT 0,
    status ENUM('Active', 'Hidden', 'Archived') DEFAULT 'Active',
    archived_at timestamp NULL DEFAULT NULL,
    report ENUM ('none', 'reported') DEFAULT 'None',
    reported_at TIMESTAMP NULL DEFAULT NULL,
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
    repost_id INT DEFAULT NULL,
    like_id INT DEFAULT NULL,
    comment_id INT DEFAULT NULL,
    convo_id INT DEFAULT NULL,
    INDEX idx_type_status (type, status),
    INDEX idx_created_at (created_at)
);

ALTER TABLE Notifications
ADD COLUMN trigger_user_id INT DEFAULT NULL,
ADD FOREIGN KEY (trigger_user_id) REFERENCES Users(id) ON DELETE SET NULL;

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

-- Post Likes Table
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

-- Index for user-specific queries on Post_Likes
CREATE INDEX idx_user_id_post_likes ON Post_Likes(user_id);


-- For Post Likes
ALTER TABLE Post_Likes
ADD UNIQUE KEY unique_user_post_like (user_id, post_id);

-- Marketplace Item Likes Table
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

-- For Marketplace Item Likes
ALTER TABLE Marketplace_Item_Likes
ADD UNIQUE KEY unique_user_item_like (user_id, marketplace_item_id);

-- Index for user-specific queries on Marketplace_Item_Likes
CREATE INDEX idx_user_id_marketplace_likes ON Marketplace_Item_Likes(user_id);

-- Post Comments Table (Updated)
CREATE TABLE Post_Comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, 
    post_id INT NOT NULL, 
    owner_id INT NOT NULL, 
    comment TEXT NOT NULL,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Post Reposts Table (Updated)
CREATE TABLE Post_Reposts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, 
    post_id INT NOT NULL, 
    owner_id INT NOT NULL, 
    quote TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Drop the existing table if it exists
DROP TABLE IF EXISTS Password_Resets;

-- Create updated Password_Resets table
CREATE TABLE Password_Resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Changed to NOT NULL with explicit expiration
    FOREIGN KEY (email) REFERENCES Users(email) ON DELETE CASCADE,
    UNIQUE KEY unique_email (email) -- Ensure only one OTP per email
);

-- Create the password_resets_admin table with correct foreign key
CREATE TABLE password_resets_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (email) REFERENCES admins(email) ON DELETE CASCADE
);

-- User Profile Pictures Table
CREATE TABLE User_Profile_Pictures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Post Images Table
CREATE TABLE Post_Images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE CASCADE
);

-- Marketplace Item Images Table
CREATE TABLE Marketplace_Item_Images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marketplace_item_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE CASCADE
);

-- Reports Table
CREATE TABLE Reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT DEFAULT NULL,
    marketplace_item_id INT DEFAULT NULL,
    reporter_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'resolved', 'hidden') DEFAULT 'pending',
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    resolved_by INT NULL,
    FOREIGN KEY (resolved_by) REFERENCES Admins(id) ON DELETE SET NULL,
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
ADD FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE SET NULL;

-- Conversations Table
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    last_message TEXT NULL,
    last_message_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'closed', 'archived') DEFAULT 'active',
    unread_count_buyer INT DEFAULT 0,
    unread_count_seller INT DEFAULT 0,
    last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_conversation (buyer_id, seller_id, product_id),
    INDEX idx_buyer (buyer_id, last_activity_at),
    INDEX idx_seller (seller_id, last_activity_at),
    INDEX idx_product (product_id),
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
    message_type ENUM('text', 'image', 'system') DEFAULT 'text',
    message TEXT NOT NULL,
    media_url VARCHAR(255) NULL,
    status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    INDEX idx_conversation (conversation_id, created_at),
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE Message_Images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE Notifications
ADD COLUMN convo_id INT DEFAULT NULL,
ADD CONSTRAINT fk_notifications_convo_id
FOREIGN KEY (convo_id) 
REFERENCES conversations(id) 
ON DELETE CASCADE;

DROP TABLE IF EXISTS websocket_clients;

CREATE TABLE websocket_clients (
    user_id INT PRIMARY KEY,
    connection_id VARCHAR(255) NOT NULL, -- Use VARCHAR for spl_object_hash
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Admin Notifications Table
CREATE TABLE admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    reporter_id INT NOT NULL,
    post_id INT DEFAULT NULL,
    marketplace_item_id INT DEFAULT NULL,
    type ENUM('post_report', 'marketplace_report', 'system_alert', 'user_report') NOT NULL,
    content TEXT NOT NULL,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    report_id INT DEFAULT NULL,
    status ENUM('pending', 'in_review', 'resolved', 'archived') DEFAULT 'pending',
    action_taken TEXT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL,
    archived_at timestamp NULL DEFAULT NULL,
    FOREIGN KEY (admin_id) REFERENCES Admins(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES Posts(id) ON DELETE SET NULL,
    FOREIGN KEY (marketplace_item_id) REFERENCES Marketplace_Items(id) ON DELETE SET NULL,
    FOREIGN KEY (report_id) REFERENCES Reports(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES Admins(id) ON DELETE SET NULL,
    INDEX idx_cleanup (status, archived_at),
    INDEX idx_type_status (type, status),
    INDEX idx_created_at (created_at),
    INDEX idx_severity (severity)
);

ALTER TABLE admin_notifications
ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL,
ADD INDEX idx_cleanup (status, archived_at);

ALTER TABLE posts
ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL,
ADD INDEX idx_cleanup (status, archived_at);

ALTER TABLE marketplace_items
ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL,
ADD INDEX idx_cleanup (status, archived_at);

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
    INSERT INTO admin_notifications (
        admin_id,
        reporter_id,
        post_id,
        marketplace_item_id,
        type,
        content,
        severity,
        is_read,
        created_at,
        report_id,
        status
    ) 
    SELECT 
        a.id, -- Get the appropriate admin based on role
        NEW.reporter_id,
        NEW.post_id,
        NEW.marketplace_item_id,
        CASE 
            WHEN NEW.post_id IS NOT NULL THEN 'post_report'
            WHEN NEW.marketplace_item_id IS NOT NULL THEN 'marketplace_report'
            ELSE 'user_report'
        END,
        CONCAT(
            'New report from User #', NEW.reporter_id, ': ',
            NEW.reason
        ),
        'medium',
        0,
        NOW(),
        NEW.id,
        'pending'
    FROM Admins a
    WHERE (
        (NEW.post_id IS NOT NULL AND a.role = 'content_moderator')
        OR
        (NEW.marketplace_item_id IS NOT NULL AND a.role IN ('marketplace_manager', 'content_moderator'))
        OR
        (NEW.post_id IS NULL AND NEW.marketplace_item_id IS NULL AND a.role = 'community_manager')
    )
    ORDER BY a.id -- Ensures only one admin is selected
    LIMIT 1;

END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER after_like_insert
AFTER INSERT ON Post_Likes
FOR EACH ROW
BEGIN
    UPDATE Posts
    SET like_count = like_count + 1
    WHERE id = NEW.post_id;
END$$

CREATE TRIGGER after_like_delete
AFTER DELETE ON Post_Likes
FOR EACH ROW
BEGIN
    UPDATE Posts
    SET like_count = like_count - 1
    WHERE id = OLD.post_id;
END$$

DELIMITER ;

DELIMITER //
CREATE TRIGGER after_message_insert
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    IF NEW.sender_id = (SELECT buyer_id FROM conversations WHERE id = NEW.conversation_id) THEN
        UPDATE conversations 
        SET unread_count_seller = unread_count_seller + 1
        WHERE id = NEW.conversation_id;
    ELSE
        UPDATE conversations 
        SET unread_count_buyer = unread_count_buyer + 1
        WHERE id = NEW.conversation_id;
    END IF;
END;//

CREATE TRIGGER after_message_read
AFTER UPDATE ON messages
FOR EACH ROW
BEGIN
    IF NEW.status = 'read' AND OLD.status != 'read' THEN
        IF NEW.receiver_id = (SELECT buyer_id FROM conversations WHERE id = NEW.conversation_id) THEN
            UPDATE conversations 
            SET unread_count_buyer = GREATEST(unread_count_buyer - 1, 0)
            WHERE id = NEW.conversation_id;
        ELSE
            UPDATE conversations 
            SET unread_count_seller = GREATEST(unread_count_seller - 1, 0)
            WHERE id = NEW.conversation_id;
        END IF;
    END IF;
END;//
DELIMITER ;
