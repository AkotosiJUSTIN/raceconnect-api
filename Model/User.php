<?php
namespace Model;
use PDO;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class User {
    private $pdo;
    private $table = "Users";
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

    public function uploadProfilePictureToS3($imageData, $imageName) {
        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images', // Replace with your S3 bucket name
                'Key'    => 'profile-pictures/' . $imageName,
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    public function saveProfilePicture($userId, $imageUrl) {
        $this->pdo->beginTransaction();
        try {
            // Insert into User_Profile_Pictures table (history of uploads)
            $stmt = $this->pdo->prepare("INSERT INTO User_Profile_Pictures (user_id, image_url) VALUES (:user_id, :image_url)");
            $stmt->execute([
                ':user_id' => $userId,
                ':image_url' => $imageUrl
            ]);
<<<<<<< HEAD
            
=======
>>>>>>> eba74726ee12920a0b50c6837e32c3372220af15
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception('Failed to save profile picture: ' . $e->getMessage());
        }
    }

    public function updateUserProfilePicture($userId, $imageUrl) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET profile_picture = :image_url WHERE id = :user_id");
        return $stmt->execute([
            ':image_url' => $imageUrl,
            ':user_id' => $userId
        ]);
    }

    public function getAllUsers() {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (username, email, password) VALUES (:username, :email, :password)");
        return $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT)
        ]);
    }

    public function updateUser($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if ($key === 'password') {
                $value = password_hash($value, PASSWORD_BCRYPT);
            } elseif (in_array($key, ['favorite_categories', 'favorite_marketplace_items'])) {
                $value = json_encode($value);
            }
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE id = :id");
        return $stmt->execute($params);
    }

    public function deleteUser($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function loginUser($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function storeToken($user_id, $token) {
        $stmt = $this->pdo->prepare("INSERT INTO user_tokens (user_id, token) VALUES (:user_id, :token)");
        return $stmt->execute([
            ':user_id' => $user_id,
            ':token' => $token
        ]);
    }

    public function validateToken($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_tokens WHERE token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC); // Return token data if valid, else null
    }

    public function revokeToken($token) {
        $stmt = $this->pdo->prepare("DELETE FROM user_tokens WHERE token = :token");
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    }

    public function storeOtp($email, $otp) {
        $stmt = $this->pdo->prepare("INSERT INTO Password_Resets (email, otp) VALUES (:email, :otp) ON DUPLICATE KEY UPDATE otp = :otp, created_at = CURRENT_TIMESTAMP");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':otp', $otp);
        return $stmt->execute();
    }

    public function verifyOtp($email, $otp) {
        $stmt = $this->pdo->prepare("SELECT * FROM Password_Resets WHERE email = :email AND otp = :otp AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':otp', $otp);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function resetPassword($email, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET password = :password WHERE email = :email");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        return $stmt->execute();
    }
}
?>