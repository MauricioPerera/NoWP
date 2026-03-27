<?php

/**
 * Install Controller
 * 
 * Handles framework installation process.
 * 
 * Requirements: 10.1, 10.2, 10.3
 */

declare(strict_types=1);

namespace ChimeraNoWP\Install;

use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Database\Connection;
use ChimeraNoWP\Database\MigrationRunner;
use ChimeraNoWP\Auth\PasswordHasher;
use PDO;

class InstallController
{
    private SystemRequirements $requirements;
    
    public function __construct()
    {
        $this->requirements = new SystemRequirements();
    }
    
    /**
     * Show installation form
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        // Check if already installed
        if ($this->isInstalled()) {
            return new Response(json_encode([
                'error' => 'Framework is already installed'
            ]), 400, ['Content-Type' => 'application/json']);
        }
        
        // Check system requirements
        $requirements = $this->requirements->checkAll();
        $allMet = $this->requirements->allRequirementsMet();
        
        return new Response(json_encode([
            'requirements' => $requirements,
            'all_met' => $allMet,
            'missing' => $this->requirements->getMissingRequirements(),
        ]), 200, ['Content-Type' => 'application/json']);
    }
    
    /**
     * Process installation
     *
     * @param Request $request
     * @return Response
     */
    public function install(Request $request): Response
    {
        // Check if already installed
        if ($this->isInstalled()) {
            return new Response(json_encode([
                'error' => 'Framework is already installed'
            ]), 400, ['Content-Type' => 'application/json']);
        }
        
        // Check system requirements
        if (!$this->requirements->allRequirementsMet()) {
            return new Response(json_encode([
                'error' => 'System requirements not met',
                'missing' => $this->requirements->getMissingRequirements(),
            ]), 400, ['Content-Type' => 'application/json']);
        }
        
        // Get installation data
        $data = $request->all();
        
        // Validate required fields
        $errors = $this->validateInstallData($data);
        if (!empty($errors)) {
            return new Response(json_encode([
                'error' => 'Validation failed',
                'errors' => $errors,
            ]), 400, ['Content-Type' => 'application/json']);
        }
        
        try {
            // Test database connection
            $connection = $this->createDatabaseConnection($data);
            
            // Run migrations
            $this->runMigrations($connection);
            
            // Create admin user
            $this->createAdminUser($connection, $data);
            
            // Create configuration file
            $this->createConfigFile($data);
            
            // Mark as installed
            $this->markAsInstalled();
            
            return new Response(json_encode([
                'success' => true,
                'message' => 'Installation completed successfully',
            ]), 200, ['Content-Type' => 'application/json']);
            
        } catch (\Exception $e) {
            return new Response(json_encode([
                'error' => 'Installation failed: ' . $e->getMessage(),
            ]), 500, ['Content-Type' => 'application/json']);
        }
    }
    
    /**
     * Validate installation data
     *
     * @param array $data
     * @return array Validation errors
     */
    private function validateInstallData(array $data): array
    {
        $errors = [];
        
        // Database configuration
        if (empty($data['db_host'])) {
            $errors['db_host'] = 'Database host is required';
        }
        
        if (empty($data['db_name'])) {
            $errors['db_name'] = 'Database name is required';
        }
        
        if (empty($data['db_user'])) {
            $errors['db_user'] = 'Database user is required';
        }
        
        // Admin user
        if (empty($data['admin_email'])) {
            $errors['admin_email'] = 'Admin email is required';
        } elseif (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Admin email must be valid';
        }
        
        if (empty($data['admin_password'])) {
            $errors['admin_password'] = 'Admin password is required';
        } elseif (strlen($data['admin_password']) < 8) {
            $errors['admin_password'] = 'Admin password must be at least 8 characters';
        }
        
        if (empty($data['admin_name'])) {
            $errors['admin_name'] = 'Admin name is required';
        }
        
        // Site configuration
        if (empty($data['site_url'])) {
            $errors['site_url'] = 'Site URL is required';
        }
        
        return $errors;
    }
    
    /**
     * Create database connection
     *
     * @param array $data
     * @return Connection
     */
    private function createDatabaseConnection(array $data): Connection
    {
        $config = [
            'driver' => 'mysql',
            'host' => $data['db_host'],
            'port' => $data['db_port'] ?? 3306,
            'database' => $data['db_name'],
            'username' => $data['db_user'],
            'password' => $data['db_password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
        
        return new Connection($config);
    }
    
    /**
     * Run database migrations
     *
     * @param Connection $connection
     * @return void
     */
    private function runMigrations(Connection $connection): void
    {
        $migrationsPath = BASE_PATH . '/migrations';
        $runner = new MigrationRunner($connection, $migrationsPath);
        $runner->run();
    }
    
    /**
     * Create admin user
     *
     * @param Connection $connection
     * @param array $data
     * @return void
     */
    private function createAdminUser(Connection $connection, array $data): void
    {
        $hasher = new PasswordHasher();
        $hashedPassword = $hasher->hash($data['admin_password']);
        
        $connection->execute(
            "INSERT INTO users (name, email, password, role, created_at, updated_at) 
             VALUES (?, ?, ?, 'admin', NOW(), NOW())",
            [
                $data['admin_name'],
                $data['admin_email'],
                $hashedPassword,
            ]
        );
    }
    
    /**
     * Create configuration file
     *
     * @param array $data
     * @return void
     */
    private function createConfigFile(array $data): void
    {
        // Sanitize all user-supplied values to prevent .env injection
        $sanitize = fn(string $value): string => str_replace(["\n", "\r", "\0"], '', $value);

        $dbHost = $sanitize($data['db_host']);
        $dbPort = $sanitize((string)($data['db_port'] ?? 3306));
        $dbName = $sanitize($data['db_name']);
        $dbUser = $sanitize($data['db_user']);
        $dbPassword = $sanitize($data['db_password'] ?? '');
        $siteUrl = $sanitize($data['site_url']);

        $envContent = <<<ENV
# Database Configuration
DB_DRIVER=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPassword}

# Application Configuration
APP_NAME=Framework
APP_ENV=production
APP_DEBUG=false
APP_URL={$siteUrl}

# JWT Configuration
JWT_SECRET={$this->generateSecret()}
JWT_EXPIRATION=3600

# Cache Configuration
CACHE_DRIVER=auto
CACHE_PREFIX=framework_

ENV;
        
        file_put_contents(BASE_PATH . '/.env', $envContent);
    }
    
    /**
     * Generate random secret
     *
     * @return string
     */
    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Mark framework as installed
     *
     * @return void
     */
    private function markAsInstalled(): void
    {
        file_put_contents(BASE_PATH . '/.installed', date('Y-m-d H:i:s'));
    }
    
    /**
     * Check if framework is already installed
     *
     * @return bool
     */
    private function isInstalled(): bool
    {
        return file_exists(BASE_PATH . '/.installed');
    }
}
