# Testing Guide

This document describes how to run and write tests for PicoORM.

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Running Tests](#running-tests)
4. [Test Structure](#test-structure)
5. [Writing Tests](#writing-tests)
6. [Test Coverage](#test-coverage)
7. [Continuous Integration](#continuous-integration)

---

## Requirements

- PHP 8.0 or higher
- PDO extension with SQLite driver
- Composer

---

## Installation

Install development dependencies:

```bash
composer install
```

This will install PHPUnit and any other development dependencies.

---

## Running Tests

### Run All Tests

```bash
# Using Composer script
composer test

# Or directly with PHPUnit
./vendor/bin/phpunit
```

### Run Specific Test File

```bash
./vendor/bin/phpunit tests/CrudTest.php
```

### Run Specific Test Method

```bash
./vendor/bin/phpunit --filter testCreateRecord
```

### Run Tests with Verbose Output

```bash
./vendor/bin/phpunit --verbose
```

### Run Tests and Stop on First Failure

```bash
./vendor/bin/phpunit --stop-on-failure
```

### Generate Code Coverage Report

```bash
# HTML report (requires Xdebug or PCOV)
composer test-coverage

# Or directly
./vendor/bin/phpunit --coverage-html coverage
```

Then open `coverage/index.html` in your browser.

---

## Test Structure

```
tests/
├── bootstrap.php          # Test setup and helper classes
├── ConnectionTest.php     # Connection management tests
├── CrudTest.php          # Create, Read, Update, Delete tests
├── ValidationTest.php    # Input validation and security tests
├── QueryTest.php         # Query and filter tests
└── ErrorHandlingTest.php # Exception handling tests
```

### Test Files Overview

#### `bootstrap.php`

Sets up the test environment:

- Loads PicoORM
- Defines test model classes (`TestModel`, `TestUsers`, `TestSecondary`, `TestCustomId`)
- Provides `TestDatabaseHelper` for database setup/teardown

#### `ConnectionTest.php`

Tests for connection management:

| Test | Description |
|------|-------------|
| `testAddConnection` | Adding connections programmatically |
| `testHasConnectionReturnsFalseForMissing` | Checking non-existent connections |
| `testGetConnectionNames` | Listing all connections |
| `testConnectionIsUsedForQueries` | Verifying connections work |
| `testMultipleConnections` | Using multiple databases |
| `testGetConnectionReturnsConnectionName` | Getting instance connection |
| `testMissingConnectionThrowsException` | Error on unconfigured connection |
| `testSetConnectionsFile` | Custom connections file path |
| `testConnectionsFileWithComments` | Parsing comments in config |
| `testConnectionsFileWithQuotedValues` | Parsing quoted values |
| `testLegacyGlobalVariablesFallback` | Backward compatibility |

#### `CrudTest.php`

Tests for CRUD operations:

| Test | Description |
|------|-------------|
| `testCreateRecord` | Creating new records |
| `testNewRecordHasNegativeOneId` | New records start with ID -1 |
| `testGetLastInsertId` | Getting last auto-increment ID |
| `testCreateWithSetMulti` | Batch property setting |
| `testMultipleCreates` | Sequential record creation |
| `testLoadExistingRecord` | Loading records by ID |
| `testLoadNonExistentRecord` | Handling missing records |
| `testLoadWithCustomIdColumn` | Custom primary key columns |
| `testExistsWithExistingRecord` | Checking record existence |
| `testExistsWithNonExistentRecord` | Checking missing records |
| `testExistsWithCustomColumn` | Existence check with custom column |
| `testIssetOnProperties` | Property existence checks |
| `testAccessingUnsetPropertyReturnsNull` | Null for missing properties |
| `testUpdateRecord` | Updating existing records |
| `testUpdateMultipleFields` | Updating multiple fields |
| `testRefreshProperties` | Reloading from database |
| `testOnlyModifiedPropertiesAreSaved` | Dirty tracking |
| `testDeleteRecord` | Deleting records |
| `testRecordNotFoundAfterDelete` | Verification after delete |
| `testAutoSaveOnDestruct` | Automatic saving on destruct |
| `testSaveWithNullValues` | Handling NULL values |
| `testUnderscorePropertiesNotSaved` | Internal properties excluded |
| `testSaveEmptyStringValues` | Empty string handling |
| `testNumericStringId` | String ID handling |

#### `ValidationTest.php`

Tests for security and validation:

| Test | Description |
|------|-------------|
| `testValidIdentifiersAreAccepted` | Valid column names pass |
| `testInvalidIdentifiersThrowException` | Invalid names rejected |
| `testSqlInjectionViaColumnNameInConstructor` | Injection prevention |
| `testSqlInjectionViaColumnNameInExists` | Injection prevention |
| `testValidOperatorsAreAccepted` | Valid operators pass |
| `testInvalidOperatorsThrowException` | Invalid operators rejected |
| `testValidFilterGlueAnd` | AND filter glue works |
| `testValidFilterGlueOr` | OR filter glue works |
| `testFilterGlueCaseInsensitive` | Case insensitivity |
| `testInvalidFilterGlueThrowsException` | Invalid glue rejected |
| `testSqlInjectionViaFilterGlue` | Injection prevention |
| `testPropertyNameValidationOnSave` | Property names validated |
| `testValuesWithSqlCharactersAreSafe` | SQL in values is safe |
| `testValuesWithQuotesAreSafe` | Quotes in values handled |
| `testValuesWithBackslashesAreSafe` | Backslashes handled |
| `testValuesWithNullBytesAreHandled` | Null bytes handled |
| `testSqlInjectionViaFilterValueIsPrevented` | Value injection prevented |

#### `QueryTest.php`

Tests for querying:

| Test | Description |
|------|-------------|
| `testGetAllObjectsReturnsAllRecords` | Fetching all records |
| `testGetAllObjectsWithSingleFilter` | Single filter condition |
| `testGetAllObjectsWithMultipleFiltersAnd` | Multiple AND filters |
| `testGetAllObjectsWithMultipleFiltersOr` | Multiple OR filters |
| `testGetAllObjectsReturnsSingleObject` | Single result behavior |
| `testGetAllObjectsForceArrayWithSingleResult` | Force array return |
| `testGetAllObjectsReturnsEmptyArrayWhenNoMatches` | Empty results |
| `testGetAllObjectsWithLikeOperator` | LIKE operator |
| `testGetAllObjectsWithComparisonOperators` | Comparison operators |
| `testGetAllObjectsWithNotEquals` | Not equals operator |
| `testGetAllObjectsWithCustomIdColumn` | Custom ID columns |
| `testGetAllObjectsResultsKeyedById` | Result array keys |
| `testFetchReturnsSingleRecord` | Custom _fetch query |
| `testFetchReturnsEmptyArrayWhenNoMatch` | _fetch no match |
| `testFetchAllReturnsAllMatches` | Custom _fetchAll query |
| `testFetchAllReturnsEmptyArrayWhenNoMatches` | _fetchAll no match |
| `testDoQueryExecutesUpdate` | UPDATE queries |
| `testDoQueryExecutesDelete` | DELETE queries |
| `testDoQueryExecutesInsert` | INSERT queries |
| `testDbPlaceholderReplacement` | _DB_ placeholder |
| `testQueryWithMultipleParameters` | Multiple parameters |
| `testQueryWithOrderBy` | ORDER BY clause |
| `testQueryWithLimit` | LIMIT clause |
| `testQueryWithCount` | COUNT queries |
| `testComplexFilterCombinations` | Complex filters |
| `testFilterWithNullValue` | NULL in filters |
| `testResultsAreModelObjects` | Result types |
| `testResultObjectsAreFullyLoaded` | Full object loading |

#### `ErrorHandlingTest.php`

Tests for error handling:

| Test | Description |
|------|-------------|
| `testInvalidSqlThrowsPdoException` | Invalid SQL errors |
| `testQueryOnNonExistentTableThrowsException` | Missing table errors |
| `testConstraintViolationThrowsException` | Constraint violations |
| `testInvalidColumnNameThrowsInvalidArgumentException` | Column validation |
| `testInvalidOperatorThrowsInvalidArgumentException` | Operator validation |
| `testInvalidFilterGlueThrowsInvalidArgumentException` | Glue validation |
| `testUnconfiguredConnectionThrowsRuntimeException` | Missing connection |
| `testModelUsableAfterCatchingException` | Recovery after error |
| `testFailedSaveDoesNotCorruptState` | State preservation |
| `testEmptyFilterArrayDoesNotError` | Empty filters |
| `testIncompleteFilterIsSkipped` | Incomplete filters |
| `testFilterWithMissingValueUsesNull` | Missing values |
| `testMultipleExceptionsHandledCleanly` | Multiple errors |
| `testUnreadableConnectionsFile` | Missing config file |
| `testMalformedDsnThrowsException` | Bad DSN |

---

## Writing Tests

### Test Model Classes

The bootstrap file provides several test model classes:

```php
// Basic test model -> test_table
class TestModel extends PicoORM {
    const TABLE_OVERRIDE = 'test_table';
}

// User model -> users table
class TestUsers extends PicoORM {
    const TABLE_OVERRIDE = 'users';
}

// Model using secondary connection
class TestSecondary extends PicoORM {
    const CONNECTION = 'secondary';
    const TABLE_OVERRIDE = 'secondary_table';
}

// Model with custom ID column
class TestCustomId extends PicoORM {
    const TABLE_OVERRIDE = 'custom_id_table';
}
```

### Database Setup

Use `TestDatabaseHelper` to set up test databases:

```php
protected function setUp(): void
{
    // Set up SQLite test database with tables
    TestDatabaseHelper::setupFileDatabase();
}

protected function tearDown(): void
{
    // Clean up test database files
    TestDatabaseHelper::cleanup();
}
```

### Writing a New Test

```php
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

class MyNewTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabaseHelper::setupFileDatabase();
    }

    protected function tearDown(): void
    {
        TestDatabaseHelper::cleanup();
    }

    public function testSomething(): void
    {
        $model = new TestModel();
        $model->name = 'Test';
        $model->save();

        $this->assertNotEquals('-1', $model->getId());
    }

    /**
     * @dataProvider myDataProvider
     */
    public function testWithDataProvider(string $input, string $expected): void
    {
        // Test with various inputs
    }

    public static function myDataProvider(): array
    {
        return [
            'case 1' => ['input1', 'expected1'],
            'case 2' => ['input2', 'expected2'],
        ];
    }
}
```

### Testing Exceptions

```php
public function testExceptionIsThrown(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid');

    // Code that should throw
    TestModel::exists(1, 'invalid-column');
}
```

### Resetting Static State

Some tests may need to reset PicoORM's static connection state:

```php
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
```

---

## Test Coverage

### Generating Coverage Reports

```bash
# Requires Xdebug or PCOV PHP extension
composer test-coverage
```

### Coverage Targets

Aim for the following coverage targets:

| Component | Target |
|-----------|--------|
| Connection Management | 90%+ |
| CRUD Operations | 95%+ |
| Validation | 95%+ |
| Query Methods | 90%+ |
| Error Handling | 85%+ |

---

## Continuous Integration

### GitHub Actions Example

Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php-version: ['8.0', '8.1', '8.2', '8.3']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: pdo, pdo_sqlite
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run tests
        run: composer test

      - name: Upload coverage
        if: matrix.php-version == '8.2'
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage/clover.xml
```

---

## Troubleshooting

### Common Issues

**Tests fail with "class not found"**

Make sure you've run `composer install` to set up autoloading.

**SQLite errors**

Ensure the PDO SQLite driver is installed:

```bash
php -m | grep -i sqlite
```

**Permission errors on /tmp**

Tests use `/tmp/picoorm_test.db`. Ensure the directory is writable.

**Tests hang or timeout**

Check for infinite loops in auto-save. The destructor calls `writeChanges()`, which could cause issues if the object is in an invalid state.

### Debug Mode

Run PHPUnit with debug output:

```bash
./vendor/bin/phpunit --debug
```

---

*Last updated: December 2024 for PicoORM v2.0.0*
