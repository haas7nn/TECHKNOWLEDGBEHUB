<?php
// database connection class
// handles connecting to mysql using pdo and shows a helpful error page if it fails

class Database {
    // the server where mysql is running
    /** @var string */
    private $host = 'localhost';

    // the name of the database we want to use
    /** @var string */
    private $db_name = 'techknowledge_hub';

    // the mysql username
    /** @var string */
    private $username = 'root';

    // the mysql password (blank by default for local dev)
    /** @var string */
    private $password = '';

    // holds the active pdo connection once we connect
    /** @var PDO|null */
    private $conn;

    // open the connection to the database and return it
    public function connect() {
        // start with no connection
        $this->conn = null;

        try {
            // build the dsn string with the host database and charset
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';

            // set pdo options for error handling fetch mode and encoding
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            );

            // create the pdo connection with all our settings
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            return $this->conn;

        } catch(PDOException $e) {
            // something went wrong so show the error page and stop
            $this->displayConnectionError($e);
            return null;
        }
    }

    // log the error serverside only — never expose credentials or db details to the browser
    private function displayConnectionError($e) {
        error_log('Database connection failed');
        http_response_code(500);
        echo '<h1>Service Unavailable</h1><p>Please try again later.</p>';
        exit;
    }

    // quick way to check if the connection is working at all
    public function testConnection() {
        if ($this->connect()) {
            return true;
        }
        return false;
    }
}


// removed test block because it leaked server info if someone opened this file directly
?>
