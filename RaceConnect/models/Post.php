<?php
class Post {
    private $pdo;
    private $table = "Posts";

    public function __construct($db) {
        $this->pdo = $db;
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
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (user_id, title, content, img_url, type) VALUES (:user_id, :title, :content, :img_url, :type)");
        return $stmt->execute([
            ':user_id' => $data['user_id'],
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':img_url' => $data['img_url'],
            ':type' => $data['type']
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
        if (isset($data['type'])) {
            $fields[] = "type = :type";
            $params[':type'] = $data['type'];
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
