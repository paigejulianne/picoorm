<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM query operations
 */
class QueryTest extends TestCase
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

    /**
     * Seed test data for query tests
     */
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
    // getAllObjects Tests
    // =========================================================================

    /**
     * Test getAllObjects returns all records when no filters
     */
    public function testGetAllObjectsReturnsAllRecords(): void
    {
        $results = TestUsers::getAllObjects('id', [], 'AND', true);

        $this->assertCount(5, $results);
    }

    /**
     * Test getAllObjects with single filter
     */
    public function testGetAllObjectsWithSingleFilter(): void
    {
        $results = TestUsers::getAllObjects('id', [
            ['role', null, '=', 'admin']
        ], 'AND', true);

        $this->assertCount(2, $results);

        foreach ($results as $user) {
            $this->assertEquals('admin', $user->role);
        }
    }

    /**
     * Test getAllObjects with multiple filters using AND
     */
    public function testGetAllObjectsWithMultipleFiltersAnd(): void
    {
        $results = TestUsers::getAllObjects('id', [
            ['role', null, '=', 'user'],
            ['is_active', null, '=', 1]
        ], 'AND', true);

        $this->assertCount(1, $results);
        $user = reset($results);
        $this->assertEquals('bob', $user->username);
    }

    /**
     * Test getAllObjects with multiple filters using OR
     */
    public function testGetAllObjectsWithMultipleFiltersOr(): void
    {
        $results = TestUsers::getAllObjects('id', [
            ['role', null, '=', 'admin'],
            ['role', null, '=', 'guest']
        ], 'OR', true);

        $this->assertCount(3, $results);
    }

    /**
     * Test getAllObjects returns single object when one result and forceArray=false
     */
    public function testGetAllObjectsReturnsSingleObject(): void
    {
        $result = TestUsers::getAllObjects('id', [
            ['username', null, '=', 'alice']
        ], 'AND', false);

        $this->assertInstanceOf(TestUsers::class, $result);
        $this->assertEquals('alice', $result->username);
    }

    /**
     * Test getAllObjects returns array when one result and forceArray=true
     */
    public function testGetAllObjectsForceArrayWithSingleResult(): void
    {
        $result = TestUsers::getAllObjects('id', [
            ['username', null, '=', 'alice']
        ], 'AND', true);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test getAllObjects returns empty array when no matches
     */
    public function testGetAllObjectsReturnsEmptyArrayWhenNoMatches(): void
    {
        $result = TestUsers::getAllObjects('id', [
            ['username', null, '=', 'nonexistent']
        ]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getAllObjects with LIKE operator
     */
    public function testGetAllObjectsWithLikeOperator(): void
    {
        $results = TestUsers::getAllObjects('id', [
            ['email', null, 'LIKE', '%@example.com']
        ], 'AND', true);

        $this->assertCount(5, $results);
    }

    /**
     * Test getAllObjects with comparison operators
     */
    public function testGetAllObjectsWithComparisonOperators(): void
    {
        // Get users with id > 2
        $results = TestUsers::getAllObjects('id', [
            ['id', null, '>', 2]
        ], 'AND', true);

        $this->assertCount(3, $results);
    }

    /**
     * Test getAllObjects with not equals operator
     */
    public function testGetAllObjectsWithNotEquals(): void
    {
        $results = TestUsers::getAllObjects('id', [
            ['role', null, '!=', 'admin']
        ], 'AND', true);

        $this->assertCount(3, $results);
    }

    /**
     * Test getAllObjects with custom ID column
     */
    public function testGetAllObjectsWithCustomIdColumn(): void
    {
        $results = TestUsers::getAllObjects('username', [
            ['is_active', null, '=', 1]
        ], 'AND', true);

        $this->assertCount(4, $results);
        $this->assertArrayHasKey('alice', $results);
        $this->assertArrayHasKey('bob', $results);
    }

    /**
     * Test getAllObjects result array is keyed by ID
     */
    public function testGetAllObjectsResultsKeyedById(): void
    {
        $results = TestUsers::getAllObjects('id', [], 'AND', true);

        foreach ($results as $key => $user) {
            $this->assertEquals($key, $user->getId());
        }
    }

    // =========================================================================
    // Custom Query Tests
    // =========================================================================

    /**
     * Test _fetch returns single record
     */
    public function testFetchReturnsSingleRecord(): void
    {
        $result = TestUsers::_fetch(
            'SELECT * FROM _DB_ WHERE username = ?',
            ['alice']
        );

        $this->assertIsArray($result);
        $this->assertEquals('alice', $result['username']);
        $this->assertEquals('alice@example.com', $result['email']);
    }

    /**
     * Test _fetch returns empty array when no match
     */
    public function testFetchReturnsEmptyArrayWhenNoMatch(): void
    {
        $result = TestUsers::_fetch(
            'SELECT * FROM _DB_ WHERE username = ?',
            ['nonexistent']
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test _fetchAll returns all matching records
     */
    public function testFetchAllReturnsAllMatches(): void
    {
        $results = TestUsers::_fetchAll(
            'SELECT * FROM _DB_ WHERE role = ? ORDER BY username',
            ['admin']
        );

        $this->assertCount(2, $results);
        $this->assertEquals('alice', $results[0]['username']);
        $this->assertEquals('diana', $results[1]['username']);
    }

    /**
     * Test _fetchAll returns empty array when no matches
     */
    public function testFetchAllReturnsEmptyArrayWhenNoMatches(): void
    {
        $results = TestUsers::_fetchAll(
            'SELECT * FROM _DB_ WHERE role = ?',
            ['superadmin']
        );

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * Test _doQuery executes UPDATE
     */
    public function testDoQueryExecutesUpdate(): void
    {
        TestUsers::_doQuery(
            'UPDATE _DB_ SET role = ? WHERE username = ?',
            ['superadmin', 'alice']
        );

        $result = TestUsers::_fetch(
            'SELECT * FROM _DB_ WHERE username = ?',
            ['alice']
        );

        $this->assertEquals('superadmin', $result['role']);
    }

    /**
     * Test _doQuery executes DELETE
     */
    public function testDoQueryExecutesDelete(): void
    {
        $countBefore = count(TestUsers::getAllObjects('id', [], 'AND', true));

        TestUsers::_doQuery(
            'DELETE FROM _DB_ WHERE username = ?',
            ['charlie']
        );

        $countAfter = count(TestUsers::getAllObjects('id', [], 'AND', true));

        $this->assertEquals($countBefore - 1, $countAfter);
    }

    /**
     * Test _doQuery executes INSERT
     */
    public function testDoQueryExecutesInsert(): void
    {
        TestUsers::_doQuery(
            'INSERT INTO _DB_ (username, email, role, is_active) VALUES (?, ?, ?, ?)',
            ['frank', 'frank@example.com', 'user', 1]
        );

        $result = TestUsers::_fetch(
            'SELECT * FROM _DB_ WHERE username = ?',
            ['frank']
        );

        $this->assertEquals('frank', $result['username']);
    }

    /**
     * Test _DB_ placeholder is replaced with table name
     */
    public function testDbPlaceholderReplacement(): void
    {
        // Using lowercase _db_ should also work
        $result = TestUsers::_fetch(
            'SELECT * FROM _db_ WHERE username = ?',
            ['alice']
        );

        $this->assertEquals('alice', $result['username']);
    }

    /**
     * Test query with multiple parameters
     */
    public function testQueryWithMultipleParameters(): void
    {
        $results = TestUsers::_fetchAll(
            'SELECT * FROM _DB_ WHERE role = ? AND is_active = ? ORDER BY username',
            ['admin', 1]
        );

        $this->assertCount(2, $results);
    }

    /**
     * Test query with ORDER BY
     */
    public function testQueryWithOrderBy(): void
    {
        $results = TestUsers::_fetchAll(
            'SELECT * FROM _DB_ ORDER BY username DESC',
            []
        );

        $this->assertEquals('eve', $results[0]['username']);
    }

    /**
     * Test query with LIMIT
     */
    public function testQueryWithLimit(): void
    {
        $results = TestUsers::_fetchAll(
            'SELECT * FROM _DB_ ORDER BY id LIMIT 3',
            []
        );

        $this->assertCount(3, $results);
    }

    /**
     * Test query with COUNT
     */
    public function testQueryWithCount(): void
    {
        $result = TestUsers::_fetch(
            'SELECT COUNT(*) as count FROM _DB_ WHERE is_active = ?',
            [1]
        );

        $this->assertEquals(4, $result['count']);
    }

    // =========================================================================
    // Complex Query Tests
    // =========================================================================

    /**
     * Test complex filter combinations
     */
    public function testComplexFilterCombinations(): void
    {
        // Active users who are either admin or have email containing 'example'
        $admins = TestUsers::getAllObjects('id', [
            ['role', null, '=', 'admin'],
            ['is_active', null, '=', 1]
        ], 'AND', true);

        $this->assertCount(2, $admins);

        // Users who are NOT active
        $inactive = TestUsers::getAllObjects('id', [
            ['is_active', null, '!=', 1]
        ], 'AND', true);

        $this->assertCount(1, $inactive);
    }

    /**
     * Test querying with null filter value
     */
    public function testFilterWithNullValue(): void
    {
        // First, create a user with null email
        $user = new TestUsers();
        $user->username = 'nullemail';
        $user->email = null;
        $user->role = 'user';
        $user->is_active = 1;
        $user->save();

        // Query for null email using IS NULL
        $result = TestUsers::_fetch(
            'SELECT * FROM _DB_ WHERE email IS NULL',
            []
        );

        $this->assertEquals('nullemail', $result['username']);
    }

    /**
     * Test that results are proper model objects
     */
    public function testResultsAreModelObjects(): void
    {
        $results = TestUsers::getAllObjects('id', [], 'AND', true);

        foreach ($results as $user) {
            $this->assertInstanceOf(TestUsers::class, $user);
            $this->assertIsString($user->username);
            $this->assertNotEquals('-1', $user->getId());
        }
    }

    /**
     * Test that result objects are fully loaded
     */
    public function testResultObjectsAreFullyLoaded(): void
    {
        $results = TestUsers::getAllObjects('id', [
            ['username', null, '=', 'alice']
        ]);

        // When single result, it's an object
        $user = $results;

        $this->assertEquals('alice', $user->username);
        $this->assertEquals('alice@example.com', $user->email);
        $this->assertEquals('admin', $user->role);
        $this->assertEquals(1, $user->is_active);
    }
}
