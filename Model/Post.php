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
            'region'  => 'ap-southeast-2',
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
        // Validate file type and size before uploading
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Invalid image file.");
        }

        // Generate a unique identifier and append it to the image name
        $uniqueId = uniqid();
        $uniqueImageName = $uniqueId . '-' . basename($imageName);

        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images', // Replace with your S3 bucket name
                'Key'    => 'post-images/' . $uniqueImageName, // Prevent path traversal
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    private function isValidImage($imageData) {
        // Basic validation for image files
        return (strlen($imageData) > 0 && strlen($imageData) <= 25000000); // 25MB limit
    }

    public function savePostImage($postId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Post_Images (post_id, image_url) VALUES (:post_id, :image_url)");
        return $stmt->execute([
            ':post_id' => (int) $postId,  // Force integer conversion
            ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL)
        ]);
    }

    public function getPostImages($postId) {
        $stmt = $this->pdo->prepare("SELECT * FROM Post_Images WHERE post_id = :post_id");
        $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllPosts($limit = 10, $offset = 0) {
        $query = "SELECT p.*, u.username 
                  FROM posts p
                  LEFT JOIN Users u ON p.user_id = u.id
                  LIMIT :limit OFFSET :offset";
    
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPostById($userId, $limit = 10, $offset = 0) {
        $query = "
            SELECT 
                p.*,
                u.username,
                u.profile_picture
            FROM {$this->table} p
            JOIN Users u ON p.user_id = u.id
            WHERE p.user_id = :user_id
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
    
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        // Fetch images for each post
        foreach ($posts as &$post) {
            $postId = $post['id'];
            $imageQuery = "SELECT image_url FROM Post_Images WHERE post_id = :post_id";
            $imageStmt = $this->pdo->prepare($imageQuery);
            $imageStmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
            $imageStmt->execute();
            $post['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    
        return $posts;
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
            return $this->pdo->lastInsertId(); // ✅ Return the post ID
        }
    
        return false; // ❌ Return false if insert failed
    }
    

    public function updatePost($id, $data) {
        $fields = [];
        $params = [':id' => (int) $id];

        if (!empty($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['content'])) {
            $fields[] = "content = :content";
            $params[':content'] = htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['img_url'])) {
            $fields[] = "img_url = :img_url";
            $params[':img_url'] = filter_var($data['img_url'], FILTER_SANITIZE_URL);
        }
        if (isset($data['like_count'])) {
            $fields[] = "like_count = :like_count";
            $params[':like_count'] = (int) $data['like_count'];
        }
        if (isset($data['comment_count'])) {
            $fields[] = "comment_count = :comment_count";
            $params[':comment_count'] = (int) $data['comment_count'];
        }
        if (isset($data['repost_count'])) {
            $fields[] = "repost_count = :repost_count";
            $params[':repost_count'] = (int) $data['repost_count'];
        }
        if (!empty($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['type'])) {
            $fields[] = "type = :type";
            $params[':type'] = htmlspecialchars($data['type'], ENT_QUOTES, 'UTF-8');
        }

        if (empty($fields)) {
            return false; // No fields to update
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
                AND p.status = 'Active' 
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
    
        // Fetch all images for each post
        foreach ($posts as &$post) {
            $imageStmt = $this->pdo->prepare("SELECT image_url FROM Post_Images WHERE post_id = :post_id");
            $imageStmt->bindValue(':post_id', $post['id'], PDO::PARAM_INT);
            $imageStmt->execute();
            $post['images'] = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    
        return $posts;
    }
    

    public function getPostsByUserId($userId, $limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
