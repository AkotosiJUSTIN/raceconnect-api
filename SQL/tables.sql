-- Create the users table first
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `number` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `age` int(11) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `favorite_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`favorite_categories`)),
  `favorite_marketplace_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`favorite_marketplace_items`)),
  `friends_list` JSON DEFAULT NULL CHECK (JSON_VALID(`friends_list`)),  -- Added friends_list column
  `friend_count` int(11) DEFAULT 0,
  `friend_privacy` enum('Public','Only me','Friends Only') DEFAULT 'Public',
  `last_online` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Active','Banned','Suspended') DEFAULT 'Active',
  `report` enum('None','Reported') DEFAULT 'None',
  `suspension_end_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Now create the admins table
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('content_moderator','community_manager','marketplace_manager') DEFAULT 'content_moderator',
  `failed_attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `posts`
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `like_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `repost_count` int(11) DEFAULT 0,
  `category` enum('Formula 1','24 Hours of Lemans','World Rally Championship','NASCAR','Formula Drift','GT Championship') DEFAULT 'Formula 1',
  `privacy` enum('Public','Only me','Friends Only') DEFAULT 'Public',
  `type` enum('text','image','video') DEFAULT 'text',
  `post_type` enum('Announcement','Normal') DEFAULT 'Normal',
  `status` enum('Active','Hidden','Archived') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `report` enum('none','reported') DEFAULT 'none',
  `archived_at` timestamp NULL DEFAULT NULL,  -- Added archived_at column
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  INDEX `idx_cleanup` (`status`, `archived_at`),  -- Added index
  CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `marketplace_items`
CREATE TABLE `marketplace_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` enum('Formula 1','24 Hours of Lemans','World Rally Championship','NASCAR','Formula Drift','GT Championship') DEFAULT 'Formula 1',
  `favorite_count` int(11) DEFAULT 0,
  `status` enum('Active','Hidden','Archived','Available','Sold','Reserved') DEFAULT 'Available',
  `report` enum('None','Reported') DEFAULT 'None',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `listing_status` enum('Available','Sold','Reserved') DEFAULT 'Available',
  `previous_status` enum('Available','Sold','Reserved') DEFAULT NULL,
  `reported_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,  -- Added archived_at column
  PRIMARY KEY (`id`),
  KEY `seller_id` (`seller_id`),
  INDEX `idx_cleanup` (`status`, `archived_at`),  -- Added index
  CONSTRAINT `marketplace_items_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create the reports table
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) DEFAULT NULL,
  `marketplace_item_id` int(11) DEFAULT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','resolved','hidden') NOT NULL DEFAULT 'pending',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_marketplace_item_id` (`marketplace_item_id`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `fk_report_marketplace` FOREIGN KEY (`marketplace_item_id`) REFERENCES `marketplace_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_report_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`resolved_by`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Now create the admin_notifications table
CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `marketplace_item_id` int(11) DEFAULT NULL,
  `type` enum('post_report','marketplace_report','system_alert','user_report') NOT NULL,
  `content` text NOT NULL,
  `severity` enum('low','medium','high') DEFAULT 'medium',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `report_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_review','resolved','archived') DEFAULT 'pending',
  `action_taken` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,  -- Added archived_at column
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `reporter_id` (`reporter_id`),
  KEY `post_id` (`post_id`),
  KEY `marketplace_item_id` (`marketplace_item_id`),
  KEY `report_id` (`report_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_type_status` (`type`,`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_severity` (`severity`),
  INDEX `idx_cleanup` (`status`, `archived_at`),  -- Added index
  CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_notifications_ibfk_2` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admin_notifications_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_notifications_ibfk_4` FOREIGN KEY (`marketplace_item_id`) REFERENCES `marketplace_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_notifications_ibfk_5` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admin_notifications_ibfk_6` FOREIGN KEY (`resolved_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `user_profile_pictures`
CREATE TABLE `user_profile_pictures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_profile_pictures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `user_tokens`
CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `admin_analytics`
CREATE TABLE `admin_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `total_users` int(11) NOT NULL,
  `total_posts` int(11) NOT NULL,
  `total_reels` int(11) NOT NULL,
  `total_marketplace_items` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `announcements`
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','archived') DEFAULT 'active',
  `file_url` varchar(255) DEFAULT NULL,
  `file_key` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `conversations`
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `last_message` TEXT NULL,  -- Added last_message column
  `last_message_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- Added last_message_time column
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `seller_id` (`seller_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`),
  CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `marketplace_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `friends`
CREATE TABLE `friends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `status` enum('Pending','Accepted','Blocked') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`friend_id`),
  KEY `friend_id` (`friend_id`),
  CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `marketplace_item_images`
CREATE TABLE `marketplace_item_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marketplace_item_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `marketplace_item_id` (`marketplace_item_id`),
  CONSTRAINT `marketplace_item_images_ibfk_1` FOREIGN KEY (`marketplace_item_id`) REFERENCES `marketplace_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `marketplace_item_likes`
CREATE TABLE `marketplace_item_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `marketplace_item_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `marketplace_item_id` (`marketplace_item_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `marketplace_item_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketplace_item_likes_ibfk_2` FOREIGN KEY (`marketplace_item_id`) REFERENCES `marketplace_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketplace_item_likes_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `messages`
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` INT NOT NULL,  -- Added receiver_id column
  `message` text NOT NULL,
  `status` enum('sent','delivered','read') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`)  -- Added foreign key constraint
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `notifications`
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `marketplace_item_id` int(11) DEFAULT NULL,
  `type` enum('post','marketplace','system') NOT NULL DEFAULT 'system',
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `report_id` int(11) DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active',
  `repost_id` int(11) DEFAULT NULL,
  `like_id` int(11) DEFAULT NULL,
  `comment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `password_resets`
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `password_resets_admin`
CREATE TABLE `password_resets_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  CONSTRAINT `password_resets_admin_ibfk_1` FOREIGN KEY (`email`) REFERENCES `admins` (`email`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `post_comments`
CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `post_comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_comments_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `post_images`
CREATE TABLE `post_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `post_images_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `post_likes`
CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `post_likes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_likes_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `post_reposts`
CREATE TABLE `post_reposts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `quote` TEXT DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `post_reposts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_reposts_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `post_reposts_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DELIMITER $$
CREATE TRIGGER `after_report_insert` AFTER INSERT ON `reports` FOR EACH ROW BEGIN
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
