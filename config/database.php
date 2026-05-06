<?php
/**
 * Database Connection Class
 * handles getting into mysql using pdo and shows errors if it fails
 * Hasan Fardan - 202301686
 * 
 * @package TechKnowledge Hub
 * @author Hasan Fardan
 * @version 1.0
 */

class Database {
    // database credentials
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
    
    /**
     * starts the connection to the database
     * @return PDO|null the pdo connection or null if something goes wrong
     */
    public function connect() {
        $this->conn = null;
        
        try {
            // setting up the dsn string
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            
            // pdo settings to make it secure and handle errors properly
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            );
            
            // create the actual pdo connection
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            return $this->conn;
            
        } catch(PDOException $e) {
            // show the error page if we cant connect
            $this->displayConnectionError($e);
            return null;
        }
    }
    
    /**
     * shows a nice error page with tips on how to fix the connection
     * @param PDOException $e the exception we caught
     */
    private function displayConnectionError($e) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Database Connection Error</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                .error-box { 
                    background: #fff; 
                    border-left: 4px solid #e74c3c; 
                    padding: 20px; 
                    border-radius: 5px; 
                    max-width: 800px; 
                    margin: 50px auto;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h2 { color: #e74c3c; margin-top: 0; }
                .error-message { 
                    background: #ffe6e6; 
                    padding: 15px; 
                    border-radius: 3px; 
                    margin: 15px 0;
                    font-family: monospace;
                }
                ul { line-height: 1.8; }
                .check-item { color: #555; }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h2> Database Connection Failed!</h2>
                <div class="error-message">' . htmlspecialchars($e->getMessage()) . '</div>
                
                <h3>🔍 Troubleshooting Steps:</h3>
                <ul>
                    <li class="check-item">✓ Is MySQL/XAMPP/WAMP running?</li>
                    <li class="check-item">✓ Is the database name correct? (Should be: <strong>techknowledge_hub</strong>)</li>
                    <li class="check-item">✓ Are username/password correct? (Default: root / blank)</li>
                    <li class="check-item">✓ Did you import the SQL file?</li>
                    <li class="check-item">✓ Check config/database.php credentials</li>
                </ul>
                
                <h3>📝 Current Settings:</h3>
                <ul>
                    <li>Host: <strong>' . $this->host . '</strong></li>
                    <li>Database: <strong>' . $this->db_name . '</strong></li>
                    <li>Username: <strong>' . $this->username . '</strong></li>
                </ul>
            </div>
        </body>
        </html>';
        die();
    }
    
    /**
     * quick way to see if the connection is working
     * @return boolean true if it works
     */
    public function testConnection() {
        if ($this->connect()) {
            return true;
        }
        return false;
    }
}

// this part runs if you open the file directly to test it
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Database Connection Test</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .success-box { 
                background: #d4edda; 
                border-left: 4px solid #28a745; 
                padding: 20px; 
                border-radius: 5px; 
                max-width: 600px; 
                margin: 50px auto;
            }
            h2 { color: #28a745; margin-top: 0; }
        </style>
    </head>
    <body>
        <div class="success-box">
            <h2> Database Connection Test !!</h2>';
    
    $database = new Database();
    if ($database->connect()) {
        echo '<p><strong>Status:</strong> Connection Successful!</p>';
        echo '<p>Database is ready to use, NOW You can now build your application</p>';
    }
    
    echo '</div></body></html>';
}
?>