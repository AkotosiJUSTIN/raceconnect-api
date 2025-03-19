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

    public function uploadItemImageToS3($imageData, $imageName) {
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Invalid image file.");
        }

        $uniqueId = uniqid();
        $uniqueImageName = $uniqueId . '-' . basename($imageName);

        try {
            $result = $this->s3->putObject([
                'Bucket' => $_ENV['AWS_S3_BUCKET'],
                'Key'    => 'item-images/' . $uniqueImageName,
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

    public function saveItemImage($itemId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Marketplace_Item_Images (marketplace_item_id, image_url) VALUES (:item_id, :image_url)");
        return $stmt->execute([
            ':item_id' => (int) $itemId,
            ':image_url' => htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8')
        ]);
    }

    public function getItemImages($itemId) {
        $stmt = $this->pdo->prepare("SELECT * FROM Marketplace_Item_Images WHERE marketplace_item_id = :marketplace_item_id");
        $stmt->bindParam(':marketplace_item_id', $itemId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllItems($limit = 10, $offset = 0, $excludeSellerId = null) {
        $query = "SELECT * FROM {$this->table}";
        $params = [
            ':limit' => $limit,
            ':offset' => $offset
        ];
    
        if ($excludeSellerId !== null) {
            $query .= " WHERE seller_id != :exclude_seller_id";
            $params[':exclude_seller_id'] = $excludeSellerId;
        }
    
        $query .= " LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($query);
    
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    
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
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (seller_id, title, description, price, category, status) 
                                    VALUES (:seller_id, :title, :description, :price, :category, 'Active')");
        $result = $stmt->execute([
            ':seller_id' => (int) $data['seller_id'],
            ':title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
            ':description' => htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8'),
            ':price' => (float) $data['price'],
            ':category' => htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8')
        ]);

        if ($result) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    public function updateItem($id, $data) {
        $fields = [];
        $params = [':id' => (int) $id];
    
        // Handle title
        if (isset($data['title']) && $data['title'] !== '') {
            $fields[] = "title = :title";
            $params[':title'] = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
        }
    
        // Handle description
        if (isset($data['description']) && $data['description'] !== '') {
            $fields[] = "description = :description";
            $params[':description'] = htmlspecialchars($data['description'], ENT_QUOTES, 'UTF-8');
        }
    
        // Handle price (required, default to 0.00 if not provided or invalid)
        $price = isset($data['price']) && is_numeric($data['price']) ? (float) $data['price'] : 0.00;
        $fields[] = "price = :price"; // Always include price
        $params[':price'] = $price;
    
        // Handle category
        if (isset($data['category']) && $data['category'] !== '') {
            $fields[] = "category = :category";
            $params[':category'] = htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8');
        }
    
        // Handle listing_status
        if (isset($data['listing_status']) && $data['listing_status'] !== '') {
            $fields[] = "listing_status = :listing_status";
            $params[':listing_status'] = htmlspecialchars($data['listing_status'], ENT_QUOTES, 'UTF-8');
        }
    
        // Handle status
        $status = $data['status'] ?? 'Active';
        if (isset($status) && in_array($status, ['Active', 'Hidden', 'Archived'])) {
            $fields[] = "status = :status";
            $params[':status'] = $status;
        }
    
        if (empty($fields)) {
            return false;
        }
    
        // Debug the query and parameters
        $query = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id";
        error_log("SQL Query: $query");
        error_log("Parameters: " . json_encode($params));
    
        $stmt = $this->pdo->prepare($query);
        $result = $stmt->execute($params);
        if (!$result) {
            error_log("SQL Execute Failed: " . json_encode($stmt->errorInfo()));
        }
        return $result;
    }

    public function deleteItem($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getItemByUserId($userId, $limit = 10, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT 
                mi.*,
                COALESCE(MAX(mii.image_url), '') AS image_url
            FROM {$this->table} mi
            LEFT JOIN Marketplace_Item_Images mii ON mi.id = mii.marketplace_item_id
            WHERE mi.seller_id = :seller_id 
            GROUP BY mi.id
            LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindParam(':seller_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getItemByUserId returned " . count($items) . " items for user $userId");
        return $items;
    }

    
}