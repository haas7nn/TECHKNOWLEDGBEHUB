<?php
/**
 * user class
 * handles all user database logic
 * Hasan Fardan - 202301686
 */

require_once __DIR__ . '/../config/database.php';

class User {
    // db connection
    /** @var PDO|null */
    private $conn;
    
    /** @var string */
    private $table = 'techknow_users';
    
    // user properties
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
    
    /**
     * constructor to setup database connection
     */
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    // ==================== AUTH LOGIC ====================
    
    /**
     * register a new user
     * @param string $full_name user name
     * @param string $email user email
     * @param string $password plain password to be hashed
     * @param string $role viewer or creator
     * @return array<string, mixed> success and message
     */
    public function register($full_name, $email, $password, $role = 'viewer') {
        // make sure fields are not empty
        if (empty($full_name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        // name validation
        if (strlen($full_name) < 3) {
            return ['success' => false, 'message' => 'Name must be at least 3 characters'];
        }
        
        // check email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // check password length
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        // check password complexity
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            return [
                'success' => false, 
                'message' => 'Password must contain uppercase, lowercase, and number'
            ];
        }
        
        // check the role
        if (!in_array($role, ['viewer', 'creator'])) {
            return ['success' => false, 'message' => 'Invalid role selected'];
        }
        
        // check for existing email
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // secure the password
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // insert statement
        $query = "INSERT INTO " . $this->table . " 
                  (full_name, email, password_hash, role, status, created_at) 
                  VALUES (:full_name, :email, :password_hash, :role, 'active', NOW())";
        
        try {
            $stmt = $this->conn->prepare($query);
            
            // bind parameters
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
            return [
                'success' => false, 
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
        
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
    
    /**
     * user login
     * @param string $email user email
     * @param string $password user password
     * @return array<string, mixed> login results
     */
    public function login($email, $password) {
        // check if inputs are empty
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }
        
        // find active user by email
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE email = :email AND status = 'active' 
                  LIMIT 1";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // verify the password
                if (password_verify($password, $user['password_hash'])) {
                    
                    // update last login time
                    $this->updateLastLogin($user['user_id']);
                    
                    // initialize sessions
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
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
            return [
                'success' => false, 
                'message' => 'Login error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * check if email is in use
     * @param string $email
     * @return bool
     */
    private function emailExists($email) {
        $query = "SELECT user_id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * update the login timestamp
     * @param int $user_id
     * @return bool
     */
    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table . " 
                  SET last_login = NOW() 
                  WHERE user_id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // ==================== GETTING USER DATA ====================
    
    /**
     * fetch user by id
     * @param int $user_id
     * @return array<string, mixed>|false user data
     */
    public function getUserById($user_id) {
        $query = "SELECT user_id, full_name, email, role, profile_picture, bio, 
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
    
    /**
     * fetch user by email
     * @param string $email
     * @return array<string, mixed>|false user data
     */
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
    
    /**
     * fetch all users with optional filters
     * @param string $search search by name or email
     * @param string $role filter by specific role
     * @param string $status filter by account status
     * @return array<int, array<string, mixed>> user list
     */
    public function getAllUsers($search = '', $role = '', $status = '') {
        $query = "SELECT user_id, full_name, email, role, status, created_at, last_login 
                  FROM " . $this->table . " 
                  WHERE 1=1";
        
        // filter by search term
        if (!empty($search)) {
            $query .= " AND (full_name LIKE :search OR email LIKE :search)";
        }
        
        // filter by role
        if (!empty($role)) {
            $query .= " AND role = :role";
        }
        
        // filter by status
        if (!empty($status)) {
            $query .= " AND status = :status";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!empty($search)) {
                $searchParam = "%{$search}%";
                $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
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
    
    /**
     * get counts based on roles
     * @return array<string, int> role counts
     */
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
    
    // ==================== UPDATE DATA ====================
    
    /**
     * update user profile info
     * @param int $user_id
     * @param array<string, string> $data profile info
     * @return bool
     */
    public function updateProfile($user_id, $data) {
        if (isset($data['full_name']) && strlen($data['full_name']) < 3) {
            return false;
        }
        
        $query = "UPDATE " . $this->table . " 
                  SET full_name = :full_name, 
                      bio = :bio";
        
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
    
    /**
     * change password after check
     * @param int $user_id
     * @param string $old_password
     * @param string $new_password
     * @return array<string, mixed> results
     */
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
    
    /**
     * update account status
     * @param int $user_id
     * @param string $status active or inactive
     * @return bool
     */
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
    
    /**
     * change user permissions
     * @param int $user_id
     * @param string $role role type
     * @return bool
     */
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
    
    // ==================== STATISTICS ====================
    
    /**
     * fetch activity statistics
     * @param int $user_id
     * @return array<string, int> user stats
     */
    public function getUserStats($user_id) {
        $query = "SELECT 
                    (SELECT COUNT(*) FROM techknow_tutorials WHERE instructor_id = :user_id) as total_tutorials,
                    (SELECT COUNT(*) FROM techknow_comments WHERE user_id = :user_id) as total_comments,
                    (SELECT COUNT(*) FROM techknow_ratings WHERE user_id = :user_id) as total_ratings,
                    (SELECT COUNT(*) FROM techknow_user_activity WHERE user_id = :user_id AND activity_type = 'complete') as completed_tutorials
                  FROM " . $this->table . " 
                  WHERE user_id = :user_id";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
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
    
    /**
     * remove user record
     * @param int $user_id
     * @return bool
     */
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

// test block
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo '<!DOCTYPE html><html><head><title>User Class Test</title>';
    echo '<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}</style></head><body>';
    echo '<h2>✅ User Class Test</h2>';
    
    try {
        $user = new User();
        echo '<p style="color:green;">✓ User class loaded successfully!</p>';
        
        $users = $user->getAllUsers();
        echo '<p>Users found: <strong>' . count($users) . '</strong></p>';
        
    } catch (Exception $e) {
        echo '<p style="color:red;">✗ Error: ' . $e->getMessage() . '</p>';
    }
    
    echo '</body></html>';
}
?>