<?php

/**
 * PHPUnit Bootstrap File for PicoORM Tests
 */

// Load the PicoORM class
require_once dirname(__DIR__) . '/PicoORM.php';

// Use SQLite in-memory database for testing
use PaigeJulianne\PicoORM;

/**
 * Base test model that uses the test database
 */
class TestModel extends PicoORM
{
    const TABLE_OVERRIDE = 'test_table';
}

/**
 * Test model for users table
 */
class TestUsers extends PicoORM
{
    const TABLE_OVERRIDE = 'users';
}

/**
 * Test model for secondary connection
 */
class TestSecondary extends PicoORM
{
    const CONNECTION = 'secondary';
    const TABLE_OVERRIDE = 'secondary_table';
}

/**
 * Test model with custom ID column
 */
class TestCustomId extends PicoORM
{
    const TABLE_OVERRIDE = 'custom_id_table';
}

/**
 * Helper class to set up test databases
 */
class TestDatabaseHelper
{
    /**
     * Set up the default test database with tables
     */
    public static function setupDefaultDatabase(): void
    {
        // Add the default connection using SQLite in-memory
        PicoORM::addConnection(
            'default',
            'sqlite::memory:',
            '',
            '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Create test tables
        self::createTables('default');
    }

    /**
     * Set up a secondary test database
     */
    public static function setupSecondaryDatabase(): void
    {
        // Add secondary connection
        PicoORM::addConnection(
            'secondary',
            'sqlite::memory:',
            '',
            '',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Create tables in secondary database
        self::createTablesSecondary();
    }

    /**
     * Create tables in the default database
     */
    private static function createTables(string $connection): void
    {
        $pdo = new \PDO('sqlite::memory:', '', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        // We need to use the connection directly, so let's create tables via _doQuery
        // But since SQLite in-memory is per-connection, we need a workaround
        // We'll use a file-based SQLite for testing
    }

    /**
     * Create tables in secondary database
     */
    private static function createTablesSecondary(): void
    {
        // Similar to above
    }

    /**
     * Get a fresh PDO connection to a SQLite file database
     */
    public static function setupFileDatabase(string $path = '/tmp/picoorm_test.db'): void
    {
        // Remove existing test database
        if (file_exists($path)) {
            unlink($path);
        }

        $dsn = 'sqlite:' . $path;

        // Create the database and tables
        $pdo = new \PDO($dsn, '', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        // Create test tables
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS test_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                email TEXT,
                status TEXT DEFAULT "active",
                created_at TEXT,
                view_count INTEGER DEFAULT 0,
                price REAL DEFAULT 0.0
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT,
                password TEXT,
                role TEXT DEFAULT "user",
                is_active INTEGER DEFAULT 1
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS custom_id_table (
                user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                value TEXT
            )
        ');

        // Add the connection to PicoORM
        PicoORM::addConnection('default', $dsn, '', '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }

    /**
     * Set up secondary file database
     */
    public static function setupSecondaryFileDatabase(string $path = '/tmp/picoorm_test_secondary.db'): void
    {
        // Remove existing test database
        if (file_exists($path)) {
            unlink($path);
        }

        $dsn = 'sqlite:' . $path;

        $pdo = new \PDO($dsn, '', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS secondary_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data TEXT,
                created_at TEXT
            )
        ');

        PicoORM::addConnection('secondary', $dsn, '', '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }

    /**
     * Clean up test databases
     */
    public static function cleanup(): void
    {
        $files = [
            '/tmp/picoorm_test.db',
            '/tmp/picoorm_test_secondary.db'
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
