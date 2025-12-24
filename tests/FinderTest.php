<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM finder methods (findBy, findOneBy, firstOrCreate, updateOrCreate)
 */
class FinderTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabaseHelper::setupFileDatabase();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        TestDatabaseHelper::cleanup();
    }

    private function seedTestData(): void
    {
        $users = [
            ['username' => 'alice', 'email' => 'alice@example.com', 'role' => 'admin', 'is_active' => 1],
            ['username' => 'bob', 'email' => 'bob@example.com', 'role' => 'user', 'is_active' => 1],
            ['username' => 'charlie', 'email' => 'charlie@example.com', 'role' => 'user', 'is_active' => 0],
        ];

        foreach ($users as $userData) {
            $user = new TestUsers();
            $user->setMulti($userData);
            $user->save();
        }
    }

    // =========================================================================
    // findBy() Tests
    // =========================================================================

    public function testFindByReturnsAllMatches(): void
    {
        $users = TestUsers::findBy('role', 'user');

        $this->assertIsArray($users);
        $this->assertCount(2, $users);

        foreach ($users as $user) {
            $this->assertEquals('user', $user->role);
        }
    }

    public function testFindByReturnsEmptyArrayWhenNoMatches(): void
    {
        $users = TestUsers::findBy('role', 'superadmin');

        $this->assertIsArray($users);
        $this->assertEmpty($users);
    }

    public function testFindByWithCustomOperator(): void
    {
        $users = TestUsers::findBy('is_active', 1, 'id', '!=');

        $this->assertCount(1, $users);
        $user = reset($users);
        $this->assertEquals('charlie', $user->username);
    }

    public function testFindByWithLikeOperator(): void
    {
        $users = TestUsers::findBy('email', '%@example.com', 'id', 'LIKE');

        $this->assertCount(3, $users);
    }

    // =========================================================================
    // findOneBy() Tests
    // =========================================================================

    public function testFindOneByReturnsModelInstance(): void
    {
        $user = TestUsers::findOneBy('email', 'alice@example.com');

        $this->assertInstanceOf(TestUsers::class, $user);
        $this->assertEquals('alice', $user->username);
        $this->assertEquals('admin', $user->role);
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $user = TestUsers::findOneBy('email', 'nonexistent@example.com');

        $this->assertNull($user);
    }

    public function testFindOneByReturnsFirstMatch(): void
    {
        // Multiple users with role 'user', should return first one found
        $user = TestUsers::findOneBy('role', 'user');

        $this->assertInstanceOf(TestUsers::class, $user);
        $this->assertEquals('user', $user->role);
    }

    public function testFindOneByThrowsOnInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUsers::findOneBy('invalid-column', 'value');
    }

    // =========================================================================
    // firstOrCreate() Tests
    // =========================================================================

    public function testFirstOrCreateReturnsExistingRecord(): void
    {
        $countBefore = TestUsers::count();

        $user = TestUsers::firstOrCreate(
            ['email' => 'alice@example.com'],
            ['username' => 'should_not_be_used']
        );

        $countAfter = TestUsers::count();

        $this->assertEquals($countBefore, $countAfter);
        $this->assertEquals('alice', $user->username);
    }

    public function testFirstOrCreateCreatesNewRecord(): void
    {
        $countBefore = TestUsers::count();

        $user = TestUsers::firstOrCreate(
            ['email' => 'newuser@example.com'],
            ['username' => 'newuser', 'role' => 'guest', 'is_active' => 1]
        );

        $countAfter = TestUsers::count();

        $this->assertEquals($countBefore + 1, $countAfter);
        $this->assertEquals('newuser', $user->username);
        $this->assertEquals('newuser@example.com', $user->email);
        $this->assertEquals('guest', $user->role);
    }

    public function testFirstOrCreateWithMultipleAttributes(): void
    {
        $user = TestUsers::firstOrCreate(
            ['role' => 'admin', 'is_active' => 1],
            ['username' => 'should_not_create']
        );

        // Should find alice (first admin)
        $this->assertEquals('alice', $user->username);
    }

    public function testFirstOrCreateSetsAllValuesOnCreate(): void
    {
        $user = TestUsers::firstOrCreate(
            ['email' => 'brand_new@example.com'],
            ['username' => 'brandnew', 'role' => 'moderator', 'is_active' => 0]
        );

        // Verify the record was saved
        $loaded = TestUsers::findOneBy('email', 'brand_new@example.com');
        $this->assertNotNull($loaded);
        $this->assertEquals('brandnew', $loaded->username);
        $this->assertEquals('moderator', $loaded->role);
        $this->assertEquals(0, $loaded->is_active);
    }

    // =========================================================================
    // updateOrCreate() Tests
    // =========================================================================

    public function testUpdateOrCreateUpdatesExistingRecord(): void
    {
        $user = TestUsers::updateOrCreate(
            ['email' => 'alice@example.com'],
            ['role' => 'superadmin']
        );

        $this->assertEquals('alice', $user->username);
        $this->assertEquals('superadmin', $user->role);

        // Verify it was actually updated in database
        $loaded = TestUsers::findOneBy('email', 'alice@example.com');
        $this->assertEquals('superadmin', $loaded->role);
    }

    public function testUpdateOrCreateCreatesNewRecord(): void
    {
        $countBefore = TestUsers::count();

        $user = TestUsers::updateOrCreate(
            ['email' => 'newbie@example.com'],
            ['username' => 'newbie', 'role' => 'user', 'is_active' => 1]
        );

        $countAfter = TestUsers::count();

        $this->assertEquals($countBefore + 1, $countAfter);
        $this->assertEquals('newbie', $user->username);
        $this->assertEquals('newbie@example.com', $user->email);
    }

    public function testUpdateOrCreateWithMultipleSearchAttributes(): void
    {
        $user = TestUsers::updateOrCreate(
            ['username' => 'bob', 'role' => 'user'],
            ['is_active' => 0]
        );

        $this->assertEquals('bob', $user->username);
        $this->assertEquals(0, $user->is_active);
    }

    public function testUpdateOrCreateDoesNotAffectOtherRecords(): void
    {
        TestUsers::updateOrCreate(
            ['email' => 'alice@example.com'],
            ['role' => 'updated']
        );

        // Verify bob wasn't affected
        $bob = TestUsers::findOneBy('email', 'bob@example.com');
        $this->assertEquals('user', $bob->role);
    }
}
