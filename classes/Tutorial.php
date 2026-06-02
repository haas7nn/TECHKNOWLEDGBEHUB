<?php
require_once __DIR__ . '/../config/database.php';

class Tutorial {
    private $conn;

    private $table = 'dbProj_tutorials';

    // tutorial fields
    public $tutorial_id;
    public $title;
    public $slug;
    public $short_description;
    public $content;
    public $instructor_id;
    public $category_id;
    public $difficulty;
    public $duration_minutes;
    public $thumbnail;
    public $video_url;
    public $status;
    public $view_count = 0;
    public $avg_rating = 0;
    public $created_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }


    public function create($data, $tags = [], $media = []) {
        if (empty($data['title']) || empty($data['content']) || empty($data['instructor_id'])) {
            return ['success' => false, 'message' => 'Title, content, and instructor are required'];
        }

        if (empty($data['short_description'])) {
            return ['success' => false, 'message' => 'Short description is required.'];
        }
        if (empty($data['category_id'])) {
            return ['success' => false, 'message' => 'Category is required.'];
        }

        // merge tags from data if not passed directly
        if (!empty($data['tags']) && empty($tags)) {
            $tags = $data['tags'];
        }

        $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td>';
        $data['content'] = strip_tags($data['content'], $allowed_tags);

        $slug = $this->generateUniqueSlug($data['title']);

        try {
            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table . "
                      (title, slug, short_description, content, instructor_id, category_id,
                       difficulty, duration_minutes, thumbnail, video_url, status, created_at)
                      VALUES
                      (:title, :slug, :short_description, :content, :instructor_id, :category_id,
                       :difficulty, :duration_minutes, :thumbnail, :video_url, :status, NOW())";

            $stmt = $this->conn->prepare($query);

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

            if (!empty($tags)) {
                $this->attachTags($tutorial_id, $tags);
            }

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
            error_log('Tutorial::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create tutorial. Please try again.'];
        }
    }

    // generate unique slug
    // exclude_id skips current tutorial when editing
    private function generateUniqueSlug($title, $exclude_id = null) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        $original_slug = $slug;
        $counter = 1;

        // increment suffix until slug is free
        while ($this->slugExists($slug, $exclude_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists($slug, $exclude_id = null) {
        // skip current tutorial row when editing
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

    // attach tags to tutorial
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

    // attach media files to tutorial
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


    // paginated published tutorials
    public function getPublished($page = 1, $limit = 12) {
        $offset = ($page - 1) * $limit;

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
                  WHERE t.status = 'published'
                  GROUP BY t.tutorial_id
                  ORDER BY t.created_at DESC
                  LIMIT :limit OFFSET :offset";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // run count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'published'";
            $countStmt = $this->conn->query($countQuery);
            $row = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = $row ? (int)$row['total'] : 0;

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
            error_log('Tutorial::getPublished error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch tutorials.',
                'tutorials' => [],
                'pagination' => []
            ];
        }
    }

    // fetch tutorial with tags and media by slug
    public function getBySlug($slug) {
        $query = "SELECT
                    t.*,
                    c.category_name,
                    u.full_name as instructor_name,
                    u.profile_picture as instructor_avatar,
                    u.bio as instructor_bio,
                    (SELECT AVG(rating) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as avg_rating,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as rating_count,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE tutorial_id = t.tutorial_id AND status = 'approved') as comment_count
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
                $tutorial['tags'] = $this->getTutorialTags($tutorial['tutorial_id']);
                $tutorial['media'] = $this->getTutorialMedia($tutorial['tutorial_id']);

                return $tutorial;
            }

            return false;

        } catch (PDOException $e) {
            return false;
        }
    }

    // fetch tutorial with tags and media by id
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


    // search with filters sort and pagination
    public function search($filters = [], $page = 1, $limit = 12) {
        $offset = ($page - 1) * $limit;
        $where = ["t.status = 'published'"];
        $params = [];

        // fulltext plus short_description LIKE fallback
        if (!empty($filters['search'])) {
            $where[] = "(MATCH(t.title, t.content) AGAINST(:search IN BOOLEAN MODE) OR t.short_description LIKE :search_like)";
            $params[':search']      = $filters['search'];
            // escape % and _ so they are treated as literals
            $params[':search_like'] = '%' . addcslashes($filters['search'], '%_') . '%';
        }

        if (!empty($filters['category_id'])) {
            $where[] = "t.category_id = :category_id";
            $params[':category_id'] = (int)$filters['category_id'];
        }

        if (!empty($filters['difficulty'])) {
            $where[] = "t.difficulty = :difficulty";
            $params[':difficulty'] = $filters['difficulty'];
        }

        if (!empty($filters['instructor_id'])) {
            $where[] = "t.instructor_id = :instructor_id";
            $params[':instructor_id'] = (int)$filters['instructor_id'];
        }

        // fall back to created_at if no publish date
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(COALESCE(t.published_at, t.created_at)) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(COALESCE(t.published_at, t.created_at)) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);

        $orderBy = "t.created_at DESC";
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'popular':
                    $orderBy = "t.view_count DESC";
                    break;
                case 'rating':
                    // tiebreak by newest
                    $orderBy = "avg_rating DESC, t.created_at DESC";
                    break;
                case 'title':
                    $orderBy = "t.title ASC";
                    break;
                case 'oldest':
                    $orderBy = "t.created_at ASC";
                    break;
                case 'relevant':
                    // weighted view count and rating score
                    $orderBy = "(t.view_count * 0.3 + avg_rating * 10) DESC, t.created_at DESC";
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
                    COUNT(DISTINCT cm.comment_id) as comment_count,
                    (SELECT COUNT(*) FROM dbProj_tutorial_media WHERE tutorial_id = t.tutorial_id) > 0 AS has_media
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

            // run count for pagination
            $countQuery = "SELECT COUNT(DISTINCT t.tutorial_id) as total
                          FROM " . $this->table . " t
                          WHERE $whereClause";
            $countStmt = $this->conn->prepare($countQuery);

            // reuse filter params for count query
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $countStmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }

            $countStmt->execute();
            $row = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = $row ? (int)$row['total'] : 0;

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
            error_log('Tutorial::search error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Search failed. Please try again.',
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


    public function update($tutorial_id, $data, $tags = []) {
        try {
            $this->conn->beginTransaction();

            $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td>';
            $data['content'] = strip_tags($data['content'], $allowed_tags);

            $publishedAtSql = '';
            if (!empty($data['status']) && $data['status'] === 'published') {
                // only stamp published_at on first publish
                $checkQuery = "SELECT published_at FROM " . $this->table . " WHERE tutorial_id = :tid LIMIT 1";
                $checkStmt  = $this->conn->prepare($checkQuery);
                $checkStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
                $checkStmt->execute();
                $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (empty($row['published_at'])) {
                    $publishedAtSql = ', published_at = NOW()';
                }
            }

            // check if title changed to decide on new slug
            $currentStmt = $this->conn->prepare(
                "SELECT title, slug FROM " . $this->table . " WHERE tutorial_id = :tid"
            );
            $currentStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
            $currentStmt->execute();
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Tutorial not found'];
            }

            // regenerate slug only if title changed
            $new_slug = $current['slug'];
            if (trim($current['title']) !== trim($data['title'])) {
                $new_slug = $this->generateUniqueSlug($data['title'], $tutorial_id);
            }

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

            // replace tags — delete old then insert new
            if (!empty($tags)) {
                $deleteTagsQuery = "DELETE FROM dbProj_tutorial_tags WHERE tutorial_id = :tutorial_id";
                $deleteStmt = $this->conn->prepare($deleteTagsQuery);
                $deleteStmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $deleteStmt->execute();

                $this->attachTags($tutorial_id, $tags);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Tutorial updated successfully'
            ];

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('Tutorial::update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update tutorial. Please try again.'];
        }
    }


    // soft delete — archives row instead of removing it
    public function delete($tutorial_id) {
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

    // hard delete — removes tutorial tags and media atomically
    public function permanentDelete($tutorial_id) {
        try {
            $this->conn->beginTransaction();

            $deleteTagsQuery = "DELETE FROM dbProj_tutorial_tags WHERE tutorial_id = :tutorial_id";
            $stmt1 = $this->conn->prepare($deleteTagsQuery);
            $stmt1->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt1->execute();

            $deleteMediaQuery = "DELETE FROM dbProj_tutorial_media WHERE tutorial_id = :tutorial_id";
            $stmt2 = $this->conn->prepare($deleteMediaQuery);
            $stmt2->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt2->execute();

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


    // track views — users via activity table trigger guests directly
    public function logView($tutorial_id, $user_id = null) {
        try {
            if ($user_id) {
                // INSERT IGNORE so repeat visits from same user are skipped
                $activityQuery = "INSERT IGNORE INTO dbProj_user_activity
                                  (user_id, tutorial_id, activity_type, activity_date)
                                  VALUES (:user_id, :tutorial_id, 'view', NOW())";
                $activityStmt = $this->conn->prepare($activityQuery);
                $activityStmt->bindParam(':user_id',     $user_id,     PDO::PARAM_INT);
                $activityStmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $activityStmt->execute();
            } else {
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


    // upload and register a media file for a tutorial
    public function uploadMedia($tutorial_id, $file, $type = 'document') {
        require_once __DIR__ . '/FileUpload.php';

        $fileUpload = new FileUpload();

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
            error_log('Tutorial::uploadMedia DB error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'File uploaded but database record failed. Please try again.'];
        }
    }

    // delete media record and remove file from disk
    public function deleteMedia($media_id) {
        $query = "SELECT file_path FROM dbProj_tutorial_media WHERE media_id = :media_id";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $media = $stmt->fetch(PDO::FETCH_ASSOC);

            // ltrim avoids double slash when joining path
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


    // get instructor tutorials with optional status filter
    public function getByInstructor($instructor_id, $status = '') {
        $query = "SELECT
                    t.*,
                    c.category_name,
                    COALESCE(AVG(r.rating), 0) as avg_rating,
                    COUNT(DISTINCT r.rating_id) as rating_count,
                    COUNT(DISTINCT cm.comment_id) as comment_count
                  FROM " . $this->table . " t
                  LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
                  LEFT JOIN dbProj_ratings r ON t.tutorial_id = r.tutorial_id
                  LEFT JOIN dbProj_comments cm ON t.tutorial_id = cm.tutorial_id AND cm.status = 'approved'
                  WHERE t.instructor_id = :instructor_id";

        if (!empty($status)) {
            $query .= " AND t.status = :status";
        }

        $query .= " GROUP BY t.tutorial_id";
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
?>
