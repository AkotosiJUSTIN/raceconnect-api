<?php
namespace Model;
use PDO;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

class Post {
    private $pdo;
    private $table = "Posts";
    private $s3;

    public function __construct($db) {
        $this->pdo = $db;
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => $_ENV['AWS_REGION'],
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
            'http'    => [
                'verify' => false
            ]
        ]);
    }

    public function uploadPostImageToS3($imageData, $imageName) {
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Invalid image file.");
        }

        $uniqueId = uniqid();
        $uniqueImageName = $uniqueId . '-' . basename($imageName);

        try {
            $result = $this->s3->putObject([
                'Bucket' => $_ENV['AWS_S3_BUCKET'],
                'Key'    => 'post-images/' . $uniqueImageName,
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    private function isValidImage($imageData) {
        return (strlen($imageData) > 0 && strlen($imageData) <= 25000000);
    }

    public function savePostImage($postId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Post_Images (post_id, image_url) VALUES (:post_id, :image_url)");
        return $stmt->execute([
            ':post_id' => (int) $postId,
            ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL)
        ]);
    }

    public function getPostImages($postId) {
        $stmt = $this->pdo->prepare("SELECT * FROM Post_Images WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deletePostImages($postId) {
        // Get image URLs
        $images = $this->getPostImages($postId);
        foreach ($images as $image) {
            $url = $image['image_url'];
            // Extract key from URL (e.g., 'post-images/filename')
            $key = parse_url($url, PHP_URL_PATH);
            $key = ltrim($key, '/'); // Remove leading slash
            try {
                $this->s3->deleteObject([
                    'Bucket' => $_ENV['AWS_S3_BUCKET'],
                    'Key'    => $_ENV['AWS_SECRET_KEY']
                ]);
            } catch (AwsException $e) {
                error_log("Failed to delete image from S3: " . $e->getMessage());
                // Continue even if S3 deletion fails to ensure DB cleanup
            }
        }
        // Delete from database
        $stmt = $this->pdo->prepare("DELETE FROM Post_Images WHERE post_id = :post_id");
        return $stmt->execute([':post_id' => (int) $postId]);
    }

    public function deleteSpecificPostImages($postId, $imageIds) {
        if (empty($imageIds)) {
            return;
        }
        // Prepare placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $sql = "DELETE FROM Post_Images WHERE post_id = ? AND id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        // Bind parameters: first is post_id, then the image IDs
        $params = array_merge([$postId], $imageIds);
        $stmt->execute($params);

        // Delete corresponding images from S3
        foreach ($imageIds as $imageId) {
            $stmt = $this->pdo->prepare("SELECT image_url FROM Post_Images WHERE id = ? AND post_id = ?");
            $stmt->execute([$imageId, $postId]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($image) {
                $url = $image['image_url'];
                $key = parse_url($url, PHP_URL_PATH);
                $key = ltrim($key, '/');
                try {
                    $this->s3->deleteObject([
                        'Bucket' => $_ENV['AWS_S3_BUCKET'],
                        'Key'    => $key
                    ]);
                } catch (AwsException $e) {
                    error_log("Failed to delete image $key from S3: " . $e->getMessage());
                }
            }
        }
    }

    public function getAllPosts($limit = 10, $offset = 0) {
        $query = "SELECT p.*, u.username, u.profile_picture
                  FROM posts p
                  LEFT JOIN Users u ON p.user_id = u.id
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPostById($id) {
        $query = "
            SELECT
                p.*,
                u.username,
                u.profile_picture
            FROM {$this->table} p
            LEFT JOIN Users u ON p.user_id = u.id
            WHERE p.id = :id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            $imageQuery = "SELECT id, image_url FROM Post_Images WHERE post_id = :post_id";
            $imageStmt = $this->pdo->prepare($imageQuery);
            $imageStmt->bindValue(':post_id', $id, PDO::PARAM_INT);
            $imageStmt->execute();
            $post['images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC); // Modified to fetch id and image_url
        }

        error_log("Fetched post by ID $id: " . json_encode($post));
        return $post ? $post : null;
    }

    public function createPost($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table}
            (user_id, title, content, like_count, comment_count, repost_count, category, type, privacy)
            VALUES (:user_id, :title, :content, :like_count, :comment_count, :repost_count, :category, :type, :privacy)");

        $success = $stmt->execute([
            ':user_id' => (int) $data['user_id'],
            ':title' => htmlspecialchars($data['title'] ?? null, ENT_QUOTES, 'UTF-8'),
            ':content' => htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8'),
            ':like_count' => (int) ($data['like_count'] ?? 0),
            ':comment_count' => (int) ($data['comment_count'] ?? 0),
            ':repost_count' => (int) ($data['repost_count'] ?? 0),
            ':category' => htmlspecialchars($data['category'] ?? 'Formula 1', ENT_QUOTES, 'UTF-8'),
            ':type' => htmlspecialchars($data['type'] ?? 'text', ENT_QUOTES, 'UTF-8'),
            ':privacy' => htmlspecialchars($data['privacy'] ?? 'Public', ENT_QUOTES, 'UTF-8')
        ]);

        if ($success) {
            return $this->pdo->lastInsertId();
        }

        return false;
    }

    public function updatePost($id, $data) {
        $fields = [];
        $params = [':id' => (int) $id];
    
        // Valid ENUM values
        $validCategories = ['Formula 1', '24 Hours of Lemans', 'World Rally Championship', 'NASCAR', 'Formula Drift', 'GT Championship'];
        $validPrivacies = ['Public', 'Only me', 'Friends Only'];
        $validTypes = ['text', 'image', 'video'];
    
        // Handle content
        if (isset($data['content']) && trim($data['content']) !== '') {
            $fields[] = "content = :content";
            $params[':content'] = htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8');
        } elseif (isset($data['content'])) {
            throw new Exception("Content cannot be empty");
        }
    
        // Handle title
        if (array_key_exists('title', $data)) {
            $fields[] = "title = :title";
            $params[':title'] = trim($data['title']) !== '' ? htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8') : NULL;
        }
    
        // Handle category
        if (!empty($data['category']) && trim($data['category']) !== '') {
            $category = trim($data['category']);
            if (in_array($category, $validCategories)) {
                $fields[] = "category = :category";
                $params[':category'] = $category;
            } else {
                throw new Exception("Invalid category value: $category");
            }
        }
    
        // Handle privacy
        if (!empty($data['privacy']) && trim($data['privacy']) !== '') {
            $privacy = trim($data['privacy']);
            if (in_array($privacy, $validPrivacies)) {
                $fields[] = "privacy = :privacy";
                $params[':privacy'] = $privacy;
            } else {
                throw new Exception("Invalid privacy value: $privacy");
            }
        }
    
        // Handle type
        if (!empty($data['type']) && trim($data['type']) !== '') {
            $type = trim($data['type']);
            if (in_array($type, $validTypes)) {
                $fields[] = "type = :type";
                $params[':type'] = $type;
            } else {
                throw new Exception("Invalid type value: $type");
            }
        }
    
        if (empty($fields)) {
            return false;
        }
    
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deletePost($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getPostsByCategoryAndPrivacy($userId, $categories, $limit = 10, $offset = 0) {
        $categoryMap = [
            'F1'  => 'Formula 1',
            'LEM' => '24 Hours of Lemans',
            'WRC' => 'World Rally Championship',
            'NAS' => 'NASCAR',
            'FD'  => 'Formula Drift',
            'GT'  => 'GT Championship'
        ];

        $fullCategories = [];
        foreach ($categories as $abbr) {
            $abbrUpper = strtoupper($abbr);
            if (!isset($categoryMap[$abbrUpper])) {
                throw new Exception("Invalid category abbreviation: $abbr");
            }
            $fullCategories[] = $categoryMap[$abbrUpper];
        }

        $placeholders = implode(', ', array_map(
            fn($key) => ":category$key",
            array_keys($fullCategories)
        ));

        $query = "
            SELECT
                p.*,
                u.username,
                u.profile_picture
            FROM Posts p
            LEFT JOIN Users u ON p.user_id = u.id
            WHERE
                p.category IN ($placeholders)
                AND p.status IN ('Active', 'Hidden')  -- Include 'Active' and 'Hidden', exclude 'Archived'
                AND (
                    p.privacy = 'Public'
                    OR (
                        p.privacy = 'Friends Only'
                        AND EXISTS (
                            SELECT 1
                            FROM Friends
                            WHERE
                                (
                                    (user_id = :user_id AND friend_id = p.user_id)
                                    OR
                                    (user_id = p.user_id AND friend_id = :user_id)
                                )
                                AND status = 'accepted'
                        )
                    )
                )
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($query);

        foreach ($fullCategories as $key => $category) {
            $stmt->bindValue(":category$key", $category);
        }

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $imageStmt = $this->pdo->prepare("SELECT image_url FROM Post_Images WHERE post_id = :post_id");
            $imageStmt->bindValue(':post_id', $post['id'], PDO::PARAM_INT);
            $imageStmt->execute();
            $post['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return $posts;
    }

    public function getPostByUserId($userId, $limit = 10, $offset = 0) {
        $query = "
            SELECT
                p.*,
                u.username,
                u.profile_picture
            FROM {$this->table} p
            JOIN Users u ON p.user_id = u.id
            WHERE p.user_id = :user_id
            AND p.status IN ('Active', 'Hidden')  -- Include 'Active' and 'Hidden', exclude 'Archived'
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $postId = $post['id'];
            $imageQuery = "SELECT image_url FROM Post_Images WHERE post_id = :post_id";
            $imageStmt = $this->pdo->prepare($imageQuery);
            $imageStmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
            $imageStmt->execute();
            $post['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        error_log("Posts fetched for user $userId: " . json_encode($posts));

        return $posts;
    }
}
?>