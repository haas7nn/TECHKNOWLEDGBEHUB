<?php
/**
 * Tutorial Class
 * Handles all tutorial operations - CRUD, search, media, views
 * Hasan Fardan - 202301686
 */

require_once __DIR__ . '/../config/database.php';

class Tutorial {
    // database connection
    /** @var PDO|null */
    private $conn;
    
    /** @var string */
    private $table = 'dbProj_tutorials';
    
    // tutorial properties
    /** @var int|null */
    public $tutorial_id;
    
    /** @var string|null */
    public $title;
    
    /** @var string|null */
    public $slug;
    
    /** @var string|null */
    public $short_description;
    
    /** @var string|null */
    public $content;
    
    /** @var int|null */
    public $instructor_id;
    
    /** @var int|null */
    public $category_id;
    
    /** @var string|null */
    public $difficulty;
    
    /** @var int|null */
    public $duration_minutes;
    
    /** @var string|null */
    public $thumbnail;
    
    /** @var string|null */
    public $video_url;
    
    /** @var string|null */
    public $status;
    
    /** @var int */
    public $view_count = 0;
    
    /** @var float */
    public $avg_rating = 0;
    
    /** @var string|null */
    public $created_at;
    
    /**
     * Constructor - setup database connection
     */
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    // ==================== CREATE TUTORIAL ====================
    
