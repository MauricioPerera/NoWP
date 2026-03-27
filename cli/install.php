<?php
/**
 * NoWP Installation Script
 * Runs migrations and creates admin user.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Load autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load .env
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

echo "NoWP Installer\n";
echo "==============\n\n";

// Connect to database
$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$db = env('DB_DATABASE', 'nowp_test');
$user = env('DB_USERNAME', 'root');
$pass = env('DB_PASSWORD', '');

echo "Connecting to MySQL ($host:$port)...\n";

try {
    // First create database if not exists
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$db' ready.\n";

    // Reconnect to the database
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Create migrations table
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Get already-ran migrations
$ran = $pdo->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

// Run migrations
$migrationPath = BASE_PATH . '/migrations';
$files = glob($migrationPath . '/*.php');
sort($files);

$config = [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int) $port,
            'database' => $db,
            'username' => $user,
            'password' => $pass,
            'charset' => 'utf8mb4',
        ],
    ],
    'retry' => ['attempts' => 3, 'delay' => 100],
];

$connection = new \ChimeraNoWP\Database\Connection($config);

foreach ($files as $file) {
    $name = basename($file, '.php');
    if (in_array($name, $ran)) {
        echo "  SKIP: $name (already ran)\n";
        continue;
    }

    echo "  RUN:  $name ... ";

    try {
        $fileContent = file_get_contents($file);
        $isAnonymous = str_contains($fileContent, 'return new class');

        if ($isAnonymous) {
            // Anonymous class migration - extract and run SQL directly
            preg_match_all('/->execute\s*\(\s*"(.*?)"\s*\)/s', $fileContent, $sqls);
            foreach ($sqls[1] as $sql) {
                $pdo->exec(trim($sql));
            }
        } else {
            // Named class migration
            require_once $file;
            $className = null;
            if (preg_match('/class\s+(\w+)\s+extends/', $fileContent, $m)) {
                $className = $m[1];
            }
            if (!$className || !class_exists($className)) {
                echo "SKIP (no class found)\n";
                continue;
            }
            $migration = new $className($connection);
            $migration->up();
        }
        $pdo->prepare("INSERT INTO migrations (migration) VALUES (?)")->execute([$name]);
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

// Create A2D tables
echo "\nCreating A2D system tables...\n";
$a2dTables = [
    "CREATE TABLE IF NOT EXISTS a2d_entities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        label VARCHAR(255) NOT NULL,
        fields JSON NOT NULL,
        search_enabled TINYINT(1) DEFAULT 0,
        api_enabled TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS a2i_services (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        label VARCHAR(255) NOT NULL,
        base_url VARCHAR(500) NOT NULL,
        auth_type VARCHAR(50) DEFAULT 'bearer',
        credentials JSON,
        endpoints JSON NOT NULL,
        test_endpoint VARCHAR(255),
        test_status VARCHAR(20) DEFAULT 'untested',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS a2e_workflows (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        steps JSON NOT NULL,
        schedule VARCHAR(100),
        is_active TINYINT(1) DEFAULT 1,
        last_run_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS a2e_execution_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        workflow_id INT,
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        steps_completed INT DEFAULT 0,
        steps_total INT DEFAULT 0,
        result JSON,
        error TEXT,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_at TIMESTAMP NULL,
        duration_ms INT,
        INDEX idx_workflow (workflow_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS a2p_pages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        slug VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        template VARCHAR(50) DEFAULT 'default',
        layout VARCHAR(50) DEFAULT 'fullwidth',
        sections JSON NOT NULL,
        auth_required TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($a2dTables as $sql) {
    try {
        $pdo->exec($sql);
        preg_match('/TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)/i', $sql, $m);
        echo "  OK: {$m[1]}\n";
    } catch (\Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}

// Create admin user
echo "\nCreating admin user...\n";
$exists = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@nowp.local'")->fetchColumn();
if ($exists == 0) {
    $hash = password_hash('admin', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)")
        ->execute(['admin@nowp.local', $hash, 'Admin', 'admin']);
    echo "  Created: admin@nowp.local / admin\n";
} else {
    echo "  Already exists.\n";
}

echo "\nInstallation complete!\n";
echo "Start server: php -S localhost:8888 -t public\n";
