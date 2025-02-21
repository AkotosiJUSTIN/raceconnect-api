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
                'key'    => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
        ]);
    }

    // Upload profile picture to S3
    public function uploadProfilePictureToS3($imageData, $imageName) {
        try {
            $result = $this->s3->putObject([
                'Bucket' => 'raceconnect-images',
                'Key'    => 'profile-pictures/' . $imageName,
                'Body'   => $imageData
            ]);
            return $result['ObjectURL'];
        } catch (AwsException $e) {
            throw new Exception('Failed to upload image to S3: ' . $e->getMessage());
        }
    }

    // Save profile picture URL to database
    public function saveProfilePicture($userId, $imageUrl) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO User_Profile_Pictures (user_id, image_url) VALUES (:user_id, :image_url)");
            $stmt->execute([
                ':user_id' => intval($userId),
                ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL)
            ]);
        } catch (Exception $e) {
            throw new Exception('Failed to save profile picture: ' . $e->getMessage());
        }
    }

    // Update profile picture in Users table
    public function updateUserProfilePicture($userId, $imageUrl) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET profile_picture = :image_url WHERE id = :user_id");
        return $stmt->execute([
            ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL),
            ':user_id' => intval($userId)
        ]);
    }

    // Get all users with pagination
    public function getAllUsers($limit = 50, $offset = 0) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get user by ID
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get user by email
    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE email = :email");
        $stmt->bindValue(':email', filter_var($email, FILTER_SANITIZE_EMAIL));
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Create a new user
    public function createUser($data) {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (username, email, password) VALUES (:username, :email, :password)");
        return $stmt->execute([
            ':username' => htmlspecialchars($data['username']),
            ':email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT)
        ]);
    }

    // Update user details
    public function updateUser($id, $data) {
        $fields = [];
        $params = [':id' => intval($id)];

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

    // Delete user
    public function deleteUser($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', intval($id), PDO::PARAM_INT);
        return $stmt->execute();
    }

    // User login
    public function loginUser($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE username = :username");
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($user && password_verify($password, $user['password'])) ? $user : false;
    }

    // Store authentication token
    public function storeToken($user_id, $token) {
        $stmt = $this->pdo->prepare("INSERT INTO user_tokens (user_id, token) VALUES (:user_id, :token)");
        return $stmt->execute([
            ':user_id' => intval($user_id),
            ':token' => $token // No hashing to allow direct lookup
        ]);
    }

    // Validate authentication token
    public function validateToken($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_tokens WHERE token = :token");
        $stmt->bindValue(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Revoke authentication token
    public function revokeToken($token) {
        $stmt = $this->pdo->prepare("DELETE FROM user_tokens WHERE token = :token");
        $stmt->bindValue(':token', $token);
        return $stmt->execute();
    }

    // Store OTP
    public function storeOtp($email, $otp) {
        $stmt = $this->pdo->prepare("INSERT INTO Password_Resets (email, otp, created_at) VALUES (:email, :otp, NOW()) 
                                     ON DUPLICATE KEY UPDATE otp = :otp, created_at = NOW()");
        $stmt->bindValue(':email', filter_var($email, FILTER_SANITIZE_EMAIL));
        $stmt->bindValue(':otp', intval($otp));
        return $stmt->execute();
    }

    // Verify OTP (valid for 1 hour)
    public function verifyOtp($email, $otp) {
        $stmt = $this->pdo->prepare("SELECT * FROM Password_Resets WHERE email = :email AND otp = :otp 
                                     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->bindValue(':email', filter_var($email, FILTER_SANITIZE_EMAIL));
        $stmt->bindValue(':otp', intval($otp));
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function deleteOtp($email) {
        error_log("Attempting to delete OTP for: " . $email); // Debug log
    
        $stmt = $this->pdo->prepare("DELETE FROM Password_Resets WHERE email = :email");
        $stmt->bindValue(':email', filter_var($email, FILTER_SANITIZE_EMAIL));
    
        $result = $stmt->execute();
    
        if ($result) {
            error_log("OTP deleted successfully for: " . $email);
        } else {
            error_log("Failed to delete OTP for: " . $email);
            error_log("PDO Error: " . implode(", ", $stmt->errorInfo())); // Log error info
        }
    
        return $result;
    }
    
    

    // Reset password
    public function resetPassword($email, $newPassword) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET password = :password WHERE email = :email");
        return $stmt->execute([
            ':password' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':email' => filter_var($email, FILTER_SANITIZE_EMAIL)
        ]);
    }
}
?>
