<?php
// this class handles everything to do with tutorials
// it covers creating reading updating deleting searching and media uploads

require_once __DIR__ . '/../config/database.php';

class Tutorial {
    // db connection
    /** @var PDO|null */
    private $conn;

    // tutorials table name
    /** @var string */
    private $table = 'dbProj_tutorials';

    // tutorial fields
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

    // init db
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }


    // create tutorial
    // use transaction
    public function create($data, $tags = [], $media = []) {
        // check required fields
        if (empty($data['title']) || empty($data['content']) || empty($data['instructor_id'])) {
            return ['success' => false, 'message' => 'Title, content, and instructor are required'];
        }

        if (empty($data['short_description'])) {
            return ['success' => false, 'message' => 'Short description is required.'];
        }
        if (empty($data['category_id'])) {
            return ['success' => false, 'message' => 'Category is required.'];
        }

        // merge tags
        if (!empty($data['tags']) && empty($tags)) {
            $tags = $data['tags'];
        }

        // sanitize content
        $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td>';
        $data['content'] = strip_tags($data['content'], $allowed_tags);

        // generate slug
        $slug = $this->generateUniqueSlug($data['title']);

        // transaction rollback on fail
        try {
            $this->conn->beginTransaction();

            // insert the main tutorial row
            $query = "INSERT INTO " . $this->table . "
                      (title, slug, short_description, content, instructor_id, category_id,
                       difficulty, duration_minutes, thumbnail, video_url, status, created_at)
                      VALUES
                      (:title, :slug, :short_description, :content, :instructor_id, :category_id,
                       :difficulty, :duration_minutes, :thumbnail, :video_url, :status, NOW())";

            $stmt = $this->conn->prepare($query);

            // bind every field to its placeholder
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
            // grab the id of the row we just inserted
            $tutorial_id = $this->conn->lastInsertId();

            // link any tags to this tutorial if some were supplied
            if (!empty($tags)) {
                $this->attachTags($tutorial_id, $tags);
            }

            // save any media files linked to this tutorial
            if (!empty($media)) {
                $this->attachMedia($tutorial_id, $media);
            }

            // everything worked so commit all the changes
            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Tutorial created successfully',
                'tutorial_id' => $tutorial_id,
                'slug' => $slug
            ];

        } catch (PDOException $e) {
            // something failed so undo all the changes
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create tutorial: ' . $e->getMessage()
            ];
        }
    }

    // build a url friendly slug from the title and make sure it is unique
    // the exclude_id lets us skip the current tutorial when checking during updates
    private function generateUniqueSlug($title, $exclude_id = null) {
        // convert to lowercase and trim whitespace
        $slug = strtolower(trim($title));
        // replace anything that is not a letter number or dash with a dash
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        // collapse multiple consecutive dashes into one
        $slug = preg_replace('/-+/', '-', $slug);
        // remove any leading or trailing dashes
        $slug = trim($slug, '-');

        // keep the original so we can append a number if needed
        $original_slug = $slug;
        $counter = 1;

        // keep trying with an incremented number until we find one that is not taken
        while ($this->slugExists($slug, $exclude_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // check if a slug is already being used by another tutorial
    // returns true if it exists and false if it is free
    private function slugExists($slug, $exclude_id = null) {
        // when editing we skip the tutorial we are currently updating
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

    // attach tags
    private function attachTags($tutorial_id, $tags) {
        $query = "INSERT INTO dbProj_tutorial_tags (tutorial_id, tag_id) VALUES (:tutorial_id, :tag_id)";
        $stmt = $this->conn->prepare($query);

        // insert one row per tag id
        foreach ($tags as $tag_id) {
            $stmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt->bindParam(':tag_id', $tag_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        return true;
    }

    // attach media
    private function attachMedia($tutorial_id, $media) {
        $query = "INSERT INTO dbProj_tutorial_media
                  (tutorial_id, media_type, file_path, file_name, file_size)
                  VALUES (:tutorial_id, :media_type, :file_path, :file_name, :file_size)";

        $stmt = $this->conn->prepare($query);

        // insert one row for each media file
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


    // get published tutorials
    // returns the tutorials and pagination info together
    public function getPublished($page = 1, $limit = 12) {
        // calculate offset
        $offset = ($page - 1) * $limit;

        $query = "SELECT
                    t.*,
                    c.category_name,
                    u.full_name as instructor_name,
                    u.profile_picture as instructor_avatar,
                    (SELECT AVG(rating) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as avg_rating,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as rating_count,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE tutorial_id = t.tutorial_id AND status = 'approved') as comment_count
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

            // count for pages
            $countQuery = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'published'";
            $countStmt = $this->conn->query($countQuery);
            $row = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = $row ? (int)$row['total'] : 0;

            // return with pagination
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
            // return empty on error
            return [
                'success' => false,
                'message' => 'Failed to fetch tutorials: ' . $e->getMessage(),
                'tutorials' => [],
                'pagination' => []
            ];
        }
    }

    // get tutorial by slug
    // also loads the tags and media attached to it
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

            // only proceed if exactly one tutorial matched the slug
            if ($stmt->rowCount() === 1) {
                $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

                // add the tags list to the tutorial data
                $tutorial['tags'] = $this->getTutorialTags($tutorial['tutorial_id']);

                // add the media files list to the tutorial data
                $tutorial['media'] = $this->getTutorialMedia($tutorial['tutorial_id']);

                return $tutorial;
            }

            // not found
            return false;

        } catch (PDOException $e) {
            return false;
        }
    }

    // get tutorial by id
    // also loads the tags and media attached to it
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

            // if a matching tutorial was found attach its tags and media
            if ($stmt->rowCount() === 1) {
                $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);
                $tutorial['tags'] = $this->getTutorialTags($tutorial_id);
                $tutorial['media'] = $this->getTutorialMedia($tutorial_id);
                return $tutorial;
            }

            // nothing found for this id
            return false;

        } catch (PDOException $e) {
            return false;
        }
    }

    // fetch all tags that are linked to a given tutorial
    // returns an array of tag rows
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

    // fetch all media files that are linked to a given tutorial
    // returns the newest files first
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


    // search tutorials
    // supports filtering by category difficulty instructor and date range
    // also supports sorting by newest oldest popular rating title and relevance
    public function search($filters = [], $page = 1, $limit = 12) {
        // calculate how many rows to skip for the current page
        $offset = ($page - 1) * $limit;
        // always start by limiting to published tutorials
        $where = ["t.status = 'published'"];
        $params = [];

        // fulltext search
        // and also do a like match on the short description
        if (!empty($filters['search'])) {
            $where[] = "(MATCH(t.title, t.content) AGAINST(:search IN BOOLEAN MODE) OR t.short_description LIKE :search_like)";
            $params[':search']      = $filters['search'];
            // escape any percent or underscore signs so they are treated as literal characters
            $params[':search_like'] = '%' . addcslashes($filters['search'], '%_') . '%';
        }

        // filter by category
        if (!empty($filters['category_id'])) {
            $where[] = "t.category_id = :category_id";
            $params[':category_id'] = (int)$filters['category_id'];
        }

        // filter by difficulty
        if (!empty($filters['difficulty'])) {
            $where[] = "t.difficulty = :difficulty";
            $params[':difficulty'] = $filters['difficulty'];
        }

        // filter by instructor
        if (!empty($filters['instructor_id'])) {
            $where[] = "t.instructor_id = :instructor_id";
            $params[':instructor_id'] = (int)$filters['instructor_id'];
        }

        // filter date from
        // falls back to created_at if published_at is not set
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(COALESCE(t.published_at, t.created_at)) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        // filter date to
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(COALESCE(t.published_at, t.created_at)) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        // combine filters
        $whereClause = implode(' AND ', $where);

        // default newest first
        $orderBy = "t.created_at DESC";
        // set sort order
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'popular':
                    // most views at the top
                    $orderBy = "t.view_count DESC";
                    break;
                case 'rating':
                    // highest rated first with newest as a tiebreaker
                    $orderBy = "avg_rating DESC, t.created_at DESC";
                    break;
                case 'title':
                    // alphabetical order by title
                    $orderBy = "t.title ASC";
                    break;
                case 'oldest':
                    // oldest tutorials first
                    $orderBy = "t.created_at ASC";
                    break;
                case 'relevant':
                    // combine view count weight and rating weight to get a relevance score
                    $orderBy = "(t.view_count * 0.3 + avg_rating * 10) DESC, t.created_at DESC";
                    break;
                case 'newest':
                default:
                    // fall back to newest first
                    $orderBy = "t.created_at DESC";
                    break;
            }
        }

        // build the main search query with joins for category instructor ratings and comments
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

            // bind params
            foreach ($params as $key => $value) {
                if (is_int($value)) {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }

            // bind pagination
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();

            $tutorials = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // run a separate count query to get the total number of matching results
            $countQuery = "SELECT COUNT(DISTINCT t.tutorial_id) as total
                          FROM " . $this->table . " t
                          WHERE $whereClause";
            $countStmt = $this->conn->prepare($countQuery);

            // reuse the same filter params for the count query
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

            // return the results along with pagination info
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
            // log the error and return an empty result
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


    // save changes to an existing tutorial and optionally swap out its tags
    // also handles slug regeneration if the title changed and sets published_at on first publish
    public function update($tutorial_id, $data, $tags = []) {
        try {
            $this->conn->beginTransaction();

            // strip dangerous html from the content before saving
            $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><a><img><table><thead><tbody><tr><th><td>';
            $data['content'] = strip_tags($data['content'], $allowed_tags);

            // if the status is being set to published check whether published_at is already set
            $publishedAtSql = '';
            if (!empty($data['status']) && $data['status'] === 'published') {
                // only stamp published_at the very first time a tutorial goes live
                $checkQuery = "SELECT published_at FROM " . $this->table . " WHERE tutorial_id = :tid LIMIT 1";
                $checkStmt  = $this->conn->prepare($checkQuery);
                $checkStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
                $checkStmt->execute();
                $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (empty($row['published_at'])) {
                    $publishedAtSql = ', published_at = NOW()';
                }
            }

            // fetch the current title and slug so we can tell if the title changed
            $currentStmt = $this->conn->prepare(
                "SELECT title, slug FROM " . $this->table . " WHERE tutorial_id = :tid"
            );
            $currentStmt->bindParam(':tid', $tutorial_id, PDO::PARAM_INT);
            $currentStmt->execute();
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            // bail out early if the tutorial does not exist
            if (!$current) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Tutorial not found'];
            }

            // keep the existing slug unless the title has actually changed
            $new_slug = $current['slug'];
            if (trim($current['title']) !== trim($data['title'])) {
                // generate a fresh slug for the new title
                $new_slug = $this->generateUniqueSlug($data['title'], $tutorial_id);
            }

            // save all the updated fields to the database
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

            // if a new set of tags was provided remove the old ones first then add the new ones
            if (!empty($tags)) {
                // wipe the existing tag links for this tutorial
                $deleteTagsQuery = "DELETE FROM dbProj_tutorial_tags WHERE tutorial_id = :tutorial_id";
                $deleteStmt = $this->conn->prepare($deleteTagsQuery);
                $deleteStmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $deleteStmt->execute();

                // add the new tag links
                $this->attachTags($tutorial_id, $tags);
            }

            // all steps succeeded so save everything
            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Tutorial updated successfully'
            ];

        } catch (PDOException $e) {
            // something went wrong so roll back everything
            $this->conn->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update tutorial: ' . $e->getMessage()
            ];
        }
    }


    // soft delete a tutorial by setting its status to archived
    // the row stays in the database but will no longer appear to visitors
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

    // permanently remove a tutorial and all of its tags and media files from the database
    // uses a transaction so the delete is all or nothing
    public function permanentDelete($tutorial_id) {
        try {
            $this->conn->beginTransaction();

            // remove all tag links for this tutorial
            $deleteTagsQuery = "DELETE FROM dbProj_tutorial_tags WHERE tutorial_id = :tutorial_id";
            $stmt1 = $this->conn->prepare($deleteTagsQuery);
            $stmt1->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt1->execute();

            // remove all media file records for this tutorial
            $deleteMediaQuery = "DELETE FROM dbProj_tutorial_media WHERE tutorial_id = :tutorial_id";
            $stmt2 = $this->conn->prepare($deleteMediaQuery);
            $stmt2->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt2->execute();

            // finally remove the tutorial row itself
            $deleteTutorialQuery = "DELETE FROM " . $this->table . " WHERE tutorial_id = :tutorial_id";
            $stmt3 = $this->conn->prepare($deleteTutorialQuery);
            $stmt3->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
            $stmt3->execute();

            // commit all three deletes together
            $this->conn->commit();
            return true;

        } catch (PDOException $e) {
            // something failed so put everything back
            $this->conn->rollBack();
            return false;
        }
    }


    // record that someone viewed a tutorial and bump the view counter
    // logged in users get an activity row which the db trigger uses to update the count
    // guests get a direct counter increment instead
    public function logView($tutorial_id, $user_id = null) {
        try {
            if ($user_id) {
                // use insert ignore so only the very first view by this user fires the trigger
                // repeated visits from the same user are silently ignored
                $activityQuery = "INSERT IGNORE INTO dbProj_user_activity
                                  (user_id, tutorial_id, activity_type, activity_date)
                                  VALUES (:user_id, :tutorial_id, 'view', NOW())";
                $activityStmt = $this->conn->prepare($activityQuery);
                $activityStmt->bindParam(':user_id',     $user_id,     PDO::PARAM_INT);
                $activityStmt->bindParam(':tutorial_id', $tutorial_id, PDO::PARAM_INT);
                $activityStmt->execute();
            } else {
                // guest visits do not create an activity row so we update the counter directly
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


    // upload a media file and link it to a tutorial in the database
    // picks the right upload method depending on whether it is an image video or document
    public function uploadMedia($tutorial_id, $file, $type = 'document') {
        require_once __DIR__ . '/FileUpload.php';

        $fileUpload = new FileUpload();

        // call the correct method on fileupload based on what kind of file this is
        if ($type === 'image') {
            $upload_result = $fileUpload->uploadThumbnail($file, 'media_' . $tutorial_id);
        } elseif ($type === 'video') {
            $upload_result = $fileUpload->uploadFile_public($file, 'video', 'tutorials/videos/', 'video_' . $tutorial_id);
        } else {
            $upload_result = $fileUpload->uploadDocument($file, 'doc_' . $tutorial_id);
        }

        // if the file upload itself failed return the error straight away
        if (!$upload_result['success']) {
            return $upload_result;
        }

        // save the file details to the database so the tutorial can reference it
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

            // return success along with the new media id and file path
            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'media_id' => $this->conn->lastInsertId(),
                'file_path' => $upload_result['filepath']
            ];

        } catch (PDOException $e) {
            // the database insert failed so clean up the file we just uploaded
            $fileUpload->deleteFile($upload_result['filepath']);
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // remove a media file record from the database and delete the actual file from disk
    // returns true if both steps worked
    public function deleteMedia($media_id) {
        // look up the file path so we know what to delete from disk
        $query = "SELECT file_path FROM dbProj_tutorial_media WHERE media_id = :media_id";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $media = $stmt->fetch(PDO::FETCH_ASSOC);

            // the file_path already contains the full relative path so just prepend the upload base
            // do not add an extra folder prefix or the path will be wrong
            $file_path = UPLOAD_PATH . ltrim($media['file_path'], '/');

            // delete the database record first
            $deleteQuery = "DELETE FROM dbProj_tutorial_media WHERE media_id = :media_id";
            $deleteStmt  = $this->conn->prepare($deleteQuery);
            $deleteStmt->bindParam(':media_id', $media_id, PDO::PARAM_INT);

            // if the row was removed also delete the file from disk
            if ($deleteStmt->execute()) {
                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path);
                }
                return true;
            }
        }
        return false;
    }


    // get all tutorials that belong to a specific instructor
    // optionally filter by status like published or draft
    public function getByInstructor($instructor_id, $status = '') {
        $query = "SELECT
                    t.*,
                    c.category_name,
                    (SELECT AVG(rating) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as avg_rating,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE tutorial_id = t.tutorial_id) as rating_count,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE tutorial_id = t.tutorial_id AND status = 'approved') as comment_count
                  FROM " . $this->table . " t
                  LEFT JOIN dbProj_categories c ON t.category_id = c.category_id
                  WHERE t.instructor_id = :instructor_id";

        // add the status filter if one was requested
        if (!empty($status)) {
            $query .= " AND t.status = :status";
        }

        // show the most recently created tutorials first
        $query .= " ORDER BY t.created_at DESC";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':instructor_id', $instructor_id, PDO::PARAM_INT);

            // bind the status filter if it was provided
            if (!empty($status)) {
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            // return an empty list if something went wrong
            return [];
        }
    }
}

// end of tutorial class
?>
