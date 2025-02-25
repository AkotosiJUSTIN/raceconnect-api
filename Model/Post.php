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
            'region'  => 'ap-southeast-2', // Replace with your region
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

        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images', // Replace with your S3 bucket name
                'Key'    => 'post-images/' . basename($imageName), // Prevent path traversal
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    private function isValidImage($imageData) {
        // Basic validation for image files
        return (strlen($imageData) > 0 && strlen($imageData) <= 5000000); // 5MB limit
    }

    public function savePostImage($postId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Post_Images (post_id, image_url) VALUES (:post_id, :image_url)");
        return $stmt->execute([
            ':post_id' => (int) $postId,  // Force integer conversion
            ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL)
        ]);
    }

    public function getAllPosts($limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM posts LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPostById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createPost($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} 
            (user_id, title, content, like_count, comment_count, repost_count, category, type) 
            VALUES (:user_id, :title, :content, :like_count, :comment_count, :repost_count, :category, :type)");
        
        $success = $stmt->execute([
            ':user_id' => (int) $data['user_id'], // Ensure user_id is an integer
            ':title' => htmlspecialchars($data['title'] ?? null, ENT_QUOTES, 'UTF-8'),
            ':content' => htmlspecialchars($data['content'], ENT_QUOTES, 'UTF-8'),
            ':like_count' => (int) ($data['like_count'] ?? 0),
            ':comment_count' => (int) ($data['comment_count'] ?? 0),
            ':repost_count' => (int) ($data['repost_count'] ?? 0),
            ':category' => htmlspecialchars($data['category'] ?? 'Formula 1', ENT_QUOTES, 'UTF-8'),
            ':type' => htmlspecialchars($data['type'] ?? 'text', ENT_QUOTES, 'UTF-8')
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
}
?>
