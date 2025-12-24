<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM input validation and SQL injection prevention
 */
class ValidationTest extends TestCase
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
    // Valid Identifier Tests
    // =========================================================================

    /**
     * Test valid column names are accepted
     * @dataProvider validIdentifierProvider
     */
    public function testValidIdentifiersAreAccepted(string $identifier): void
    {
        // If no exception is thrown, the test passes
        $this->assertTrue(TestModel::exists(1, $identifier) || true);
    }

    /**
     * Provides valid SQL identifiers
     */
    public static function validIdentifierProvider(): array
    {
        return [
            'simple lowercase' => ['id'],
            'simple uppercase' => ['ID'],
            'mixed case' => ['userId'],
            'with underscore' => ['user_id'],
            'starting with underscore' => ['_private'],
            'with numbers' => ['field123'],
            'underscore and numbers' => ['field_123_value'],
            'all uppercase' => ['STATUS'],
            'single letter' => ['x'],
            'single underscore start' => ['_'],
        ];
    }

    // =========================================================================
    // Invalid Identifier Tests
    // =========================================================================

    /**
     * Test invalid column names throw exception
     * @dataProvider invalidIdentifierProvider
     */
    public function testInvalidIdentifiersThrowException(string $identifier): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid');

        TestModel::exists(1, $identifier);
    }

    /**
     * Provides invalid SQL identifiers
     */
    public static function invalidIdentifierProvider(): array
    {
        return [
            'with hyphen' => ['user-id'],
            'with space' => ['user id'],
            'with dot' => ['user.id'],
            'sql injection attempt' => ['id; DROP TABLE users;--'],
            'with quotes' => ["id'"],
            'with double quotes' => ['id"'],
            'with backticks' => ['id`'],
            'starting with number' => ['123field'],
            'with special chars' => ['field@name'],
            'with parentheses' => ['field()'],
            'with equals' => ['field=1'],
            'empty string' => [''],
            'with asterisk' => ['*'],
            'with semicolon' => ['id;'],
        ];
    }

    /**
     * Test SQL injection via column name in constructor
     */
    public function testSqlInjectionViaColumnNameInConstructor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TestModel(1, "id' OR '1'='1");
    }

    /**
     * Test SQL injection via column name in exists()
     */
    public function testSqlInjectionViaColumnNameInExists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestModel::exists(1, "id; DROP TABLE test_table;--");
    }

    // =========================================================================
    // Operator Validation Tests
    // =========================================================================

    /**
     * Test valid operators are accepted
     * @dataProvider validOperatorProvider
     */
    public function testValidOperatorsAreAccepted(string $operator): void
    {
        // Create a record to query
        $model = new TestModel();
        $model->name = 'Test';
        $model->status = 'active';
        $model->save();

        // This should not throw - operator should be accepted
        $result = TestModel::getAllObjects('id', [
            ['status', null, $operator, 'active']
        ]);

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    /**
     * Provides valid SQL operators
     */
    public static function validOperatorProvider(): array
    {
        return [
            'equals' => ['='],
            'not equals' => ['!='],
            'not equals alt' => ['<>'],
            'less than' => ['<'],
            'greater than' => ['>'],
            'less or equal' => ['<='],
            'greater or equal' => ['>='],
            'like lowercase' => ['like'],
            'like uppercase' => ['LIKE'],
            'like mixed' => ['Like'],
            'not like' => ['NOT LIKE'],
            'in' => ['IN'],
            'not in' => ['NOT IN'],
            'is' => ['IS'],
            'is not' => ['IS NOT'],
        ];
    }

    /**
     * Test invalid operators throw exception
     * @dataProvider invalidOperatorProvider
     */
    public function testInvalidOperatorsThrowException(string $operator): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SQL operator');

        TestModel::getAllObjects('id', [
            ['status', null, $operator, 'active']
        ]);
    }

    /**
     * Provides invalid SQL operators
     */
    public static function invalidOperatorProvider(): array
    {
        return [
            'arbitrary text' => ['EQUALS'],
            'sql injection' => ['= 1; DROP TABLE users;--'],
            'or injection' => ['= 1 OR 1=1'],
            'union injection' => ['UNION SELECT'],
            'semicolon' => [';'],
            'comment' => ['--'],
            'between' => ['BETWEEN'],  // Not in whitelist
            'regexp' => ['REGEXP'],    // Not in whitelist
            'empty' => [''],
        ];
    }

    // =========================================================================
    // Filter Glue Validation Tests
    // =========================================================================

    /**
     * Test valid filter glue values
     */
    public function testValidFilterGlueAnd(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->status = 'active';
        $model->save();

        $result = TestModel::getAllObjects('id', [
            ['status', null, '=', 'active']
        ], 'AND');

        $this->assertNotEmpty($result);
    }

    /**
     * Test valid filter glue OR
     */
    public function testValidFilterGlueOr(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->status = 'active';
        $model->save();

        $result = TestModel::getAllObjects('id', [
            ['status', null, '=', 'active'],
            ['status', null, '=', 'inactive']
        ], 'OR');

        $this->assertNotEmpty($result);
    }

    /**
     * Test case insensitivity of filter glue
     */
    public function testFilterGlueCaseInsensitive(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->save();

        // These should all work
        $result1 = TestModel::getAllObjects('id', [], 'and');
        $result2 = TestModel::getAllObjects('id', [], 'AND');
        $result3 = TestModel::getAllObjects('id', [], 'And');

        $this->assertTrue(true); // Test passes if no exception
    }

    /**
     * Test invalid filter glue throws exception
     */
    public function testInvalidFilterGlueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid filter glue");

        TestModel::getAllObjects('id', [], 'XOR');
    }

    /**
     * Test SQL injection via filter glue
     */
    public function testSqlInjectionViaFilterGlue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestModel::getAllObjects('id', [
            ['status', null, '=', 'active']
        ], "AND 1=1; DROP TABLE users;--");
    }

    // =========================================================================
    // Property Name Validation Tests
    // =========================================================================

    /**
     * Test that property names are validated on save
     */
    public function testPropertyNameValidationOnSave(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->save();
        $id = $model->getId();

        // Setting a property with invalid name should throw on save
        $loaded = new TestModel($id);

        // Use reflection to bypass __set validation
        $reflection = new ReflectionClass($loaded);
        $propProperty = $reflection->getProperty('properties');
        $propProperty->setAccessible(true);
        $props = $propProperty->getValue($loaded);
        $props['invalid-column'] = 'test';
        $propProperty->setValue($loaded, $props);

        $taintedProperty = $reflection->getProperty('_taintedItems');
        $taintedProperty->setAccessible(true);
        $taintedProperty->setValue($loaded, ['invalid-column' => 'invalid-column']);

        $this->expectException(\InvalidArgumentException::class);
        $loaded->save();
    }

    // =========================================================================
    // Values Are Properly Escaped Tests
    // =========================================================================

    /**
     * Test that values with SQL special characters are safely stored
     */
    public function testValuesWithSqlCharactersAreSafe(): void
    {
        $dangerousValue = "Robert'); DROP TABLE test_table;--";

        $model = new TestModel();
        $model->name = $dangerousValue;
        $model->save();
        $id = $model->getId();

        // Verify the value was stored correctly (not executed as SQL)
        $loaded = new TestModel($id);
        $this->assertEquals($dangerousValue, $loaded->name);

        // Verify the table still exists
        $this->assertTrue(TestModel::exists($id));
    }

    /**
     * Test values with quotes are safely handled
     */
    public function testValuesWithQuotesAreSafe(): void
    {
        $model = new TestModel();
        $model->name = "O'Brien";
        $model->email = 'test"quote@example.com';
        $model->save();

        $loaded = new TestModel($model->getId());
        $this->assertEquals("O'Brien", $loaded->name);
        $this->assertEquals('test"quote@example.com', $loaded->email);
    }

    /**
     * Test values with backslashes are safely handled
     */
    public function testValuesWithBackslashesAreSafe(): void
    {
        $model = new TestModel();
        $model->name = 'Path\\To\\File';
        $model->save();

        $loaded = new TestModel($model->getId());
        $this->assertEquals('Path\\To\\File', $loaded->name);
    }

    /**
     * Test values with null bytes are handled
     */
    public function testValuesWithNullBytesAreHandled(): void
    {
        $model = new TestModel();
        $model->name = "Test\x00Value";
        $model->save();

        // SQLite may handle null bytes differently, but no exception should be thrown
        $this->assertNotEquals('-1', $model->getId());
    }

    // =========================================================================
    // Filter Value Injection Tests
    // =========================================================================

    /**
     * Test SQL injection via filter value is prevented
     */
    public function testSqlInjectionViaFilterValueIsPrevented(): void
    {
        $model = new TestModel();
        $model->name = 'Safe';
        $model->status = 'active';
        $model->save();

        // This malicious value should be treated as a literal string
        $result = TestModel::getAllObjects('id', [
            ['status', null, '=', "active' OR '1'='1"]
        ]);

        // Should find nothing (the injection attempt is treated as literal)
        $this->assertEmpty($result);
    }
}
