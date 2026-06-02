<?php
// pdo database connection class

class Database {
    /** @var string */
    private $host = 'localhost';

    /** @var string */
    private $db_name = 'techknowledge_hub';

    /** @var string */
    private $username = 'root';

    /** @var string */
    private $password = '';

    /** @var PDO|null */
    private $conn;

    // connect and return pdo instance
    public function connect() {
        $this->conn = null;

        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';

            // throw exceptions use assoc arrays force real prepares
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            );

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            return $this->conn;

        } catch(PDOException $e) {
            $this->displayConnectionError($e);
            return null;
        }
    }

    // log error silently never expose db details
    private function displayConnectionError($e) {
        error_log('Database connection failed');
        http_response_code(500);
        echo '<h1>Service Unavailable</h1><p>Please try again later.</p>';
        exit;
    }

    // ping to verify connection works
    public function testConnection() {
        if ($this->connect()) {
            return true;
        }
        return false;
    }
}


// no test block here to avoid leaking server info
?>
