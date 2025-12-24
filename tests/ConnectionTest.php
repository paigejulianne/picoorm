<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM connection management
 */
class ConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset connections before each test
        $this->resetConnections();
    }

    protected function tearDown(): void
    {
        TestDatabaseHelper::cleanup();
    }

    /**
     * Reset the static connection state using reflection
     */
    private function resetConnections(): void
    {
        $reflection = new ReflectionClass(PicoORM::class);

        $connectionsProperty = $reflection->getProperty('connections');
        $connectionsProperty->setAccessible(true);
        $connectionsProperty->setValue(null, []);

        $loadedProperty = $reflection->getProperty('connectionsLoaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue(null, false);
    }

    /**
     * Test adding a connection programmatically
     */
    public function testAddConnection(): void
    {
        PicoORM::addConnection(
            'test',
            'sqlite::memory:',
            'user',
            'pass',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->assertTrue(PicoORM::hasConnection('test'));
    }

    /**
     * Test checking for non-existent connection
     */
    public function testHasConnectionReturnsFalseForMissing(): void
    {
        $this->assertFalse(PicoORM::hasConnection('nonexistent'));
    }

    /**
     * Test getting connection names
     */
    public function testGetConnectionNames(): void
    {
        PicoORM::addConnection('conn1', 'sqlite::memory:', '', '');
        PicoORM::addConnection('conn2', 'sqlite::memory:', '', '');
        PicoORM::addConnection('conn3', 'sqlite::memory:', '', '');

        $names = PicoORM::getConnectionNames();

        $this->assertContains('conn1', $names);
        $this->assertContains('conn2', $names);
        $this->assertContains('conn3', $names);
        $this->assertCount(3, $names);
    }

    /**
     * Test that connection configuration is used correctly
     */
    public function testConnectionIsUsedForQueries(): void
    {
        TestDatabaseHelper::setupFileDatabase();

        // Create a record to verify connection works
        $model = new TestModel();
        $model->name = 'Test Name';
        $model->email = 'test@example.com';
        $model->save();

        $this->assertNotEquals('-1', $model->getId());
    }

    /**
     * Test multiple connections
     */
    public function testMultipleConnections(): void
    {
        TestDatabaseHelper::setupFileDatabase();
        TestDatabaseHelper::setupSecondaryFileDatabase();

        // Create record in default database
        $model1 = new TestModel();
        $model1->name = 'Default DB Record';
        $model1->save();

        // Create record in secondary database
        $model2 = new TestSecondary();
        $model2->data = 'Secondary DB Record';
        $model2->save();

        $this->assertNotEquals('-1', $model1->getId());
        $this->assertNotEquals('-1', $model2->getId());
        $this->assertEquals('secondary', $model2->getConnection());
    }

    /**
     * Test getConnection returns correct connection name
     */
    public function testGetConnectionReturnsConnectionName(): void
    {
        TestDatabaseHelper::setupFileDatabase();

        $model = new TestModel();
        $this->assertEquals('default', $model->getConnection());
    }

    /**
     * Test that missing connection throws RuntimeException
     */
    public function testMissingConnectionThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Database connection 'nonexistent' is not configured");

        // Create a model class that uses a non-existent connection
        $model = new class extends PicoORM {
            const CONNECTION = 'nonexistent';
            const TABLE_OVERRIDE = 'test';
        };

        // This should throw when trying to query
        $model->name = 'test';
        $model->save();
    }

    /**
     * Test setting custom connections file path
     */
    public function testSetConnectionsFile(): void
    {
        // Create a temporary connections file
        $tempFile = '/tmp/test_connections';
        file_put_contents($tempFile, "[testconn]\nDSN=sqlite::memory:\nUSER=\nPASS=\n");

        PicoORM::setConnectionsFile($tempFile);
        PicoORM::loadConnections();

        $this->assertTrue(PicoORM::hasConnection('testconn'));

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test parsing connections file with comments
     */
    public function testConnectionsFileWithComments(): void
    {
        $tempFile = '/tmp/test_connections_comments';
        $content = <<<EOF
# This is a comment
; This is also a comment

[myconnection]
DSN=sqlite::memory:
USER=testuser
PASS=testpass

# Another comment
[another]
DSN=sqlite::memory:
USER=
PASS=
EOF;
        file_put_contents($tempFile, $content);

        PicoORM::setConnectionsFile($tempFile);
        PicoORM::loadConnections();

        $this->assertTrue(PicoORM::hasConnection('myconnection'));
        $this->assertTrue(PicoORM::hasConnection('another'));

        unlink($tempFile);
    }

    /**
     * Test parsing connections file with quoted values
     */
    public function testConnectionsFileWithQuotedValues(): void
    {
        $tempFile = '/tmp/test_connections_quoted';
        $content = <<<EOF
[quoted]
DSN="sqlite::memory:"
USER='testuser'
PASS="password with spaces"
EOF;
        file_put_contents($tempFile, $content);

        PicoORM::setConnectionsFile($tempFile);
        PicoORM::loadConnections();

        $this->assertTrue(PicoORM::hasConnection('quoted'));

        unlink($tempFile);
    }

    /**
     * Test that legacy global variables still work as fallback
     */
    public function testLegacyGlobalVariablesFallback(): void
    {
        global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS, $PICOORM_OPTIONS;

        $PICOORM_DSN = 'sqlite:/tmp/picoorm_legacy_test.db';
        $PICOORM_USER = '';
        $PICOORM_PASS = '';
        $PICOORM_OPTIONS = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

        // Create the database
        $pdo = new \PDO($PICOORM_DSN, '', '', $PICOORM_OPTIONS);
        $pdo->exec('CREATE TABLE IF NOT EXISTS test_table (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo = null;

        // Reset connections to force fallback
        $this->resetConnections();

        // This should use global variables
        $model = new TestModel();
        $model->name = 'Legacy Test';
        $model->save();

        $this->assertNotEquals('-1', $model->getId());

        // Clean up
        unlink('/tmp/picoorm_legacy_test.db');
    }
}
