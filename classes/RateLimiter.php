<?php
// tracks login attempts using files not sessions so cookie clears cant bypass it

class RateLimiter {
    private int    $max_attempts;
    private int    $lockout_time = 900; // 15 min lockout
    private string $storage_dir;

    public function __construct() {
        // pull limit from config constant
        $this->max_attempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
        // temp dir survives session clears
        $this->storage_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'techknow_rl' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->storage_dir)) {
            mkdir($this->storage_dir, 0700, true);
        }
    }

    // md5 hash keeps filename safe and fixed length
    private function filePath(string $identifier): string {
        return $this->storage_dir . md5($identifier) . '.json';
    }

    // load attempt data from file defaulting to zeroes
    private function load(string $identifier): array {
        $path = $this->filePath($identifier);
        // no file means first attempt ever
        if (!file_exists($path)) {
            return ['attempts' => 0, 'last_attempt' => 0];
        }
        $data = json_decode(file_get_contents($path), true);
        // invalid json falls back to zero state
        return is_array($data) ? $data : ['attempts' => 0, 'last_attempt' => 0];
    }

    // write attempt data with exclusive lock to avoid race conditions
    private function save(string $identifier, array $data): void {
        file_put_contents($this->filePath($identifier), json_encode($data), LOCK_EX);
    }

    // true if identifier is still in lockout window
    public function isRateLimited(string $identifier): bool {
        $data = $this->load($identifier);
        // lockout window expired so auto reset
        if ($data['last_attempt'] > 0 && (time() - $data['last_attempt']) > $this->lockout_time) {
            $this->reset($identifier);
            return false;
        }
        return $data['attempts'] >= $this->max_attempts;
    }

    // increment attempt count and timestamp
    public function recordAttempt(string $identifier): void {
        $data = $this->load($identifier);
        // window expired so restart count
        if ((time() - $data['last_attempt']) > $this->lockout_time) {
            $data['attempts'] = 0;
        }
        $data['attempts']++;
        $data['last_attempt'] = time();
        $this->save($identifier, $data);
    }

    // delete attempt file so identifier can try again
    public function reset(string $identifier): void {
        $path = $this->filePath($identifier);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // attempts left before lockout never negative
    public function getRemainingAttempts(string $identifier): int {
        $data = $this->load($identifier);
        return max(0, $this->max_attempts - $data['attempts']);
    }

    // seconds remaining on current lockout or zero
    public function getLockoutTimeRemaining(string $identifier): int {
        $data = $this->load($identifier);
        // never attempted so no lockout
        if ($data['last_attempt'] === 0) return 0;
        return max(0, $this->lockout_time - (time() - $data['last_attempt']));
    }
}
?>
