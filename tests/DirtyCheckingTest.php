<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM dirty checking methods (isDirty, isClean, getDirty, getOriginal, fresh, toArray)
 */
class DirtyCheckingTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabaseHelper::setupFileDatabase();
    }

    protected function tearDown(): void
    {
        TestDatabaseHelper::cleanup();
    }

    private function createTestUser(): TestUsers
    {
        $user = new TestUsers();
        $user->setMulti([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role' => 'user',
            'is_active' => 1
        ]);
        $user->save();

        // Return a fresh load to reset dirty state
        return new TestUsers($user->getId());
    }

    // =========================================================================
    // isDirty() and isClean() Tests
    // =========================================================================

    public function testNewRecordIsNotDirty(): void
    {
        $user = new TestUsers();

        $this->assertFalse($user->isDirty());
        $this->assertTrue($user->isClean());
    }

    public function testLoadedRecordIsClean(): void
    {
        $user = $this->createTestUser();

        $this->assertFalse($user->isDirty());
        $this->assertTrue($user->isClean());
    }

    public function testModifiedRecordIsDirty(): void
    {
        $user = $this->createTestUser();
        $user->username = 'modified';

        $this->assertTrue($user->isDirty());
        $this->assertFalse($user->isClean());
    }

    public function testIsDirtyWithSpecificColumn(): void
    {
        $user = $this->createTestUser();
        $user->username = 'modified';

        $this->assertTrue($user->isDirty('username'));
        $this->assertFalse($user->isDirty('email'));
        $this->assertFalse($user->isDirty('role'));
    }

    public function testRecordIsCleanAfterSave(): void
    {
        $user = $this->createTestUser();
        $user->username = 'modified';

        $this->assertTrue($user->isDirty());

        $user->save();

        $this->assertFalse($user->isDirty());
        $this->assertTrue($user->isClean());
    }

    public function testRecordIsCleanAfterRefresh(): void
    {
        $user = $this->createTestUser();
        $user->username = 'modified_but_not_saved';

        $this->assertTrue($user->isDirty());

        $user->refreshProperties();

        $this->assertFalse($user->isDirty());
        $this->assertTrue($user->isClean());
        $this->assertEquals('testuser', $user->username);
    }

    // =========================================================================
    // getDirty() Tests
    // =========================================================================

    public function testGetDirtyReturnsChangedProperties(): void
    {
        $user = $this->createTestUser();
        $user->username = 'newname';
        $user->email = 'newemail@example.com';

        $dirty = $user->getDirty();

        $this->assertIsArray($dirty);
        $this->assertCount(2, $dirty);
        $this->assertArrayHasKey('username', $dirty);
        $this->assertArrayHasKey('email', $dirty);
        $this->assertEquals('newname', $dirty['username']);
        $this->assertEquals('newemail@example.com', $dirty['email']);
    }

    public function testGetDirtyReturnsEmptyArrayWhenClean(): void
    {
        $user = $this->createTestUser();

        $dirty = $user->getDirty();

        $this->assertIsArray($dirty);
        $this->assertEmpty($dirty);
    }

    // =========================================================================
    // getOriginal() Tests
    // =========================================================================

    public function testGetOriginalReturnsOriginalValue(): void
    {
        $user = $this->createTestUser();
        $originalName = $user->username;

        $user->username = 'modified';

        $this->assertEquals($originalName, $user->getOriginal('username'));
        $this->assertEquals('modified', $user->username);
    }

    public function testGetOriginalReturnsAllOriginalValues(): void
    {
        $user = $this->createTestUser();
        $user->username = 'modified';
        $user->email = 'modified@example.com';

        $original = $user->getOriginal();

        $this->assertIsArray($original);
        $this->assertEquals('testuser', $original['username']);
        $this->assertEquals('test@example.com', $original['email']);
    }

    public function testGetOriginalReturnsNullForNonexistentColumn(): void
    {
        $user = $this->createTestUser();

        $this->assertNull($user->getOriginal('nonexistent'));
    }

    // =========================================================================
    // fresh() Tests
    // =========================================================================

    public function testFreshReturnsNewInstance(): void
    {
        $user = $this->createTestUser();
        $user->username = 'local_modification';

        $fresh = $user->fresh();

        $this->assertInstanceOf(TestUsers::class, $fresh);
        $this->assertNotSame($user, $fresh);
        $this->assertEquals($user->getId(), $fresh->getId());
    }

    public function testFreshHasLatestDatabaseValues(): void
    {
        $user = $this->createTestUser();
        $user->username = 'local_modification';

        $fresh = $user->fresh();

        // Fresh instance should have database value
        $this->assertEquals('testuser', $fresh->username);

        // Original instance should still have local modification
        $this->assertEquals('local_modification', $user->username);
    }

    public function testFreshReturnsNullForUnsavedRecord(): void
    {
        $user = new TestUsers();
        $user->username = 'unsaved';

        $fresh = $user->fresh();

        $this->assertNull($fresh);
    }

    public function testFreshReturnsNullForDeletedRecord(): void
    {
        $user = $this->createTestUser();
        $id = $user->getId();

        $user->delete();

        // Create a new instance with the old ID (simulating stale reference)
        $stale = new TestUsers();
        // Manually set ID to simulate a stale object
        $stale->_id = $id;

        // This should return null since record doesn't exist
        $user2 = new TestUsers($id);
        $fresh = $user2->fresh();

        $this->assertNull($fresh);
    }

    // =========================================================================
    // toArray() Tests
    // =========================================================================

    public function testToArrayReturnsAllProperties(): void
    {
        $user = $this->createTestUser();

        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('role', $array);
        $this->assertEquals('testuser', $array['username']);
        $this->assertEquals('test@example.com', $array['email']);
    }

    public function testToArrayWithSpecificColumns(): void
    {
        $user = $this->createTestUser();

        $array = $user->toArray(['username', 'email']);

        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayNotHasKey('role', $array);
        $this->assertArrayNotHasKey('is_active', $array);
    }

    public function testToArrayIgnoresNonexistentColumns(): void
    {
        $user = $this->createTestUser();

        $array = $user->toArray(['username', 'nonexistent_column']);

        $this->assertCount(1, $array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayNotHasKey('nonexistent_column', $array);
    }

    public function testToArrayReturnsEmptyForNewRecord(): void
    {
        $user = new TestUsers();

        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertEmpty($array);
    }

    public function testToArrayIncludesModifiedValues(): void
    {
        $user = $this->createTestUser();
        $user->username = 'modified';

        $array = $user->toArray();

        $this->assertEquals('modified', $array['username']);
    }
}
