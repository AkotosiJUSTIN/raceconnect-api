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
            'region'  => $_ENV['AWS_REGION'],
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY'],
                'secret' => $_ENV['AWS_SECRET_KEY'],
            ],
            'http' => [
                'verify' => false,
                'timeout' => 30
            ],
            'ssl_verify' => false,
            'debug' => true
        ]);
    }

    // Upload profile picture to S3
    public function uploadProfilePictureToS3($imageData, $imageName) {
        // Validate file type and size before uploading
        if (!$this->isValidImage($imageData)) {
            throw new Exception("Invalid image file.");
        }

        // Generate a unique identifier and append it to the image name
        $uniqueId = uniqid();
        $uniqueImageName = $uniqueId . '-' . basename($imageName);

        try {
            $result = $this->s3->putObject([
                'Bucket' => $_ENV['AWS_S3_BUCKET'],
                'Key'    => 'profile-pictures/' . $uniqueImageName,
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

    public function saveProfilePicture($userId, $imageUrl) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO User_Profile_Pictures (user_id, image_url) VALUES (:user_id, :image_url)");
            $stmt->execute([
                ':user_id' => intval($userId),
                ':image_url' => filter_var($imageUrl, FILTER_SANITIZE_URL)
            ]);
            return true;
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

    public function getUserProfileImages($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM User_Profile_Pictures WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $this->cleanupExpiredOtps();
        
        // Explicitly set timezone to match database/server
        $dateTime = new \DateTime('now', new \DateTimeZone('Asia/Manila')); // Adjust timezone as needed
        $expiresAt = $dateTime->modify('+5 minutes')->format('Y-m-d H:i:s');
        
        error_log("Storing OTP: Email=$email, OTP=$otp, ExpiresAt=$expiresAt, CurrentTime=" . $dateTime->format('Y-m-d H:i:s'));
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO Password_Resets (email, otp, expires_at) 
             VALUES (:email, :otp, :expires_at)
             ON DUPLICATE KEY UPDATE 
             otp = VALUES(otp), 
             created_at = NOW(), 
             expires_at = VALUES(expires_at)"
        );
        
        $result = $stmt->execute([
            ':email' => filter_var($email, FILTER_SANITIZE_EMAIL),
            ':otp' => intval($otp),
            ':expires_at' => $expiresAt
        ]);
        
        if (!$result) {
            error_log("Failed to store OTP: " . implode(", ", $stmt->errorInfo()));
        }
        
        return $result;
    }

    public function verifyOtp($email, $otp) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM Password_Resets 
             WHERE email = :email 
             AND otp = :otp 
             AND expires_at > NOW()"
        );
        $stmt->bindValue(':email', filter_var($email, FILTER_SANITIZE_EMAIL));
        $stmt->bindValue(':otp', intval($otp));
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result === false) {
            error_log("OTP verification failed: Email=$email, OTP=$otp, No matching valid OTP found");
            $debugStmt = $this->pdo->prepare("SELECT * FROM Password_Resets WHERE email = :email");
            $debugStmt->execute([':email' => filter_var($email, FILTER_SANITIZE_EMAIL)]);
            $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Current state: " . json_encode($debugResult));
        } else {
            error_log("OTP verified: Email=$email, OTP=$otp, ExpiresAt=" . $result['expires_at']);
            $this->cleanupExpiredOtps();
        }
        
        return $result !== false;
    }

    private function cleanupExpiredOtps() {
        $stmt = $this->pdo->prepare(
            "DELETE FROM Password_Resets 
             WHERE expires_at <= NOW()"
        );
        $result = $stmt->execute();
        if ($result) {
            error_log("Cleaned up expired OTPs");
        }
        return $result;
    }


    // Delete OTP (unchanged from your version)
    public function deleteOtp($email) {
        $stmt = $this->pdo->prepare(
            "DELETE FROM Password_Resets 
             WHERE email = :email"
        );
        $stmt->bindValue(':email', filter_var($email, FILTER_SANITIZE_EMAIL));
        return $stmt->execute();
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
