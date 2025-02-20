<?php
namespace Model;
use PDO;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';

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

            ],
        ]);
    }

    public function uploadItemImageToS3($imageData, $imageName) {
        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images', // Replace with your S3 bucket name
                'Key'    => 'item-images/' . $imageName,
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    public function saveItemImage($itemId, $imageUrl) {
        $stmt = $this->pdo->prepare("INSERT INTO Marketplace_Item_Images (marketplace_item_id, image_url) VALUES (:item_id, :image_url)");
        return $stmt->execute([
            ':item_id' => $itemId,
            ':image_url' => $imageUrl
        ]);
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
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createItem($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (seller_id, title, description, price, category) 
                                    VALUES (:seller_id, :title, :description, :price, :category)");
        return $stmt->execute([
            ':seller_id' => $data['seller_id'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':price' => $data['price'],
            ':category' => $data['category']
        ]);
    }

    public function updateItem($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['title'])) {
            $fields[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params[':description'] = $data['description'];
        }
        if (isset($data['price'])) {
            $fields[] = "price = :price";
            $params[':price'] = $data['price'];
        }
        if (isset($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = $data['category'];
        }
        if (isset($data['image_url'])) {
            $fields[] = "image_url = :image_url";
            $params[':image_url'] = $data['image_url'];
        }
        if (isset($data['favorite_count'])) {
            $fields[] = "favorite_count = :favorite_count";
            $params[':favorite_count'] = $data['favorite_count'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteItem($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
}
?>
