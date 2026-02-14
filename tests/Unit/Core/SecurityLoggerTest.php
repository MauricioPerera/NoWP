<?php

use Framework\Core\SecurityLogger;

beforeEach(function () {
    $this->logFile = __DIR__ . '/../../fixtures/security-test.log';
    $this->logger = new SecurityLogger($this->logFile);
});

afterEach(function () {
    if (file_exists($this->logFile)) {
        unlink($this->logFile);
    }
});

it('logs login attempts', function () {
    $this->logger->logLoginAttempt('user@example.com', true, '192.168.1.1');
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('LOGIN_ATTEMPT');
    expect($entries[0])->toContain('SUCCESS');
    expect($entries[0])->toContain('user@example.com');
    expect($entries[0])->toContain('192.168.1.1');
});

it('logs failed login attempts', function () {
    $this->logger->logLoginAttempt('user@example.com', false, '192.168.1.1');
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('FAILED');
});

it('logs permission changes', function () {
    $this->logger->logPermissionChange(1, 'subscriber', 'editor', 2);
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('PERMISSION_CHANGE');
    expect($entries[0])->toContain('subscriber');
    expect($entries[0])->toContain('editor');
});

it('logs plugin activations', function () {
    $this->logger->logPluginAction('example-plugin', true, 1);
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('PLUGIN_ACTION');
    expect($entries[0])->toContain('example-plugin');
    expect($entries[0])->toContain('activated');
});

it('logs plugin deactivations', function () {
    $this->logger->logPluginAction('example-plugin', false, 1);
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('deactivated');
});

it('logs authorization failures', function () {
    $this->logger->logAuthorizationFailure(1, 'content', 'delete');
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('AUTHORIZATION_FAILURE');
    expect($entries[0])->toContain('content');
    expect($entries[0])->toContain('delete');
});

it('logs rate limit violations', function () {
    $this->logger->logRateLimitViolation('192.168.1.1', '/api/login');
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(1);
    expect($entries[0])->toContain('RATE_LIMIT_VIOLATION');
    expect($entries[0])->toContain('192.168.1.1');
    expect($entries[0])->toContain('/api/login');
});

it('returns recent entries in reverse order', function () {
    $this->logger->logLoginAttempt('user1@example.com', true);
    $this->logger->logLoginAttempt('user2@example.com', true);
    $this->logger->logLoginAttempt('user3@example.com', true);
    
    $entries = $this->logger->getRecentEntries();
    expect($entries)->toHaveCount(3);
    expect($entries[0])->toContain('user3@example.com');
    expect($entries[2])->toContain('user1@example.com');
});

it('limits number of returned entries', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->logger->logLoginAttempt("user{$i}@example.com", true);
    }
    
    $entries = $this->logger->getRecentEntries(5);
    expect($entries)->toHaveCount(5);
});

it('clears log file', function () {
    $this->logger->logLoginAttempt('user@example.com', true);
    expect($this->logger->getRecentEntries())->toHaveCount(1);
    
    $this->logger->clear();
    expect($this->logger->getRecentEntries())->toHaveCount(0);
});

it('handles non-existent log file', function () {
    $logger = new SecurityLogger(__DIR__ . '/../../fixtures/non-existent.log');
    expect($logger->getRecentEntries())->toHaveCount(0);
});

it('includes context data in JSON format', function () {
    $this->logger->logLoginAttempt('user@example.com', true, '192.168.1.1');
    
    $entries = $this->logger->getRecentEntries();
    expect($entries[0])->toContain('"email":"user@example.com"');
    expect($entries[0])->toContain('"success":true');
    expect($entries[0])->toContain('"ip":"192.168.1.1"');
});
