<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM count() and pluck() methods
 */
class CountAndPluckTest extends TestCase
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
            ['username' => 'diana', 'email' => 'diana@example.com', 'role' => 'admin', 'is_active' => 1],
            ['username' => 'eve', 'email' => 'eve@example.com', 'role' => 'guest', 'is_active' => 1],
        ];

        foreach ($users as $userData) {
            $user = new TestUsers();
            $user->setMulti($userData);
            $user->save();
        }
    }

    // =========================================================================
    // count() Tests
    // =========================================================================

    public function testCountAllRecords(): void
    {
        $count = TestUsers::count();
        $this->assertEquals(5, $count);
    }

    public function testCountWithSingleFilter(): void
    {
        $count = TestUsers::count([
            ['role', null, '=', 'admin']
        ]);
        $this->assertEquals(2, $count);
    }

    public function testCountWithMultipleFiltersAnd(): void
    {
        $count = TestUsers::count([
            ['role', null, '=', 'user'],
            ['is_active', null, '=', 1]
        ], 'AND');
        $this->assertEquals(1, $count);
    }

    public function testCountWithMultipleFiltersOr(): void
    {
        $count = TestUsers::count([
            ['role', null, '=', 'admin'],
            ['role', null, '=', 'guest']
        ], 'OR');
        $this->assertEquals(3, $count);
    }

    public function testCountWithNoMatches(): void
    {
        $count = TestUsers::count([
            ['role', null, '=', 'superadmin']
        ]);
        $this->assertEquals(0, $count);
    }

    public function testCountWithLikeOperator(): void
    {
        $count = TestUsers::count([
            ['email', null, 'LIKE', '%@example.com']
        ]);
        $this->assertEquals(5, $count);
    }

    public function testCountThrowsOnInvalidFilterGlue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUsers::count([], 'INVALID');
    }

    // =========================================================================
    // pluck() Tests
    // =========================================================================

    public function testPluckAllValues(): void
    {
        $usernames = TestUsers::pluck('username');

        $this->assertCount(5, $usernames);
        $this->assertContains('alice', $usernames);
        $this->assertContains('bob', $usernames);
        $this->assertContains('charlie', $usernames);
        $this->assertContains('diana', $usernames);
        $this->assertContains('eve', $usernames);
    }

    public function testPluckWithFilter(): void
    {
        $usernames = TestUsers::pluck('username', [
            ['role', null, '=', 'admin']
        ]);

        $this->assertCount(2, $usernames);
        $this->assertContains('alice', $usernames);
        $this->assertContains('diana', $usernames);
    }

    public function testPluckWithMultipleFilters(): void
    {
        $emails = TestUsers::pluck('email', [
            ['role', null, '=', 'user'],
            ['is_active', null, '=', 1]
        ], 'AND');

        $this->assertCount(1, $emails);
        $this->assertContains('bob@example.com', $emails);
    }

    public function testPluckReturnsEmptyArrayWhenNoMatches(): void
    {
        $usernames = TestUsers::pluck('username', [
            ['role', null, '=', 'superadmin']
        ]);

        $this->assertIsArray($usernames);
        $this->assertEmpty($usernames);
    }

    public function testPluckThrowsOnInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUsers::pluck('invalid-column');
    }

    public function testPluckThrowsOnInvalidFilterGlue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TestUsers::pluck('username', [], 'INVALID');
    }
}
