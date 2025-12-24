<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM CRUD operations
 */
class CrudTest extends TestCase
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
    // CREATE Tests
    // =========================================================================

    /**
     * Test creating a new record
     */
    public function testCreateRecord(): void
    {
        $model = new TestModel();
        $model->name = 'John Doe';
        $model->email = 'john@example.com';
        $model->save();

        $this->assertNotEquals('-1', $model->getId());
        $this->assertIsNumeric($model->getId());
    }

    /**
     * Test that new records start with ID -1
     */
    public function testNewRecordHasNegativeOneId(): void
    {
        $model = new TestModel();
        $this->assertEquals('-1', $model->getId());
    }

    /**
     * Test getLastInsertId after create
     */
    public function testGetLastInsertId(): void
    {
        $model = new TestModel();
        $model->name = 'Test User';
        $model->save();

        $lastId = TestModel::getLastInsertId();
        $this->assertEquals($model->getId(), $lastId);
    }

    /**
     * Test creating record with setMulti
     */
    public function testCreateWithSetMulti(): void
    {
        $model = new TestModel();
        $model->setMulti([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'pending'
        ]);
        $model->save();

        $this->assertNotEquals('-1', $model->getId());
        $this->assertEquals('Jane Doe', $model->name);
        $this->assertEquals('jane@example.com', $model->email);
        $this->assertEquals('pending', $model->status);
    }

    /**
     * Test multiple sequential creates
     */
    public function testMultipleCreates(): void
    {
        $ids = [];

        for ($i = 1; $i <= 5; $i++) {
            $model = new TestModel();
            $model->name = "User $i";
            $model->save();
            $ids[] = $model->getId();
        }

        // All IDs should be unique
        $this->assertCount(5, array_unique($ids));

        // IDs should be sequential
        for ($i = 1; $i < count($ids); $i++) {
            $this->assertGreaterThan($ids[$i - 1], $ids[$i]);
        }
    }

    // =========================================================================
    // READ Tests
    // =========================================================================

    /**
     * Test loading an existing record
     */
    public function testLoadExistingRecord(): void
    {
        // Create a record first
        $model = new TestModel();
        $model->name = 'Load Test';
        $model->email = 'load@example.com';
        $model->save();
        $id = $model->getId();

        // Load it back
        $loaded = new TestModel($id);
        $this->assertEquals($id, $loaded->getId());
        $this->assertEquals('Load Test', $loaded->name);
        $this->assertEquals('load@example.com', $loaded->email);
    }

    /**
     * Test loading non-existent record returns -1 ID
     */
    public function testLoadNonExistentRecord(): void
    {
        $model = new TestModel(99999);
        $this->assertEquals('-1', $model->getId());
    }

    /**
     * Test loading with custom ID column
     */
    public function testLoadWithCustomIdColumn(): void
    {
        // Create a record with custom ID column
        $model = new TestCustomId();
        $model->name = 'Custom ID Test';
        $model->value = 'test value';
        $model->save();
        $id = $model->getId();

        // Load using custom ID column
        $loaded = new TestCustomId($id, 'user_id');
        $this->assertEquals($id, $loaded->getId());
        $this->assertEquals('Custom ID Test', $loaded->name);
    }

    /**
     * Test exists() method with existing record
     */
    public function testExistsWithExistingRecord(): void
    {
        $model = new TestModel();
        $model->name = 'Exists Test';
        $model->save();
        $id = $model->getId();

        $this->assertTrue(TestModel::exists($id));
    }

    /**
     * Test exists() method with non-existent record
     */
    public function testExistsWithNonExistentRecord(): void
    {
        $this->assertFalse(TestModel::exists(99999));
    }

    /**
     * Test exists() with custom column
     */
    public function testExistsWithCustomColumn(): void
    {
        $model = new TestUsers();
        $model->username = 'testuser';
        $model->email = 'test@example.com';
        $model->save();

        $this->assertTrue(TestUsers::exists('test@example.com', 'email'));
        $this->assertFalse(TestUsers::exists('nonexistent@example.com', 'email'));
    }

    /**
     * Test isset on properties
     */
    public function testIssetOnProperties(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->save();

        $loaded = new TestModel($model->getId());

        $this->assertTrue(isset($loaded->name));
        $this->assertFalse(isset($loaded->nonexistent_property));
    }

    /**
     * Test accessing null/unset property returns null
     */
    public function testAccessingUnsetPropertyReturnsNull(): void
    {
        $model = new TestModel();
        $this->assertNull($model->nonexistent_property);
    }

    // =========================================================================
    // UPDATE Tests
    // =========================================================================

    /**
     * Test updating an existing record
     */
    public function testUpdateRecord(): void
    {
        // Create
        $model = new TestModel();
        $model->name = 'Original Name';
        $model->save();
        $id = $model->getId();

        // Update
        $model->name = 'Updated Name';
        $model->save();

        // Verify
        $loaded = new TestModel($id);
        $this->assertEquals('Updated Name', $loaded->name);
    }

    /**
     * Test updating multiple fields
     */
    public function testUpdateMultipleFields(): void
    {
        $model = new TestModel();
        $model->name = 'Original';
        $model->email = 'original@example.com';
        $model->status = 'active';
        $model->save();
        $id = $model->getId();

        $model->name = 'Updated';
        $model->email = 'updated@example.com';
        $model->status = 'inactive';
        $model->save();

        $loaded = new TestModel($id);
        $this->assertEquals('Updated', $loaded->name);
        $this->assertEquals('updated@example.com', $loaded->email);
        $this->assertEquals('inactive', $loaded->status);
    }

    /**
     * Test refreshProperties reloads data
     */
    public function testRefreshProperties(): void
    {
        $model = new TestModel();
        $model->name = 'Original';
        $model->save();
        $id = $model->getId();

        // Update directly in database
        TestModel::_doQuery(
            "UPDATE test_table SET name = ? WHERE id = ?",
            ['Modified Directly', $id]
        );

        // Model still has old value
        $this->assertEquals('Original', $model->name);

        // Refresh from database
        $model->refreshProperties();

        // Now has new value
        $this->assertEquals('Modified Directly', $model->name);
    }

    /**
     * Test that only tainted (modified) properties are saved
     */
    public function testOnlyModifiedPropertiesAreSaved(): void
    {
        $model = new TestModel();
        $model->name = 'Test Name';
        $model->email = 'test@example.com';
        $model->save();
        $id = $model->getId();

        // Load and modify only one field
        $loaded = new TestModel($id);
        $loaded->email = 'new@example.com';
        $loaded->save();

        // Verify name wasn't affected
        $verify = new TestModel($id);
        $this->assertEquals('Test Name', $verify->name);
        $this->assertEquals('new@example.com', $verify->email);
    }

    // =========================================================================
    // DELETE Tests
    // =========================================================================

    /**
     * Test deleting a record
     */
    public function testDeleteRecord(): void
    {
        $model = new TestModel();
        $model->name = 'To Delete';
        $model->save();
        $id = $model->getId();

        // Verify it exists
        $this->assertTrue(TestModel::exists($id));

        // Delete it
        $model->delete();

        // Verify it's gone
        $this->assertFalse(TestModel::exists($id));
    }

    /**
     * Test that record is not accessible after delete
     */
    public function testRecordNotFoundAfterDelete(): void
    {
        $model = new TestModel();
        $model->name = 'Delete Test';
        $model->save();
        $id = $model->getId();

        $model->delete();

        $loaded = new TestModel($id);
        $this->assertEquals('-1', $loaded->getId());
    }

    // =========================================================================
    // AUTO-SAVE Tests
    // =========================================================================

    /**
     * Test that changes are auto-saved on destruct
     */
    public function testAutoSaveOnDestruct(): void
    {
        $id = null;

        // Create in a closure so the object goes out of scope
        $createRecord = function () use (&$id) {
            $model = new TestModel();
            $model->name = 'Auto Save Test';
            $model->email = 'autosave@example.com';
            $model->save();
            $id = $model->getId();

            // Modify without explicit save
            $model->name = 'Modified Name';
            // Object destructor should save on scope exit
        };

        $createRecord();

        // Force garbage collection
        gc_collect_cycles();

        // Verify the modification was saved
        $loaded = new TestModel($id);
        $this->assertEquals('Modified Name', $loaded->name);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    /**
     * Test saving record with null values
     */
    public function testSaveWithNullValues(): void
    {
        $model = new TestModel();
        $model->name = 'Null Test';
        $model->email = null;
        $model->save();

        $loaded = new TestModel($model->getId());
        $this->assertEquals('Null Test', $loaded->name);
        $this->assertNull($loaded->email);
    }

    /**
     * Test that properties starting with underscore are not saved
     */
    public function testUnderscorePropertiesNotSaved(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->_internal = 'should not save';
        $model->save();

        // The record should be created
        $this->assertNotEquals('-1', $model->getId());

        // But _internal should not cause an error or be in the database
        // (This test passes if no exception is thrown)
    }

    /**
     * Test saving empty string values
     */
    public function testSaveEmptyStringValues(): void
    {
        $model = new TestModel();
        $model->name = '';
        $model->email = '';
        $model->save();

        $loaded = new TestModel($model->getId());
        $this->assertEquals('', $loaded->name);
        $this->assertEquals('', $loaded->email);
    }

    /**
     * Test numeric string ID
     */
    public function testNumericStringId(): void
    {
        $model = new TestModel();
        $model->name = 'Numeric String Test';
        $model->save();
        $id = $model->getId();

        // Load with string ID
        $loaded = new TestModel((string)$id);
        $this->assertEquals($id, $loaded->getId());
        $this->assertEquals('Numeric String Test', $loaded->name);
    }
}
