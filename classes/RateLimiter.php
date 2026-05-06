<?php
/**
 * Rate Limiter Class
 * Prevents brute force attacks
 * Hasan Fardan - 202301686
 */

class RateLimiter {
    private $max_attempts = 10;
    private $lockout_time = 900; // 15 minutes
    
    /**
     * Check if IP is rate limited
     * @param string $identifier (email or IP)
     * @return bool
     */
    public function isRateLimited($identifier) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return false;
        }
        
        $data = $_SESSION[$key];
        
        // Check if lockout period has expired
        if (time() - $data['last_attempt'] > $this->lockout_time) {
            unset($_SESSION[$key]);
            return false;
        }
        
        return $data['attempts'] >= $this->max_attempts;
    }
    
    /**
     * Record failed attempt
     * @param string $identifier
     * @return void
     */
    public function recordAttempt($identifier) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'last_attempt' => time()];
        }
        
        $_SESSION[$key]['attempts']++;
        $_SESSION[$key]['last_attempt'] = time();
    }
    
    /**
     * Reset attempts on successful login
     * @param string $identifier
     * @return void
     */
    public function reset($identifier) {
        $key = 'rate_limit_' . md5($identifier);
        unset($_SESSION[$key]);
    }
    
    /**
     * Get remaining attempts
     * @param string $identifier
     * @return int
     */
    public function getRemainingAttempts($identifier) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return $this->max_attempts;
        }
        
        return max(0, $this->max_attempts - $_SESSION[$key]['attempts']);
    }
    
    /**
     * Get lockout time remaining
     * @param string $identifier
     * @return int seconds
     */
    public function getLockoutTimeRemaining($identifier) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $elapsed = time() - $_SESSION[$key]['last_attempt'];
        return max(0, $this->lockout_time - $elapsed);
    }
}
?>