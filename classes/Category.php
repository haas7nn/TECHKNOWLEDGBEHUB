<?php
/**
 * Category Class
 * Manages tutorial categories
 * Role 1 Deliverable
 */

require_once __DIR__ . '/../config/database.php';

class Category {
    private $conn;
    private $table = 'dbProj_categories';
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /**
     * Get all categories
     * @return array<int, array<string, mixed>>
     */
    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY category_name ASC";
        
        try {
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get category by ID
     * @param int $category_id
     * @return array<string, mixed>|false
     */
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
    
    /**
     * Get category with tutorial count
     * @return array<int, array<string, mixed>>
     */
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
    
    /**
     * Create category (Admin only)
     * @param string $name
     * @param string $description
     * @return array<string, mixed>
     */
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
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update category
     * @param int $category_id
     * @param string $name
     * @param string $description
     * @return bool
     */
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
    
    /**
     * Delete category
     * @param int $category_id
     * @return bool
     */
    public function delete($category_id) {
        // Check if category has tutorials
        $checkQuery = "SELECT COUNT(*) as count FROM dbProj_tutorials WHERE category_id = :id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $category_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return false; // Cannot delete category with tutorials
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