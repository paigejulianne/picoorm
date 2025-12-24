<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM error handling
 */
class ErrorHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabaseHelper::setupFileDatabase();
    }

    protected function tearDown(): void
    {
        TestDatabaseHelper::cleanup();
    }

    // =========================================================================
    // PDO Exception Tests
    // =========================================================================

    /**
     * Test that invalid SQL throws PDOException
     */
    public function testInvalidSqlThrowsPdoException(): void
    {
        $this->expectException(\PDOException::class);

        TestModel::_doQuery('INVALID SQL SYNTAX HERE');
    }

    /**
     * Test that query on non-existent table throws PDOException
     */
    public function testQueryOnNonExistentTableThrowsException(): void
    {
        $this->expectException(\PDOException::class);

        // Create a model that maps to a non-existent table
        $model = new class extends PicoORM {
            const TABLE_OVERRIDE = 'nonexistent_table_xyz';
        };

        $model->name = 'test';
        $model->save();
    }

    /**
     * Test that constraint violation throws PDOException
     */
    public function testConstraintViolationThrowsException(): void
    {
        // Create a table with NOT NULL constraint
        $pdo = new \PDO('sqlite:/tmp/picoorm_test.db');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS strict_table (
                id INTEGER PRIMARY KEY,
                required_field TEXT NOT NULL
            )
        ');
        $pdo = null;

        PicoORM::addConnection('default', 'sqlite:/tmp/picoorm_test.db', '', '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);

        $this->expectException(\PDOException::class);

        $model = new class extends PicoORM {
            const TABLE_OVERRIDE = 'strict_table';
        };

        // Not setting required_field should cause constraint violation
        $model->save();
    }

    // =========================================================================
    // InvalidArgumentException Tests
    // =========================================================================

    /**
     * Test that invalid column name throws InvalidArgumentException
     */
    public function testInvalidColumnNameThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column name');

        new TestModel(1, 'invalid-column-name');
    }

    /**
     * Test that invalid operator throws InvalidArgumentException
     */
    public function testInvalidOperatorThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL operator');

        TestModel::getAllObjects('id', [
            ['status', null, 'INVALID', 'value']
        ]);
    }

    /**
     * Test that invalid filter glue throws InvalidArgumentException
     */
    public function testInvalidFilterGlueThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filter glue');

        TestModel::getAllObjects('id', [], 'INVALID');
    }

    // =========================================================================
    // RuntimeException Tests
    // =========================================================================

    /**
     * Test that unconfigured connection throws RuntimeException
     */
    public function testUnconfiguredConnectionThrowsRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not configured');

        // Reset connections
        $reflection = new ReflectionClass(PicoORM::class);
        $prop = $reflection->getProperty('connections');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        $loadedProp = $reflection->getProperty('connectionsLoaded');
        $loadedProp->setAccessible(true);
        $loadedProp->setValue(null, true);

        // Clear global variables
        global $PICOORM_DSN;
        $originalDsn = $PICOORM_DSN ?? null;
        $PICOORM_DSN = null;

        try {
            $model = new TestModel();
            $model->name = 'test';
            $model->save();
        } finally {
            // Restore
            $PICOORM_DSN = $originalDsn;
            TestDatabaseHelper::setupFileDatabase();
        }
    }

    // =========================================================================
    // Error Recovery Tests
    // =========================================================================

    /**
     * Test that model can be used after catching exception
     */
    public function testModelUsableAfterCatchingException(): void
    {
        // First, cause an exception
        try {
            TestModel::getAllObjects('id', [
                ['status', null, 'INVALID', 'value']
            ]);
        } catch (\InvalidArgumentException $e) {
            // Expected
        }

        // Now, normal operations should still work
        $model = new TestModel();
        $model->name = 'After Exception';
        $model->save();

        $this->assertNotEquals('-1', $model->getId());
    }

    /**
     * Test that failed save doesn't corrupt object state
     */
    public function testFailedSaveDoesNotCorruptState(): void
    {
        $model = new TestModel();
        $model->name = 'Original';
        $model->save();
        $id = $model->getId();

        // Now try to update with invalid operation (using reflection to bypass validation)
        $reflection = new ReflectionClass($model);
        $propProperty = $reflection->getProperty('properties');
        $propProperty->setAccessible(true);
        $props = $propProperty->getValue($model);
        $props['bad-column'] = 'value';
        $propProperty->setValue($model, $props);

        $taintedProperty = $reflection->getProperty('_taintedItems');
        $taintedProperty->setAccessible(true);
        $taintedProperty->setValue($model, ['bad-column' => 'bad-column']);

        try {
            $model->save();
        } catch (\InvalidArgumentException $e) {
            // Expected
        }

        // The original record should still be intact
        $loaded = new TestModel($id);
        $this->assertEquals('Original', $loaded->name);
    }

    // =========================================================================
    // Edge Case Error Tests
    // =========================================================================

    /**
     * Test handling of empty filter array
     */
    public function testEmptyFilterArrayDoesNotError(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->save();

        $results = TestModel::getAllObjects('id', []);

        $this->assertNotEmpty($results);
    }

    /**
     * Test handling of incomplete filter (missing operator)
     */
    public function testIncompleteFilterIsSkipped(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->status = 'active';
        $model->save();

        // Filter with missing operator (index 2) should be skipped
        $results = TestModel::getAllObjects('id', [
            ['status', null],  // Incomplete - no operator
        ], 'AND', true);

        // Should return results as if no filter was applied
        $this->assertNotEmpty($results);
    }

    /**
     * Test handling of filter with null value
     */
    public function testFilterWithMissingValueUsesNull(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->status = null;
        $model->save();

        // This should use null as the value
        $results = TestModel::getAllObjects('id', [
            ['status', null, 'IS', null]
        ], 'AND', true);

        // Should work without errors
        $this->assertIsArray($results);
    }

    /**
     * Test multiple simultaneous exceptions don't cause issues
     */
    public function testMultipleExceptionsHandledCleanly(): void
    {
        $exceptions = 0;

        for ($i = 0; $i < 5; $i++) {
            try {
                TestModel::getAllObjects('id', [
                    ['status', null, 'INVALID_OP', 'value']
                ]);
            } catch (\InvalidArgumentException $e) {
                $exceptions++;
            }
        }

        $this->assertEquals(5, $exceptions);

        // System should still be functional
        $model = new TestModel();
        $model->name = 'After Multiple Exceptions';
        $model->save();

        $this->assertNotEquals('-1', $model->getId());
    }

    // =========================================================================
    // Connection Error Tests
    // =========================================================================

    /**
     * Test error when connections file is unreadable
     */
    public function testUnreadableConnectionsFile(): void
    {
        // This should not throw - just fall back to no connections
        PicoORM::setConnectionsFile('/nonexistent/path/to/file');

        // Reset to force reload
        $reflection = new ReflectionClass(PicoORM::class);
        $loadedProp = $reflection->getProperty('connectionsLoaded');
        $loadedProp->setAccessible(true);
        $loadedProp->setValue(null, false);

        // Loading should not throw
        PicoORM::loadConnections();

        // But using an undefined connection should
        $this->assertFalse(PicoORM::hasConnection('fromfile'));
    }

    /**
     * Test error with malformed DSN
     */
    public function testMalformedDsnThrowsException(): void
    {
        $this->expectException(\PDOException::class);

        PicoORM::addConnection('bad', 'not_a_valid_dsn', '', '');

        $model = new class extends PicoORM {
            const CONNECTION = 'bad';
            const TABLE_OVERRIDE = 'test';
        };

        $model->name = 'test';
        $model->save();
    }
}
