<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM pagination and ordering in getAllObjects()
 */
class PaginationTest extends TestCase
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
            ['username' => 'frank', 'email' => 'frank@example.com', 'role' => 'user', 'is_active' => 1],
            ['username' => 'grace', 'email' => 'grace@example.com', 'role' => 'user', 'is_active' => 1],
            ['username' => 'henry', 'email' => 'henry@example.com', 'role' => 'user', 'is_active' => 0],
            ['username' => 'ivy', 'email' => 'ivy@example.com', 'role' => 'moderator', 'is_active' => 1],
            ['username' => 'jack', 'email' => 'jack@example.com', 'role' => 'user', 'is_active' => 1],
        ];

        foreach ($users as $userData) {
            $user = new TestUsers();
            $user->setMulti($userData);
            $user->save();
        }
    }

    // =========================================================================
    // Limit Tests
    // =========================================================================

    public function testLimitReturnsCorrectCount(): void
    {
        $users = TestUsers::getAllObjects('id', [], 'AND', true, 3);

        $this->assertCount(3, $users);
    }

    public function testLimitWithZeroReturnsEmpty(): void
    {
        $users = TestUsers::getAllObjects('id', [], 'AND', true, 0);

        // Limit 0 should return no results
        $this->assertEmpty($users);
    }

    public function testLimitGreaterThanTotalReturnsAll(): void
    {
        $users = TestUsers::getAllObjects('id', [], 'AND', true, 100);

        $this->assertCount(10, $users);
    }

    // =========================================================================
    // Offset Tests
    // =========================================================================

    public function testOffsetSkipsRecords(): void
    {
        // Get first 3 without offset
        $firstPage = TestUsers::getAllObjects('id', [], 'AND', true, 3, 0, 'id', 'ASC');

        // Get 3 with offset of 3
        $secondPage = TestUsers::getAllObjects('id', [], 'AND', true, 3, 3, 'id', 'ASC');

        // Pages should have no overlap
        $firstIds = array_keys($firstPage);
        $secondIds = array_keys($secondPage);

        $this->assertEmpty(array_intersect($firstIds, $secondIds));
    }

    public function testOffsetBeyondTotalReturnsEmpty(): void
    {
        $users = TestUsers::getAllObjects('id', [], 'AND', true, 10, 100);

        $this->assertEmpty($users);
    }

    // =========================================================================
    // Order By Tests
    // =========================================================================

    public function testOrderByAscending(): void
    {
        $users = TestUsers::getAllObjects('id', [], 'AND', true, null, 0, 'username', 'ASC');

        $usernames = array_map(fn($u) => $u->username, $users);
        $sorted = $usernames;
        sort($sorted);

        $this->assertEquals($sorted, $usernames);
    }

    public function testOrderByDescending(): void
    {
        $users = TestUsers::getAllObjects('id', [], 'AND', true, null, 0, 'username', 'DESC');

        $usernames = array_map(fn($u) => $u->username, $users);
        $sorted = $usernames;
        rsort($sorted);

        $this->assertEquals($sorted, $usernames);
    }

    public function testOrderByWithLimit(): void
    {
        // Get first 3 users alphabetically
        $users = TestUsers::getAllObjects('id', [], 'AND', true, 3, 0, 'username', 'ASC');

        $usernames = array_map(fn($u) => $u->username, $users);

        $this->assertCount(3, $users);
        $this->assertEquals('alice', $usernames[0]);
        $this->assertEquals('bob', $usernames[1]);
        $this->assertEquals('charlie', $usernames[2]);
    }

    public function testOrderByWithLimitAndOffset(): void
    {
        // Skip first 3, get next 3 users alphabetically
        $users = TestUsers::getAllObjects('id', [], 'AND', true, 3, 3, 'username', 'ASC');

        $usernames = array_map(fn($u) => $u->username, $users);

        $this->assertCount(3, $users);
        $this->assertEquals('diana', $usernames[0]);
        $this->assertEquals('eve', $usernames[1]);
        $this->assertEquals('frank', $usernames[2]);
    }

    public function testOrderByThrowsOnInvalidColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestUsers::getAllObjects('id', [], 'AND', true, null, 0, 'invalid-column', 'ASC');
    }

    public function testOrderByThrowsOnInvalidDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestUsers::getAllObjects('id', [], 'AND', true, null, 0, 'username', 'INVALID');
    }

    // =========================================================================
    // Combined Tests
    // =========================================================================

    public function testPaginationWithFilters(): void
    {
        // Get active users, ordered by username, page 1
        $page1 = TestUsers::getAllObjects(
            'id',
            [['is_active', null, '=', 1]],
            'AND',
            true,
            3,
            0,
            'username',
            'ASC'
        );

        // Get active users, ordered by username, page 2
        $page2 = TestUsers::getAllObjects(
            'id',
            [['is_active', null, '=', 1]],
            'AND',
            true,
            3,
            3,
            'username',
            'ASC'
        );

        $this->assertCount(3, $page1);
        $this->assertCount(3, $page2);

        // All should be active
        foreach (array_merge($page1, $page2) as $user) {
            $this->assertEquals(1, $user->is_active);
        }

        // Should be in alphabetical order
        $page1Names = array_map(fn($u) => $u->username, $page1);
        $page2Names = array_map(fn($u) => $u->username, $page2);

        // Last of page1 should be before first of page2
        $this->assertLessThan($page2Names[0], end($page1Names));
    }

    public function testFullPaginationScenario(): void
    {
        $pageSize = 3;
        $allUsers = [];
        $page = 0;

        // Paginate through all records
        do {
            $users = TestUsers::getAllObjects(
                'id',
                [],
                'AND',
                true,
                $pageSize,
                $page * $pageSize,
                'id',
                'ASC'
            );

            $allUsers = array_merge($allUsers, $users);
            $page++;
        } while (count($users) === $pageSize);

        // Should have fetched all 10 users
        $this->assertCount(10, $allUsers);
    }

    // =========================================================================
    // Named Parameters Test
    // =========================================================================

    public function testNamedParameters(): void
    {
        // Using named parameters for clarity
        $users = TestUsers::getAllObjects(
            idColumn: 'id',
            filters: [['role', null, '=', 'user']],
            filterGlue: 'AND',
            forceArray: true,
            limit: 2,
            offset: 1,
            orderBy: 'username',
            orderDir: 'ASC'
        );

        $this->assertCount(2, $users);

        foreach ($users as $user) {
            $this->assertEquals('user', $user->role);
        }
    }
}