    /**
     * Create a new tutorial with tags and media
     * @param array<string, mixed> $data Tutorial data
     * @param array<int> $tags Array of tag IDs
     * @param array<array<string, mixed>> $media Array of media files
     * @return array<string, mixed> Success status and tutorial_id
     */
    public function create($data, $tags = [], $media = []) {
        // check they actually filled in the important stuff
        if (empty($data['title']) || empty($data['content']) || empty($data['instructor_id'])) {
            return ['success' => false, 'message' => 'Title, content, and instructor are required'];
        }
        
        // Bug 1 fix: tags can arrive inside $data['tags'] OR as the $tags param
        if (!empty($data['tags']) && empty($tags)) {
            $tags = $data['tags'];
        }

        // Bug 13 fix: sanitize TinyMCE HTML content — strip dangerous tags/attrs
        $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td>';
        $data['content'] = strip_tags($data['content'], $allowed_tags);
        
        // make a URL-friendly version of the title
        $slug = $this->generateUniqueSlug($data['title']);
        
        // start transaction for atomic operation
        try {
            $this->conn->beginTransaction();
            
            // insert main tutorial
            $query = "INSERT INTO " . $this->table . " 
                      (title, slug, short_description, content, instructor_id, category_id, 
                       difficulty, duration_minutes, thumbnail, video_url, status, created_at) 
                      VALUES 
                      (:title, :slug, :short_description, :content, :instructor_id, :category_id, 
                       :difficulty, :duration_minutes, :thumbnail, :video_url, :status, NOW())";
            
            $stmt = $this->conn->prepare($query);
            
            // bind all parameters
            $stmt->bindParam(':title', $data['title'], PDO::PARAM_STR);
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->bindParam(':short_description', $data['short_description'], PDO::PARAM_STR);
            $stmt->bindParam(':content', $data['content'], PDO::PARAM_STR);
            $stmt->bindParam(':instructor_id', $data['instructor_id'], PDO::PARAM_INT);
            $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
            $stmt->bindParam(':difficulty', $data['difficulty'], PDO::PARAM_STR);
            $stmt->bindParam(':duration_minutes', $data['duration_minutes'], PDO::PARAM_INT);
            $stmt->bindParam(':thumbnail', $data['thumbnail'], PDO::PARAM_STR);
            $stmt->bindParam(':video_url', $data['video_url'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            
            $stmt->execute();
            $tutorial_id = $this->conn->lastInsertId();
            
            // insert tags if provided
            if (!empty($tags)) {
                $this->attachTags($tutorial_id, $tags);
            }
            
            // insert media files if provided
            if (!empty($media)) {
                $this->attachMedia($tutorial_id, $media);
            }
            
            $this->conn->commit();
            
            return [
                'success' => true, 
                'message' => 'Tutorial created successfully',
                'tutorial_id' => $tutorial_id,
                'slug' => $slug
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false, 
                'message' => 'Failed to create tutorial: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate unique slug from title
     * @param string $title
     * @param int|null $exclude_id tutorial_id to exclude from uniqueness check (for updates)
     * @return string
     */
    private function generateUniqueSlug($title, $exclude_id = null) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug, $exclude_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug already exists
     * @param string $slug
     * @param int|null $exclude_id
     * @return bool
     */
    private function slugExists($slug, $exclude_id = null) {
        if ($exclude_id) {
            $query = "SELECT tutorial_id FROM " . $this->table . " WHERE slug = :slug AND tutorial_id != :eid LIMIT 1";
            $stmt  = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug,       PDO::PARAM_STR);
            $stmt->bindParam(':eid',  $exclude_id, PDO::PARAM_INT);
        } else {
            $query = "SELECT tutorial_id FROM " . $this->table . " WHERE slug = :slug LIMIT 1";
            $stmt  = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Attach tags to tutorial
     * @param int $tutorial_id
     * @param array<int> $tags
     * @return bool
     */
    private function attachTags($tutorial_id, $tags) {
        $query = "INSERT INTO dbProj_tutorial_tags (tutorial_id, tag_id) VALUES (:tutorial_id, :tag_id)";
        $stmt = $this->conn->prepare($query);
        
        foreach ($tags as $tag_id) {
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
            $stmt->execute();
        }
        
        return true;
    }
    
    /**
     * Attach media files to tutorial
     * @param int $tutorial_id
     * @param array<array<string, mixed>> $media
     * @return bool
     */
    private function attachMedia($tutorial_id, $media) {
        $query = "INSERT INTO dbProj_tutorial_media 
                  (tutorial_id, media_type, file_path, file_name, file_size) 
                  VALUES (:tutorial_id, :media_type, :file_path, :file_name, :file_size)";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($media as $file) {
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt->bindParam(':media_type', $file['type'], PDO::PARAM_STR);
            $stmt->bindParam(':file_path', $file['path'], PDO::PARAM_STR);
            $stmt->bindParam(':file_name', $file['name'], PDO::PARAM_STR);
            $stmt->bindParam(':file_size', $file['size'], PDO::PARAM_INT);
            $stmt->execute();
        }
        
        return true;
    }
    
    // ==================== READ OPERATIONS ====================
    
    /**
     * Get all published tutorials with pagination
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array<string, mixed> Tutorials and pagination info
     */
    public function getPublished($page = 1, $limit = 12) {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT 
                    t.*,
                    c.category_name,
                    u.full_name as instructor_name,
                    u.profile_picture as instructor_avatar,
                    (SELECT AVG(rating) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as avg_rating,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as rating_count,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE tutorial_id = t.tutorial_id) as comment_count
                  FROM " . $this->table . " t
                  LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
                  LEFT JOIN dbProj_users u ON t.instructor_id = u.user_id
                  WHERE t.status = 'published'
                  ORDER BY t.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'published'";
            $countStmt = $this->conn->query($countQuery);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'success' => true,
                'tutorials' => $tutorials,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $limit),
                    'total_items' => $total,
                    'items_per_page' => $limit
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch tutorials: ' . $e->getMessage(),
                'tutorials' => [],
                'pagination' => []
            ];
        }
    }
    
    /**
     * Get single tutorial by slug
     * @param string $slug
     * @return array<string, mixed>|false
     */
    public function getBySlug($slug) {
        $query = "SELECT 
                    t.*,
                    c.category_name,
                    u.full_name as instructor_name,
                    u.profile_picture as instructor_avatar,
                    u.bio as instructor_bio,
                    (SELECT AVG(rating) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as avg_rating,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as rating_count,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE tutorial_id = t.tutorial_id) as comment_count
                  FROM " . $this->table . " t
                  LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
                  LEFT JOIN dbProj_users u ON t.instructor_id = u.user_id
                  WHERE t.slug = :slug
                  LIMIT 1";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':slug', $slug, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() === 1) {
                $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // get tags
                $tutorial['tags'] = $this->getTutorialTags($tutorial['tutorial_id']);
                
                // get media files
                $tutorial['media'] = $this->getTutorialMedia($tutorial['tutorial_id']);
                
                return $tutorial;
            }
            
            return false;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get tutorial by ID
     * @param int $tutorial_id
     * @return array<string, mixed>|false
     */
    public function getById($tutorial_id) {
        $query = "SELECT 
                    t.*,
                    c.category_name,
                    u.full_name as instructor_name
                  FROM " . $this->table . " t
                  LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
                  LEFT JOIN dbProj_users u ON t.instructor_id = u.user_id
                  WHERE t.tutorial_id = :tutorial_id
                  LIMIT 1";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() === 1) {
                $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);
                $tutorial['tags'] = $this->getTutorialTags($tutorial_id);
                $tutorial['media'] = $this->getTutorialMedia($tutorial_id);
                return $tutorial;
            }
            
            return false;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get tags for a tutorial
     * @param int $tutorial_id
     * @return array<int, array<string, mixed>>
     */
    private function getTutorialTags($tutorial_id) {
        $query = "SELECT t.tag_id, t.tag_name 
                  FROM dbProj_tags t
                  INNER JOIN dbProj_tutorial_tags tt ON t.tag_id = tt.tag_id
                  WHERE tt.tutorial_id = :tutorial_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get media files for a tutorial
     * @param int $tutorial_id
     * @return array<int, array<string, mixed>>
     */
    private function getTutorialMedia($tutorial_id) {
        $query = "SELECT * FROM dbProj_tutorial_media 
                  WHERE tutorial_id = :tutorial_id 
                  ORDER BY uploaded_at DESC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    // ==================== SEARCH AND FILTER ====================
    
    /**
 * Full-text search with filters - FIXED VERSION
 * @param array<string, mixed> $filters Search parameters
 * @param int $page Page number
 * @param int $limit Items per page
 * @return array<string, mixed> Search results
 */
public function search($filters = [], $page = 1, $limit = 12) {
    $offset = ($page - 1) * $limit;
    $where = ["t.status = 'published'"];
    $params = [];
    $useFullText = false;
    
    // Full-text search on title and content (FIXED)
    if (!empty($filters['search'])) {
        // Use LIKE instead of MATCH for broader compatibility
        // PDO named params can only be bound once per statement, so use unique names
        $where[] = "(t.title LIKE :search1 OR t.short_description LIKE :search2 OR t.content LIKE :search3)";
        $searchParam = '%' . $filters['search'] . '%';
        $params[':search1'] = $searchParam;
        $params[':search2'] = $searchParam;
        $params[':search3'] = $searchParam;
        $useFullText = true;
    }
    
    // Filter by category
    if (!empty($filters['category_id'])) {
        $where[] = "t.category_id = :category_id";
        $params[':category_id'] = (int)$filters['category_id'];
    }
    
    // Filter by difficulty
    if (!empty($filters['difficulty'])) {
        $where[] = "t.difficulty = :difficulty";
        $params[':difficulty'] = $filters['difficulty'];
    }
    
    // Filter by instructor
    if (!empty($filters['instructor_id'])) {
        $where[] = "t.instructor_id = :instructor_id";
        $params[':instructor_id'] = (int)$filters['instructor_id'];
    }
    
    // Build WHERE clause
    $whereClause = implode(' AND ', $where);
    
    // Determine ORDER BY
    $orderBy = "t.created_at DESC";
    if (!empty($filters['sort'])) {
        switch ($filters['sort']) {
            case 'popular':
                $orderBy = "t.view_count DESC";
                break;
            case 'rating':
                $orderBy = "avg_rating DESC, t.created_at DESC";
                break;
            case 'title':
                $orderBy = "t.title ASC";
                break;
            case 'oldest':
                $orderBy = "t.created_at ASC";
                break;
            case 'relevant':
                // Bug 35 fix: relevance = view_count weight + avg_rating weight
                $orderBy = "(t.view_count * 0.3 + COALESCE(AVG(r.rating), 0) * 10) DESC, t.created_at DESC";
                break;
            case 'newest':
            default:
                $orderBy = "t.created_at DESC";
                break;
        }
    }
    
    $query = "SELECT 
                t.*,
                c.category_name,
                u.full_name as instructor_name,
                u.profile_picture as instructor_avatar,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(DISTINCT r.rating_id) as rating_count,
                COUNT(DISTINCT cm.comment_id) as comment_count
              FROM " . $this->table . " t
              LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
              LEFT JOIN dbProj_users u ON t.instructor_id = u.user_id
              LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
              LEFT JOIN dbProj_comments cm ON t.tutorial_id = cm.tutorial_id AND cm.status = 'approved'
              WHERE $whereClause
              GROUP BY t.tutorial_id
              ORDER BY $orderBy
              LIMIT :limit OFFSET :offset";
    
    try {
        $stmt = $this->conn->prepare($query);
        
        // Bind all filter parameters with proper types
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countQuery = "SELECT COUNT(DISTINCT t.tutorial_id) as total 
                      FROM " . $this->table . " t
                      WHERE $whereClause";
        $countStmt = $this->conn->prepare($countQuery);
        
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $countStmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'success' => true,
            'tutorials' => $tutorials,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total > 0 ? ceil($total / $limit) : 0,
                'total_items' => (int)$total,
                'items_per_page' => $limit
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Search failed: ' . $e->getMessage(),
            'tutorials' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'items_per_page' => $limit
            ]
        ];
    }
}
    
    // ==================== UPDATE ====================
    
    /**
     * Update tutorial
     * @param int $tutorial_id
     * @param array<string, mixed> $data
     * @param array<int> $tags
     * @return array<string, mixed>
     */
    public function update($tutorial_id, $data, $tags = []) {
        try {
            $this->conn->beginTransaction();
            
            // Bug 13 fix: sanitize TinyMCE content on update too
            $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td>';
            $data['content'] = strip_tags($data['content'], $allowed_tags);

            // Bug 14 fix: update published_at when status flips to published
            $publishedAtSql = '';
            if (!empty($data['status']) && $data['status'] === 'published') {
                // only set published_at if not already set (first publish)
                $checkQuery = "SELECT published_at FROM " . $this->table . " WHERE tutorial_id = :tid LIMIT 1";
                $checkStmt  = $this->conn->prepare($checkQuery);
                $checkStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
                $checkStmt->execute();
                $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (empty($row['published_at'])) {
                    $publishedAtSql = ', published_at = NOW()';
                }
            }

            // if the title changed, we need a new URL slug too
            // fetch the current title to compare
            $currentStmt = $this->conn->prepare(
                "SELECT title, slug FROM " . $this->table . " WHERE tutorial_id = :tid"
            );
            $currentStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
            $currentStmt->execute();
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            $new_slug = $current['slug']; // keep existing by default
            if ($current && trim($current['title']) !== trim($data['title'])) {
                $new_slug = $this->generateUniqueSlug($data['title'], $tutorial_id);
            }

            // save the changes to the database
            $query = "UPDATE " . $this->table . " 
                      SET title = :title,
                          slug = :slug,
                          short_description = :short_description,
                          content = :content,
                          category_id = :category_id,
                          difficulty = :difficulty,
                          duration_minutes = :duration_minutes,
                          thumbnail = :thumbnail,
                          video_url = :video_url,
                          status = :status,
                          updated_at = NOW()
                          $publishedAtSql
                      WHERE tutorial_id = :tutorial_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':title',             $data['title'],            PDO::PARAM_STR);
            $stmt->bindParam(':slug',              $new_slug,                 PDO::PARAM_STR);
            $stmt->bindParam(':short_description', $data['short_description'], PDO::PARAM_STR);
            $stmt->bindParam(':content',           $data['content'],           PDO::PARAM_STR);
            $stmt->bindParam(':category_id',       $data['category_id'],       PDO::PARAM_INT);
            $stmt->bindParam(':difficulty',        $data['difficulty'],        PDO::PARAM_STR);
            $stmt->bindParam(':duration_minutes',  $data['duration_minutes'],  PDO::PARAM_INT);
            $stmt->bindParam(':thumbnail',         $data['thumbnail'],         PDO::PARAM_STR);
            $stmt->bindParam(':video_url',         $data['video_url'],         PDO::PARAM_STR);
            $stmt->bindParam(':status',            $data['status'],            PDO::PARAM_STR);
            $stmt->bindParam(':tutorial_id',       $tutorial_id,               PDO::PARAM_INT);
            
            $stmt->execute();
            
            // update tags if provided
            if (!empty($tags)) {
                // remove old tags
                $deleteTagsQuery = "DELETE FROM dbProj_tutorial_tags WHERE tutorial_id = :tutorial_id";
                $deleteStmt = $this->conn->prepare($deleteTagsQuery);
                $deleteStmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $deleteStmt->execute();
                
                // add new tags
                $this->attachTags($tutorial_id, $tags);
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Tutorial updated successfully'
            ];
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update tutorial: ' . $e->getMessage()
            ];
        }
    }
    
    // ==================== DELETE ====================
    
    /**
     * Delete tutorial (soft delete by changing status)
     * @param int $tutorial_id
     * @return bool
     */
    public function delete($tutorial_id) {
        // soft delete - change status to archived
        $query = "UPDATE " . $this->table . " 
                  SET status = 'archived', updated_at = NOW() 
                  WHERE tutorial_id = :tutorial_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Permanently delete tutorial
     * @param int $tutorial_id
     * @return bool
     */
    public function permanentDelete($tutorial_id) {
        try {
            $this->conn->beginTransaction();
            
            // delete tags
            $deleteTagsQuery = "DELETE FROM dbProj_tutorial_tags WHERE tutorial_id = :tutorial_id";
            $stmt1 = $this->conn->prepare($deleteTagsQuery);
            $stmt1->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt1->execute();
            
            // delete media
            $deleteMediaQuery = "DELETE FROM dbProj_tutorial_media WHERE tutorial_id = :tutorial_id";
            $stmt2 = $this->conn->prepare($deleteMediaQuery);
            $stmt2->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt2->execute();
            
            // delete tutorial
            $deleteTutorialQuery = "DELETE FROM " . $this->table . " WHERE tutorial_id = :tutorial_id";
            $stmt3 = $this->conn->prepare($deleteTutorialQuery);
            $stmt3->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt3->execute();
            
            $this->conn->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return false;
        }
    }
    
    // ==================== VIEW COUNT ====================
    
    /**
     * Log tutorial view and increment counter
     * @param int $tutorial_id
     * @param int|null $user_id
     * @return bool
     */
    public function logView($tutorial_id, $user_id = null) {
        try {
            if ($user_id) {
                // Bug 2 fix: use INSERT IGNORE so the trigger only fires on the
                // very first view (new row). Repeat visits by the same user are
                // silently ignored — no UPDATE, no second trigger fire.
                $activityQuery = "INSERT IGNORE INTO dbProj_user_activity 
                                  (user_id, tutorial_id, activity_type, activity_date) 
                                  VALUES (:user_id, :tutorial_id, 'view', NOW())";
                $activityStmt = $this->conn->prepare($activityQuery);
                $activityStmt->bindParam(':user_id',     $user_id,     PDO::PARAM_INT);
                $activityStmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $activityStmt->execute();
            } else {
                // Guest view — no activity row, no trigger, so increment manually
                $query = "UPDATE " . $this->table . " 
                          SET view_count = view_count + 1 
                          WHERE tutorial_id = :tutorial_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // ==================== MEDIA MANAGEMENT ====================
    
/**
 * Upload and attach media file (using FileUpload class)
 * @param int $tutorial_id
 * @param array<string, mixed> $file $_FILES array element
 * @param string $type Type of media (image/video/document)
 * @return array<string, mixed>
 */
public function uploadMedia($tutorial_id, $file, $type = 'document') {
    require_once __DIR__ . '/FileUpload.php';
    
    $fileUpload = new FileUpload();

    // Bug 9 fix: call the correct upload method based on media type
    if ($type === 'image') {
        $upload_result = $fileUpload->uploadThumbnail($file, 'media_' . $tutorial_id);
    } elseif ($type === 'video') {
        $upload_result = $fileUpload->uploadFile_public($file, 'video', 'tutorials/videos/', 'video_' . $tutorial_id);
    } else {
        $upload_result = $fileUpload->uploadDocument($file, 'doc_' . $tutorial_id);
    }
    
    if (!$upload_result['success']) {
        return $upload_result;
    }
    
    // save to database
    $query = "INSERT INTO dbProj_tutorial_media 
              (tutorial_id, media_type, file_path, file_name, file_size) 
              VALUES (:tutorial_id, :media_type, :file_path, :file_name, :file_size)";
    
    try {
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
        $stmt->bindParam(':media_type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':file_path', $upload_result['filepath'], PDO::PARAM_STR);
        $stmt->bindParam(':file_name', $upload_result['filename'], PDO::PARAM_STR);
        $stmt->bindParam(':file_size', $upload_result['size'], PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'media_id' => $this->conn->lastInsertId(),
            'file_path' => $upload_result['filepath']
        ];
        
    } catch (PDOException $e) {
        $fileUpload->deleteFile($upload_result['filepath']);
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
    
    /**
     * Delete media file
     * @param int $media_id
     * @return bool
     */
    public function deleteMedia($media_id) {
        $query = "SELECT file_path FROM dbProj_tutorial_media WHERE media_id = :media_id";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Bug 30 fix: file_path from FileUpload already contains the full
            // relative path (e.g. "tutorials/thumbnails/file.jpg").
            // Don't prepend 'tutorials/' again — use UPLOAD_PATH directly.
            $file_path = UPLOAD_PATH . ltrim($media['file_path'], '/');
            
            $deleteQuery = "DELETE FROM dbProj_tutorial_media WHERE media_id = :media_id";
            $deleteStmt  = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);
            
            if ($deleteStmt->execute()) {
                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path);
                }
                return true;
            }
        }
        return false;
    }
    
    // ==================== INSTRUCTOR TUTORIALS ====================
    
    /**
     * Get all tutorials by instructor
     * @param int $instructor_id
     * @param string $status Filter by status
     * @return array<int, array<string, mixed>>
     */
    public function getByInstructor($instructor_id, $status = '') {
        $query = "SELECT 
                    t.*,
                    c.category_name,
                    (SELECT AVG(rating) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as avg_rating,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as rating_count,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE tutorial_id = t.tutorial_id) as comment_count
                  FROM " . $this->table . " t
                  LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
                  WHERE t.instructor_id = :instructor_id";
        
        if (!empty($status)) {
            $query .= " AND t.status = :status";
        }
        
        $query .= " ORDER BY t.created_at DESC";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);
            
            if (!empty($status)) {
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return [];
        }
    }
}

// End of Tutorial class
?>
