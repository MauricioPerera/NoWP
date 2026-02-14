<?php

/**
 * Migration System Tests
 * 
 * Tests for the Migration base class and MigrationRunner.
 * Validates migration execution, rollback, and tracking.
 * 
 * Requirements: 5.3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Database/Migration.php';
require_once __DIR__ . '/../../../src/Database/MigrationRunner.php';

use Framework\Database\Connection;
use Framework\Database\Migration;
use Framework\Database\MigrationRunner;

beforeEach(function () {
    // Use in-memory SQLite for testing
    $config = [
        'default' => 'testing',
        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            ],
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 100,
        ],
    ];
    
    $this->connection = new Connection($config);
    $this->migrationsPath = __DIR__ . '/../../fixtures/migrations';
    
    // Create migrations directory if it doesn't exist
    if (!is_dir($this->migrationsPath)) {
        mkdir($this->migrationsPath, 0777, true);
    }
    
    $this->runner = new \Framework\Database\MigrationRunner($this->connection, $this->migrationsPath);
});

afterEach(function () {
    // Clean up test migration files
    if (is_dir($this->migrationsPath)) {
        $files = glob($this->migrationsPath . '/*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }
});

it('creates migrations tracking table', function () {
    $this->runner->createMigrationsTable();
    
    // Verify table exists by querying it
    $result = $this->connection->fetchAll('SELECT * FROM migrations');
    expect($result)->toBeArray();
});

it('runs pending migrations', function () {
    // Create a test migration
    $migrationContent = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE
            )
        ");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE users");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreateUsersTable.php', $migrationContent);
    
    // Run migrations
    $executed = $this->runner->run();
    
    expect($executed)->toContain('CreateUsersTable');
    
    // Verify table was created
    $result = $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    expect($result)->toHaveCount(1);
});

it('tracks executed migrations', function () {
    // Create a test migration
    $migrationContent = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL
            )
        ");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE posts");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreatePostsTable.php', $migrationContent);
    
    // Run migrations
    $this->runner->run();
    
    // Verify migration was recorded
    $result = $this->connection->fetchAll("SELECT migration, batch FROM migrations WHERE migration = 'CreatePostsTable'");
    expect($result)->toHaveCount(1)
        ->and($result[0]['migration'])->toBe('CreatePostsTable')
        ->and($result[0]['batch'])->toBe(1);
});

it('does not run already executed migrations', function () {
    // Create a test migration
    $migrationContent = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreateCommentsTable extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content TEXT NOT NULL
            )
        ");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE comments");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreateCommentsTable.php', $migrationContent);
    
    // Run migrations twice
    $firstRun = $this->runner->run();
    $secondRun = $this->runner->run();
    
    expect($firstRun)->toContain('CreateCommentsTable')
        ->and($secondRun)->toBeEmpty();
});

it('rolls back last batch of migrations', function () {
    // Create a test migration
    $migrationContent = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreateCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )
        ");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE categories");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreateCategoriesTable.php', $migrationContent);
    
    // Run migrations
    $this->runner->run();
    
    // Verify table exists
    $result = $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'");
    expect($result)->toHaveCount(1);
    
    // Rollback
    $rolledBack = $this->runner->rollback();
    
    expect($rolledBack)->toContain('CreateCategoriesTable');
    
    // Verify table was dropped
    $result = $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='categories'");
    expect($result)->toBeEmpty();
});

it('removes migration record after rollback', function () {
    // Create a test migration
    $migrationContent = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreateTagsTable extends Migration
{
    public function up(): void
    {
        $this->connection->execute("
            CREATE TABLE tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL
            )
        ");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE tags");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreateTagsTable.php', $migrationContent);
    
    // Run and rollback
    $this->runner->run();
    $this->runner->rollback();
    
    // Verify migration record was removed
    $result = $this->connection->fetchAll("SELECT migration FROM migrations WHERE migration = 'CreateTagsTable'");
    expect($result)->toBeEmpty();
});

it('runs migrations in batches', function () {
    // Create first migration
    $migration1 = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreateTable1 extends Migration
{
    public function up(): void
    {
        $this->connection->execute("CREATE TABLE table1 (id INTEGER PRIMARY KEY)");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE table1");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreateTable1.php', $migration1);
    
    // Run first batch
    $this->runner->run();
    
    // Create second migration
    $migration2 = <<<'PHP'
<?php

use Framework\Database\Migration;

class CreateTable2 extends Migration
{
    public function up(): void
    {
        $this->connection->execute("CREATE TABLE table2 (id INTEGER PRIMARY KEY)");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE table2");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/CreateTable2.php', $migration2);
    
    // Run second batch
    $this->runner->run();
    
    // Verify batches
    $batch1 = $this->connection->fetchAll("SELECT migration FROM migrations WHERE batch = 1");
    $batch2 = $this->connection->fetchAll("SELECT migration FROM migrations WHERE batch = 2");
    
    expect($batch1)->toHaveCount(1)
        ->and($batch2)->toHaveCount(1)
        ->and($batch1[0]['migration'])->toBe('CreateTable1')
        ->and($batch2[0]['migration'])->toBe('CreateTable2');
});

it('provides migration status', function () {
    // Create two migrations
    $migration1 = <<<'PHP'
<?php

use Framework\Database\Migration;

class Migration1 extends Migration
{
    public function up(): void
    {
        $this->connection->execute("CREATE TABLE test1 (id INTEGER PRIMARY KEY)");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE test1");
    }
}
PHP;
    
    $migration2 = <<<'PHP'
<?php

use Framework\Database\Migration;

class Migration2 extends Migration
{
    public function up(): void
    {
        $this->connection->execute("CREATE TABLE test2 (id INTEGER PRIMARY KEY)");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE test2");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/Migration1.php', $migration1);
    file_put_contents($this->migrationsPath . '/Migration2.php', $migration2);
    
    // Run only first migration
    $this->runner->run();
    
    // Get status
    $status = $this->runner->status();
    
    expect($status)->toHaveCount(2)
        ->and($status[0]['migration'])->toBe('Migration1')
        ->and($status[0]['executed'])->toBeTrue()
        ->and($status[1]['migration'])->toBe('Migration2')
        ->and($status[1]['executed'])->toBeFalse();
});

it('uses transactions for migration execution', function () {
    // Create a migration that will fail
    $migrationContent = <<<'PHP'
<?php

use Framework\Database\Migration;

class FailingMigration extends Migration
{
    public function up(): void
    {
        $this->connection->execute("CREATE TABLE test_table (id INTEGER PRIMARY KEY)");
        throw new Exception("Intentional failure");
    }
    
    public function down(): void
    {
        $this->connection->execute("DROP TABLE test_table");
    }
}
PHP;
    
    file_put_contents($this->migrationsPath . '/FailingMigration.php', $migrationContent);
    
    // Try to run migration (should fail)
    try {
        $this->runner->run();
        $this->fail('Expected RuntimeException was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('Migration failed');
    }
    
    // Verify table was not created (transaction rolled back)
    $result = $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='test_table'");
    expect($result)->toBeEmpty();
    
    // Verify migration was not recorded
    $result = $this->connection->fetchAll("SELECT migration FROM migrations WHERE migration = 'FailingMigration'");
    expect($result)->toBeEmpty();
});

it('gets migration name from class', function () {
    $migration = new class($this->connection) extends Migration {
        public function up(): void {}
        public function down(): void {}
    };
    
    $name = $migration->getName();
    expect($name)->toContain('class@anonymous');
});
