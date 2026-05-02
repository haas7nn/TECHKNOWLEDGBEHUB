<?php
// this class tracks how many login attempts an identifier has made
// it uses temp files instead of sessions so clearing cookies cannot bypass it

class RateLimiter {
    // how many attempts are allowed before the account gets locked out
    private int    $max_attempts;
    // how long in seconds the lockout lasts which is 15 minutes
    private int    $lockout_time = 900;
    // the folder where the attempt tracking files are stored
    private string $storage_dir;

    // set up the storage folder when the class is created
    public function __construct() {
        // read max_attempts from config constant so it stays consistent with max_login_attempts
        $this->max_attempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        // store files in the system temp directory so they survive session clears
        $this->storage_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'techknow_rl' . DIRECTORY_SEPARATOR;
        // create the folder if it does not already exist
        if (!is_dir($this->storage_dir)) {
            mkdir($this->storage_dir, 0700, true);
        }
    }

    // work out what the file path should be for a given identifier
    // uses a hash of the identifier so the filename is safe and fixed length
    private function filePath(string $identifier): string {
        return $this->storage_dir . md5($identifier) . '.json';
    }

    // read the attempt data for an identifier from its file
    // returns default zeroes if the file does not exist or the data is unreadable
    private function load(string $identifier): array {
        $path = $this->filePath($identifier);
        // if no file exists yet this identifier has had zero attempts
        if (!file_exists($path)) {
            return ['attempts' => 0, 'last_attempt' => 0];
        }
        $data = json_decode(file_get_contents($path), true);
        // fall back to zeroes if the file content is not valid json
        return is_array($data) ? $data : ['attempts' => 0, 'last_attempt' => 0];
    }

    // write the attempt data for an identifier back to its file
    // uses lock_ex so two requests do not corrupt the file at the same time
    private function save(string $identifier, array $data): void {
        file_put_contents($this->filePath($identifier), json_encode($data), LOCK_EX);
    }

    // check whether this identifier is currently blocked from making more attempts
    // also auto resets the record if the lockout window has already expired
    public function isRateLimited(string $identifier): bool {
        $data = $this->load($identifier);
        // if the lockout window has passed since the last attempt reset the counter
        if ($data['last_attempt'] > 0 && (time() - $data['last_attempt']) > $this->lockout_time) {
            $this->reset($identifier);
            return false;
        }
        // return true if they have hit or exceeded the attempt limit
        return $data['attempts'] >= $this->max_attempts;
    }

    // add one to the attempt count for this identifier and save the timestamp
    // also resets the counter if enough time has passed since the last attempt
    public function recordAttempt(string $identifier): void {
        $data = $this->load($identifier);
        // if the lockout window has expired start counting fresh from zero
        if ((time() - $data['last_attempt']) > $this->lockout_time) {
            $data['attempts'] = 0;
        }
        // increment the attempt count and record when it happened
        $data['attempts']++;
        $data['last_attempt'] = time();
        $this->save($identifier, $data);
    }

    // clear the attempt record for this identifier so they can try again immediately
    public function reset(string $identifier): void {
        $path = $this->filePath($identifier);
        // only try to delete the file if it actually exists
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // return how many more attempts this identifier can make before being locked out
    // the minimum is zero so we never return a negative number
    public function getRemainingAttempts(string $identifier): int {
        $data = $this->load($identifier);
        return max(0, $this->max_attempts - $data['attempts']);
    }

    // return how many seconds are left on the current lockout
    // returns zero if there is no active lockout
    public function getLockoutTimeRemaining(string $identifier): int {
        $data = $this->load($identifier);
        // if there has never been an attempt there is nothing to count down
        if ($data['last_attempt'] === 0) return 0;
        // calculate how many seconds remain before the lockout expires
        return max(0, $this->lockout_time - (time() - $data['last_attempt']));
    }
}
?>
