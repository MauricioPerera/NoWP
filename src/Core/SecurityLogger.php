<?php

/**
 * Security Logger
 * 
 * Logs security-related events for auditing and monitoring.
 * 
 * Requirements: 12.5
 */

declare(strict_types=1);

namespace Framework\Core;

class SecurityLogger
{
    private string $logFile;
    
    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? BASE_PATH . '/storage/logs/security.log';
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log a login attempt
     *
     * @param string $email User email
     * @param bool $success Whether login was successful
     * @param string|null $ip IP address
     * @return void
     */
    public function logLoginAttempt(string $email, bool $success, ?string $ip = null): void
    {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $message = "Login attempt {$status} for user: {$email}";
        
        if ($ip) {
            $message .= " from IP: {$ip}";
        }
        
        $this->log('LOGIN_ATTEMPT', $message, [
            'email' => $email,
            'success' => $success,
            'ip' => $ip
        ]);
    }
    
    /**
     * Log a permission change
     *
     * @param int $userId User ID
     * @param string $oldRole Old role
     * @param string $newRole New role
     * @param int|null $changedBy User ID who made the change
     * @return void
     */
    public function logPermissionChange(
        int $userId,
        string $oldRole,
        string $newRole,
        ?int $changedBy = null
    ): void {
        $message = "Permission changed for user ID {$userId}: {$oldRole} -> {$newRole}";
        
        if ($changedBy) {
            $message .= " by user ID {$changedBy}";
        }
        
        $this->log('PERMISSION_CHANGE', $message, [
            'user_id' => $userId,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'changed_by' => $changedBy
        ]);
    }
    
    /**
     * Log a plugin activation/deactivation
     *
     * @param string $pluginName Plugin name
     * @param bool $activated Whether plugin was activated (true) or deactivated (false)
     * @param int|null $userId User ID who performed the action
     * @return void
     */
    public function logPluginAction(string $pluginName, bool $activated, ?int $userId = null): void
    {
        $action = $activated ? 'activated' : 'deactivated';
        $message = "Plugin '{$pluginName}' {$action}";
        
        if ($userId) {
            $message .= " by user ID {$userId}";
        }
        
        $this->log('PLUGIN_ACTION', $message, [
            'plugin' => $pluginName,
            'action' => $action,
            'user_id' => $userId
        ]);
    }
    
    /**
     * Log an authorization failure
     *
     * @param int|null $userId User ID
     * @param string $resource Resource being accessed
     * @param string $action Action being attempted
     * @return void
     */
    public function logAuthorizationFailure(?int $userId, string $resource, string $action): void
    {
        $message = "Authorization failed: User ID {$userId} attempted {$action} on {$resource}";
        
        $this->log('AUTHORIZATION_FAILURE', $message, [
            'user_id' => $userId,
            'resource' => $resource,
            'action' => $action
        ]);
    }
    
    /**
     * Log a rate limit violation
     *
     * @param string $key Rate limit key (e.g., IP address)
     * @param string $endpoint Endpoint being accessed
     * @return void
     */
    public function logRateLimitViolation(string $key, string $endpoint): void
    {
        $message = "Rate limit exceeded for key: {$key} on endpoint: {$endpoint}";
        
        $this->log('RATE_LIMIT_VIOLATION', $message, [
            'key' => $key,
            'endpoint' => $endpoint
        ]);
    }
    
    /**
     * Write log entry
     *
     * @param string $type Event type
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    private function log(string $type, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? ' ' . json_encode($context) : '';
        
        $logEntry = "[{$timestamp}] [{$type}] {$message}{$contextJson}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get log entries
     *
     * @param int $limit Maximum number of entries to return
     * @return array
     */
    public function getRecentEntries(int $limit = 100): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return [];
        }
        
        return array_slice(array_reverse($lines), 0, $limit);
    }
    
    /**
     * Clear log file
     *
     * @return void
     */
    public function clear(): void
    {
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }
}
