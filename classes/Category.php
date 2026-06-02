<?php
// crud for tutorial categories

require_once __DIR__ . '/../config/database.php';

class Category {
    private $conn;
    private $table = 'dbProj_categories';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // all categories sorted a to z
    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY category_name ASC";

        try {
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // single category by id
    public function getById($category_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE category_id = :id LIMIT 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // categories with published tutorial count each
    public function getAllWithCount() {
        $query = "SELECT
                    c.*,
                    COUNT(t.tutorial_id) as tutorial_count
                  FROM " . $this->table . " c
                  LEFT JOIN dbProj_tutorials t ON c.category_id = t.category_id
                    AND t.status = 'published'
                  GROUP BY c.category_id
                  ORDER BY c.category_name ASC";

        try {
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // insert new category admin only
    public function create($name, $description = '') {
        $query = "INSERT INTO " . $this->table . " (category_name, description)
                  VALUES (:name, :description)";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->execute();

            return [
                'success' => true,
                'category_id' => $this->conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            error_log('Category::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create category. Please try again.'];
        }
    }

    // update category name and description
    public function update($category_id, $name, $description = '') {
        $query = "UPDATE " . $this->table . "
                  SET category_name = :name, description = :description
                  WHERE category_id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    // delete category blocked if tutorials still use it
    public function delete($category_id) {
        // check how many tutorials still reference this category
        $checkQuery = "SELECT COUNT(*) as count FROM dbProj_tutorials WHERE category_id = :id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $category_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // tutorials still linked so refuse delete
        if ($result['count'] > 0) {
            return false;
        }

        $query = "DELETE FROM " . $this->table . " WHERE category_id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
