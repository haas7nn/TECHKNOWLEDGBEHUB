<?php
// this class handles everything to do with users in the database

require_once __DIR__ . '/../config/database.php';

class User {
    // db connection
    /** @var PDO|null */
    private $conn;

    // the name of the users table
    /** @var string */
    private $table = 'dbProj_users';

    // these are the fields that belong to a user
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

    // init db
    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }


    // register a new user and save them to the database
    // returns an array with success status and a message
    public function register($full_name, $email, $password, $role = 'viewer') {
        // make sure none of the required fields are empty
        if (empty($full_name) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        // the name needs to be at least 3 characters long
        if (strlen($full_name) < 3) {
            return ['success' => false, 'message' => 'Name must be at least 3 characters'];
        }

        // check the email is in a valid format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        // the password must be at least 8 characters
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }

        // make sure the password has uppercase letters lowercase letters and a number
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
            return [
                'success' => false,
                'message' => 'Password must contain uppercase, lowercase, and number'
            ];
        }

        // only allow the two valid roles
        if (!in_array($role, ['viewer', 'creator'])) {
            return ['success' => false, 'message' => 'Invalid role selected'];
        }

        // stop registration if the email is already taken
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // hash the password before storing it so we never save it in plain text
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // build the insert query to add the new user
        $query = "INSERT INTO " . $this->table . "
                  (full_name, email, password_hash, role, status, created_at)
                  VALUES (:full_name, :email, :password_hash, :role, 'active', NOW())";

        try {
            $stmt = $this->conn->prepare($query);

            // safely bind each value to the query placeholder
            $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);

            // run the query and return success with the new user id
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Registration successful! You can now login.',
                    'user_id' => $this->conn->lastInsertId()
                ];
            }
        } catch (PDOException $e) {
            // something went wrong with the database
            error_log('User::register error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }

        // fall through if execute returned false without throwing
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    // log a user in by checking their email and password
    // returns their info and role on success
    public function login($email, $password) {
        // both fields are required so check they are not empty
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }

        // look up the active user with this email
        $query = "SELECT * FROM " . $this->table . "
                  WHERE email = :email AND status = 'active'
                  LIMIT 1";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            // check if we found exactly one matching user
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // compare the submitted password against the stored hash
                if (password_verify($password, $user['password_hash'])) {

                    // record the time of this login
                    $this->updateLastLogin($user['user_id']);

                    // generate a new session id to prevent session fixation attacks
                    session_regenerate_id(true);

                    // save their info to the session so we know who they are
                    $_SESSION['user_id']       = $user['user_id'];
                    $_SESSION['full_name']     = $user['full_name'];
                    $_SESSION['email']         = $user['email'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['last_activity'] = time();

                    // return their details so the caller can use them
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
                    // password did not match so deny access
                    return ['success' => false, 'message' => 'Invalid email or password'];
                }
            } else {
                // no active user found with that email
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
        } catch (PDOException $e) {
            // database error during login attempt
            error_log('User::login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }

    // check if a given email address already exists in the database
    // returns true if it does and false if it is available
    private function emailExists($email) {
        $query = "SELECT user_id FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        // any row found means the email is already taken
        return $stmt->rowCount() > 0;
    }

    // stamp the last login time for the given user
    // returns true if the update worked and false if it failed
    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table . "
                  SET last_login = NOW()
                  WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            // silently return false so login still succeeds even if timestamp fails
            return false;
        }
    }


    // fetch a single user by their id
    // returns their data as an array or false if not found
    public function getUserById($user_id) {
        $query = "SELECT user_id, full_name, email, password_hash, role, profile_picture, bio,
                         status, created_at, last_login
                  FROM " . $this->table . "
                  WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            // return whatever was found or false if nothing matched
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // fetch a single user by their email address
    // returns their data as an array or false if not found
    public function getUserByEmail($email) {
        $query = "SELECT user_id, full_name, email, role, profile_picture, bio,
                         status, created_at, last_login
                  FROM " . $this->table . "
                  WHERE email = :email";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            // return the user row or false if nothing was found
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return false;
        }
    }

    // get a list of all users with optional filters for search role and status
    // returns an array of user rows
    public function getAllUsers($search = '', $role = '', $status = '') {
        // start with a base query that matches everyone
        $query = "SELECT user_id, full_name, email, role, status, created_at, last_login
                  FROM " . $this->table . "
                  WHERE 1=1";

        // add a name or email search filter if a search term was given
        // two unique param names are used because pdo only lets you bind a named placeholder once per statement
        if (!empty($search)) {
            $query .= " AND (full_name LIKE :search1 OR email LIKE :search2)";
        }

        // narrow results down to a specific role if one was requested
        if (!empty($role)) {
            $query .= " AND role = :role";
        }

        // narrow results down to a specific status if one was requested
        if (!empty($status)) {
            $query .= " AND status = :status";
        }

        // show newest accounts first
        $query .= " ORDER BY created_at DESC";

        try {
            $stmt = $this->conn->prepare($query);

            // bind the search term with wildcards for partial matching
            if (!empty($search)) {
                $searchParam = "%{$search}%";
                $stmt->bindValue(':search1', $searchParam, PDO::PARAM_STR);
                $stmt->bindValue(':search2', $searchParam, PDO::PARAM_STR);
            }

            // bind the role filter if it was set
            if (!empty($role)) {
                $stmt->bindParam(':role', $role, PDO::PARAM_STR);
            }

            // bind the status filter if it was set
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

    // count how many active users there are for each role
    // returns an array with keys for admin creator viewer and total
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

            // start all counts at zero
            $counts = [
                'admin' => 0,
                'creator' => 0,
                'viewer' => 0,
                'total' => 0
            ];

            // add each role count from the database results into the array
            foreach ($results as $row) {
                $counts[$row['role']] = (int)$row['count'];
                $counts['total'] += (int)$row['count'];
            }

            return $counts;
        } catch (PDOException $e) {
            // return zeroes if the query fails
            return ['admin' => 0, 'creator' => 0, 'viewer' => 0, 'total' => 0];
        }
    }


    // save updated profile info for a user
    // also updates the profile picture if one was provided
    public function updateProfile($user_id, $data) {
        // reject the update if the name is too short
        if (isset($data['full_name']) && strlen($data['full_name']) < 3) {
            return false;
        }

        // build the update query starting with the fields that always change
        $query = "UPDATE " . $this->table . "
                  SET full_name = :full_name,
                      bio = :bio";

        // only update the profile picture column if a new one was provided
        if (!empty($data['profile_picture'])) {
            $query .= ", profile_picture = :profile_picture";
        }

        // close the query with the where condition
        $query .= " WHERE user_id = :user_id";

        try {
            $stmt = $this->conn->prepare($query);

            // bind the standard fields
            $stmt->bindParam(':full_name', $data['full_name'], PDO::PARAM_STR);
            $stmt->bindParam(':bio', $data['bio'], PDO::PARAM_STR);

            // bind the profile picture only if it was included
            if (!empty($data['profile_picture'])) {
                $stmt->bindParam(':profile_picture', $data['profile_picture'], PDO::PARAM_STR);
            }

            // bind the user id to target the right row
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    // let a user change their password after confirming the old one
    // returns an array with success and a message
    public function changePassword($user_id, $old_password, $new_password) {
        // look up the current password hash for this user
        $query = "SELECT password_hash FROM " . $this->table . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // stop if the user id does not exist
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // verify the old password matches what is stored
        if (!password_verify($old_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        // the new password must be at least 8 characters
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters'];
        }

        // hash the new password before saving it
        $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

        // build the query to overwrite the old hash
        $updateQuery = "UPDATE " . $this->table . "
                        SET password_hash = :password_hash
                        WHERE user_id = :user_id";

        try {
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':password_hash', $new_password_hash, PDO::PARAM_STR);
            $updateStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

            // return success if the row was updated
            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to change password'];
        }

        // fall through if something unexpected happened
        return ['success' => false, 'message' => 'Failed to change password'];
    }

    // set the account status to either active or inactive
    // returns true on success or false if the status value is not valid
    public function updateStatus($user_id, $status) {
        // only allow these two status values
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

    // change a users role to viewer creator or admin
    // returns true on success or false if the role is not valid
    public function changeRole($user_id, $role) {
        // reject any role that is not in the allowed list
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


    // pull together stats for one user like how many tutorials they made and how many comments they left
    // returns an array of counts
    public function getUserStats($user_id) {
        // each subquery needs a unique parameter name because pdo does not allow reusing named placeholders
        $query = "SELECT
                    (SELECT COUNT(*) FROM dbProj_tutorials WHERE instructor_id = :uid1) as total_tutorials,
                    (SELECT COUNT(*) FROM dbProj_comments WHERE user_id = :uid2) as total_comments,
                    (SELECT COUNT(*) FROM dbProj_ratings WHERE user_id = :uid3) as total_ratings,
                    (SELECT COUNT(*) FROM dbProj_user_activity WHERE user_id = :uid4 AND activity_type = 'complete') as completed_tutorials
                  FROM " . $this->table . "
                  WHERE user_id = :uid5";

        try {
            $stmt = $this->conn->prepare($query);
            // bind the user id five times with five unique placeholder names
            $stmt->bindValue(':uid1', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid2', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid3', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid4', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':uid5', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            // return the stat row or zeroes if nothing came back
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: [
                'total_tutorials' => 0,
                'total_comments' => 0,
                'total_ratings' => 0,
                'completed_tutorials' => 0
            ];
        } catch (PDOException $e) {
            // return zeroes so the caller always gets a usable array
            return [
                'total_tutorials' => 0,
                'total_comments' => 0,
                'total_ratings' => 0,
                'completed_tutorials' => 0
            ];
        }
    }

    // permanently remove a user record from the database
    // returns true if deleted or false if something went wrong
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

// end of user class
?>
