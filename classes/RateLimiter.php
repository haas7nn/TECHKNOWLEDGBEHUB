<?php
/**
 * RateLimiter — file-based so clearing cookies cannot bypass it
 * Bug 9 fix: old version stored attempts in $_SESSION which could be
 * bypassed by simply clearing cookies. This version stores data in
 * temp files keyed by a hash of email+IP.
 * Hasan Fardan - 202301686
 */
class RateLimiter {
    private int    $max_attempts = 10;
    private int    $lockout_time = 900; // 15 minutes
    private string $storage_dir;

    public function __construct() {
        // store in system temp — survives session clears
        $this->storage_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'techknow_rl' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->storage_dir)) {
            mkdir($this->storage_dir, 0700, true);
        }
    }

    private function filePath(string $identifier): string {
        return $this->storage_dir . md5($identifier) . '.json';
    }

    private function load(string $identifier): array {
        $path = $this->filePath($identifier);
        if (!file_exists($path)) {
            return ['attempts' => 0, 'last_attempt' => 0];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : ['attempts' => 0, 'last_attempt' => 0];
    }

    private function save(string $identifier, array $data): void {
        file_put_contents($this->filePath($identifier), json_encode($data), LOCK_EX);
    }

    public function isRateLimited(string $identifier): bool {
        $data = $this->load($identifier);
        // lockout expired — auto-reset
        if ($data['last_attempt'] > 0 && (time() - $data['last_attempt']) > $this->lockout_time) {
            $this->reset($identifier);
            return false;
        }
        return $data['attempts'] >= $this->max_attempts;
    }

    public function recordAttempt(string $identifier): void {
        $data = $this->load($identifier);
        // reset counter if lockout window has passed
        if ((time() - $data['last_attempt']) > $this->lockout_time) {
            $data['attempts'] = 0;
        }
        $data['attempts']++;
        $data['last_attempt'] = time();
        $this->save($identifier, $data);
    }

    public function reset(string $identifier): void {
        $path = $this->filePath($identifier);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function getRemainingAttempts(string $identifier): int {
        $data = $this->load($identifier);
        return max(0, $this->max_attempts - $data['attempts']);
    }

    public function getLockoutTimeRemaining(string $identifier): int {
        $data = $this->load($identifier);
        if ($data['last_attempt'] === 0) return 0;
        return max(0, $this->lockout_time - (time() - $data['last_attempt']));
    }
}
?>
