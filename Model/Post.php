<?php
namespace Model;
use PDO;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';

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
            
            ],
        ]);
    }

    public function uploadPostImageToS3($imageData, $imageName) {
        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images', // Replace with your S3 bucket name
                'Key'    => 'post-images/' . $imageName,
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    public function savePostImage($postId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Post_Images (post_id, image_url) VALUES (:post_id, :image_url)");
        return $stmt->execute([
            ':post_id' => $postId,
            ':image_url' => $imageUrl
        ]);
    }

    public function getAllPosts() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPostById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createPost($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} 
            (user_id, title, content, img_url, like_count, comment_count, repost_count, category, type) 
            VALUES (:user_id, :title, :content, :img_url, :like_count, :comment_count, :repost_count, :category, :type)");
        
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':title' => $data['title'] ?? null,
            ':content' => $data['content'],
            ':img_url' => $data['img_url'] ?? null,
            ':like_count' => $data['like_count'] ?? 0,
            ':comment_count' => $data['comment_count'] ?? 0,
            ':repost_count' => $data['repost_count'] ?? 0,
            ':category' => $data['category'] ?? 'Formula 1',
            ':type' => $data['type'] ?? 'text'
        ]);
    }

    public function updatePost($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        if (isset($data['content'])) {
            $fields[] = "content = :content";
            $params[':content'] = $data['content'];
        }
        if (isset($data['img_url'])) {
            $fields[] = "img_url = :img_url";
            $params[':img_url'] = $data['img_url'];
        }
        if (isset($data['like_count'])) {
            $fields[] = "like_count = :like_count";
            $params[':like_count'] = $data['like_count'];
        }
        if (isset($data['comment_count'])) {
            $fields[] = "comment_count = :comment_count";
            $params[':comment_count'] = $data['comment_count'];
        }
        if (isset($data['repost_count'])) {
            $fields[] = "repost_count = :repost_count";
            $params[':repost_count'] = $data['repost_count'];
        }
        if (isset($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = $data['category'];
        }
        if (isset($data['type'])) {
            $fields[] = "type = :type";
            $params[':type'] = $data['type'];
        }

        if (empty($fields)) {
            return false; // No fields to update
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deletePost($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>
