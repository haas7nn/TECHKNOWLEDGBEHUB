<?php
require_once __DIR__ . '/../config/database.php';

class User {
    /** @var PDO|null */
    private $conn;

    /** @var string */
    private $table = 'dbProj_users';

    /** @var int|null */
    public $user_id;

    /** @var string|null */
    public $full_name;

    /** @var string|null */
    public $email;

    /** @var string|null */
    public $password_hash;

    /** @var string|null */
    public $role;

    /** @var string|null */
    public $profile_picture;

    /** @var string|null */
    public $bio;

    /** @var string|null */
    public $status;

    /** @var string|null */
    public $created_at;

    /** @var string|null */
    public $last_login;

    // connect to db
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }


    // register new user
    public function register($full_name, $email, $password, $role = 'viewer') {
        if (empty($full_name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        if (strlen($full_name) < 3) {
            return ['success' => false, 'message' => 'Name must be at least 3 characters'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        // needs upper lower and digit
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            return [
                'success' => false,
                'message' => 'Password must contain uppercase, lowercase, and number'
            ];
        }

        if (!in_array($role, ['viewer', 'creator'])) {
            return ['success' => false, 'message' => 'Invalid role selected'];
        }

        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // never store plain text passwords
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $query = "INSERT INTO " . $this->table . "
                  (full_name, email, password_hash, role, status, created_at)
                  VALUES (:full_name, :email, :password_hash, :role, 'active', NOW())";

        try {
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Registration successful! You can now login.',
                    'user_id' => $this->conn->lastInsertId()
                ];
            }
        } catch (PDOException $e) {
            error_log('User::register error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }

        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    // log in and populate session
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }

        $query = "SELECT * FROM " . $this->table . "
                  WHERE email = :email AND status = 'active'
                  LIMIT 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (password_verify($password, $user['password_hash'])) {

                    $this->updateLastLogin($user['user_id']);

                    // regenerate id prevents session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id']       = $user['user_id'];
                    $_SESSION['full_name']     = $user['full_name'];
                    $_SESSION['email']         = $user['email'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['last_activity'] = time();

                    return [
                        'success' => true,
                        'message' => 'Login successful',
                        'role' => $user['role'],
                        'user' => [
                            'user_id' => $user['user_id'],
                            'full_name' => $user['full_name'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ]
                    ];
                } else {
                    return ['success' => false, 'message' => 'Invalid email or password'];
                }
            } else {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
        } catch (PDOException $e) {
            error_log('User::login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }

    // true if email already taken
    private function emailExists($email) {
        $query = "SELECT user_id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // update last login time
    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table . "
                  SET last_login = NOW()
                  WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            // login still works even if timestamp fails
            return false;
        }
    }


    // get user row by id
    public function getUserById($user_id) {
        $query = "SELECT user_id, full_name, email, password_hash, role, profile_picture, bio,
                         status, created_at, last_login
                  FROM " . $this->table . "
                  WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // get user row by email
    public function getUserByEmail($email) {
        $query = "SELECT user_id, full_name, email, role, profile_picture, bio,
                         status, created_at, last_login
                  FROM " . $this->table . "
                  WHERE email = :email";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // list users with optional search role and status filters
    public function getAllUsers($search = '', $role = '', $status = '') {
        $query = "SELECT user_id, full_name, email, role, status, created_at, last_login
                  FROM " . $this->table . "
                  WHERE 1=1";

        // two param names because pdo only allows one named placeholder per statement
        if (!empty($search)) {
            $query .= " AND (full_name LIKE :search1 OR email LIKE :search2)";
        }

        if (!empty($role)) {
            $query .= " AND role = :role";
        }

        if (!empty($status)) {
            $query .= " AND status = :status";
        }

        $query .= " ORDER BY created_at DESC";

        try {
            $stmt = $this->conn->prepare($query);

            if (!empty($search)) {
                $searchParam = "%{$search}%";
                $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
                $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
            }

            if (!empty($role)) {
                $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            }

            if (!empty($status)) {
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // count active users per role
    public function getUserCountsByRole() {
        $query = "SELECT
                    role,
                    COUNT(*) as count
                  FROM " . $this->table . "
                  WHERE status = 'active'
                  GROUP BY role";

        try {
            $stmt = $this->conn->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $counts = [
                'admin' => 0,
                'creator' => 0,
                'viewer' => 0,
                'total' => 0
            ];

            foreach ($results as $row) {
                $counts[$row['role']] = (int)$row['count'];
                $counts['total'] += (int)$row['count'];
            }

            return $counts;
        } catch (PDOException $e) {
            return ['admin' => 0, 'creator' => 0, 'viewer' => 0, 'total' => 0];
        }
    }


    // update profile fields and optional picture
    public function updateProfile($user_id, $data) {
        if (isset($data['full_name']) && strlen($data['full_name']) < 3) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET full_name = :full_name,
                      bio = :bio";

        // only update picture if a new one was given
        if (!empty($data['profile_picture'])) {
            $query .= ", profile_picture = :profile_picture";
        }

        $query .= " WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':full_name', $data['full_name'], PDO::PARAM_STR);
            $stmt->bindParam(':bio', $data['bio'], PDO::PARAM_STR);

            if (!empty($data['profile_picture'])) {
                $stmt->bindParam(':profile_picture', $data['profile_picture'], PDO::PARAM_STR);
            }

            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    // change password after verifying old one
    public function changePassword($user_id, $old_password, $new_password) {
        $query = "SELECT password_hash FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        if (!password_verify($old_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters'];
        }

        $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

        $updateQuery = "UPDATE " . $this->table . "
                        SET password_hash = :password_hash
                        WHERE user_id = :user_id";

        try {
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':password_hash', $new_password_hash, PDO::PARAM_STR);
            $updateStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to change password'];
        }

        return ['success' => false, 'message' => 'Failed to change password'];
    }

    // toggle user account status
    public function updateStatus($user_id, $status) {
        if (!in_array($status, ['active', 'inactive'])) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET status = :status
                  WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    // change user role
    public function changeRole($user_id, $role) {
        if (!in_array($role, ['viewer', 'creator', 'admin'])) {
            return false;
        }

        $query = "UPDATE " . $this->table . "
                  SET role = :role
                  WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }


    // get activity counts for one user
    public function getUserStats($user_id) {
        // pdo requires unique placeholder names even for the same value
        $query = "SELECT
                    (SELECT COUNT(*) FROM dbProj_tutorials WHERE instructor_id = :uid1) as total_tutorials,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE user_id = :uid2) as total_comments,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE user_id = :uid3) as total_ratings,
                    (SELECT COUNT(*) FROM dbProj_user_activity WHERE user_id = :uid4 AND activity_type = 'complete') as completed_tutorials
                  FROM " . $this->table . "
                  WHERE user_id = :uid5";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':uid1', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid4', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid5', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [
                'total_tutorials' => 0,
                'total_comments' => 0,
                'total_ratings' => 0,
                'completed_tutorials' => 0
            ];
        } catch (PDOException $e) {
            return [
                'total_tutorials' => 0,
                'total_comments' => 0,
                'total_ratings' => 0,
                'completed_tutorials' => 0
            ];
        }
    }

    // hard delete a user record
    public function deleteUser($user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
