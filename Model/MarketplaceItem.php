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

class MarketplaceItem {
    private $pdo;
    private $table = "Marketplace_Items";
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

    public function uploadItemImageToS3($imageData, $imageName) {
        // Validate file type and size before uploading
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Invalid image file.");
        }

        // Generate a unique identifier and append it to the image name
        $uniqueId = uniqid();
        $uniqueImageName = $uniqueId . '-' . basename($imageName);

        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images', 
                'Key'    => 'item-images/' . $uniqueImageName,
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

    public function saveItemImage($itemId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Marketplace_Item_Images (marketplace_item_id, image_url) VALUES (:item_id, :image_url)");
        return $stmt->execute([
            ':item_id' => (int) $itemId, // Explicit type conversion
            ':image_url' => htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') // Sanitize URL
        ]);
    }

    public function getItemImages($itemId) {
        $stmt = $this->pdo->prepare("SELECT * FROM Marketplace_Item_Images WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $itemId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllItems($limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getItemById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createItem($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (seller_id, title, description, price, category) 
                                    VALUES (:seller_id, :title, :description, :price, :category)");
        $result = $stmt->execute([
            ':seller_id' => (int) $data['seller_id'],
            ':title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
            ':description' => htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8'),
            ':price' => (float) $data['price'],
            ':category' => htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8')
        ]);

        if ($result) {
            return $this->pdo->lastInsertId(); // Return inserted ID
        }
        return false;
    }

    public function updateItem($id, $data) {
        $fields = [];
        $params = [':id' => (int) $id];

        if (!empty($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($data['price']) && is_numeric($data['price'])) {
            $fields[] = "price = :price";
            $params[':price'] = (float) $data['price'];
        }
        if (!empty($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8');
        }
        if (empty($fields)) {
            return false; // No valid fields to update
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteItem($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
?>
