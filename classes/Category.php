<?php
// this class handles everything to do with tutorial categories
// it lets admins create update and delete categories and lets anyone read them

require_once __DIR__ . '/../config/database.php';

class Category {
    // db connection
    private $conn;
    // the name of the categories table
    private $table = 'dbProj_categories';

    // init db
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // fetch every category sorted alphabetically by name
    // returns an array of category rows
    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY category_name ASC";

        try {
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // return an empty list if the query fails
            return [];
        }
    }

    // look up a single category by its id
    // returns the category row or false if nothing was found
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

    // fetch every category and include a count of how many published tutorials each one has
    // this is useful for showing category listings with tutorial counts
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
            // return an empty list if something goes wrong
            return [];
        }
    }

    // add a new category to the database
    // only admins should be calling this
    // returns an array with success and the new category id
    public function create($name, $description = '') {
        $query = "INSERT INTO " . $this->table . " (category_name, description)
                  VALUES (:name, :description)";

        try {
            $stmt = $this->conn->prepare($query);
            // bind the name and optional description
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->execute();

            // return the id of the newly created category
            return [
                'success' => true,
                'category_id' => $this->conn->lastInsertId()
            ];
        } catch (PDOException $e) {
            // return failure with the database error message
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // save changes to an existing category name and description
    // returns true if the update worked or false if something went wrong
    public function update($category_id, $name, $description = '') {
        $query = "UPDATE " . $this->table . "
                  SET category_name = :name, description = :description
                  WHERE category_id = :id";

        try {
            $stmt = $this->conn->prepare($query);
            // bind the new name and description
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            // target the right row with the id
            $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    // remove a category from the database
    // will not delete it if any tutorials are still linked to it
    // returns true on success or false if blocked or something went wrong
    public function delete($category_id) {
        // count how many tutorials are using this category before we try to delete it
        $checkQuery = "SELECT COUNT(*) as count FROM dbProj_tutorials WHERE category_id = :id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':id', $category_id, PDO::PARAM_INT);
        $checkStmt->execute();
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // block the delete if any tutorials are still assigned to this category
        if ($result['count'] > 0) {
            return false;
        }

        // safe to delete so run the delete query
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
