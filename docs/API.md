# PicoORM API Documentation

**Version:** 2.1.1
**Namespace:** `PaigeJulianne`
**License:** GPL-3.0-or-later

---

## Table of Contents

- [Class Constants](#class-constants)
- [Connection Management](#connection-management)
- [Constructor](#constructor)
- [CRUD Operations](#crud-operations)
- [Query Methods](#query-methods)
- [Finder Methods](#finder-methods)
- [Counting & Aggregation](#counting--aggregation)
- [Property Access](#property-access)
- [Dirty Checking](#dirty-checking)
- [Data Export](#data-export)
- [Atomic Operations](#atomic-operations)
- [Transaction Support](#transaction-support)
- [Type Validation](#type-validation)
- [Low-Level Query Methods](#low-level-query-methods)

---

## Class Constants

### `TABLE_OVERRIDE`
```php
const TABLE_OVERRIDE = '';
```
Override in child classes to specify a custom table name instead of using the class name.

**Example:**
```php
class Product extends PicoORM {
    const TABLE_OVERRIDE = 'shop_products';
}
```

---

### `CONNECTION`
```php
const CONNECTION = 'default';
```
Override in child classes to specify which database connection to use.

**Example:**
```php
class AnalyticsEvent extends PicoORM {
    const CONNECTION = 'analytics';
}
```

---

### `VALIDATE_TYPES`
```php
const VALIDATE_TYPES = true;
```
Override in child classes to disable automatic type validation against the database schema.

**Example:**
```php
class LegacyData extends PicoORM {
    const VALIDATE_TYPES = false;
}
```

---

## Connection Management

### `setConnectionsFile()`
```php
public static function setConnectionsFile(string $path): void
```
Set the path to the connections configuration file.

**Parameters:**
- `$path` (string) - Absolute or relative path to `.connections` file

**Example:**
```php
PicoORM::setConnectionsFile('/etc/myapp/.connections');
```

---

### `loadConnections()`
```php
public static function loadConnections(): void
```
Manually load and parse the connections file. Called automatically on first database access.

**Throws:** `\RuntimeException` if the file cannot be read or parsed

---

### `addConnection()`
```php
public static function addConnection(
    string $name,
    string $dsn,
    string $user = '',
    string $pass = '',
    array $options = []
): void
```
Programmatically add or update a connection configuration.

**Parameters:**
- `$name` (string) - Connection name
- `$dsn` (string) - PDO DSN string
- `$user` (string) - Database username
- `$pass` (string) - Database password
- `$options` (array) - PDO options array

**Example:**
```php
PicoORM::addConnection(
    'analytics',
    'mysql:host=localhost;dbname=analytics',
    'user',
    'pass',
    [PDO::ATTR_PERSISTENT => true]
);
```

---

### `getConnectionNames()`
```php
public static function getConnectionNames(): array
```
Get all configured connection names.

**Returns:** `array` - List of connection names

---

### `hasConnection()`
```php
public static function hasConnection(string $name): bool
```
Check if a named connection exists.

**Parameters:**
- `$name` (string) - Connection name

**Returns:** `bool`

---

### `getLastInsertId()`
```php
public static function getLastInsertId(): int
```
Get the last auto-increment ID from an INSERT operation.

**Returns:** `int`

---

## Constructor

### `__construct()`
```php
public function __construct(
    string|int|bool $id_value = false,
    string $id_column = 'id'
)
```
Create a new PicoORM instance.

**Parameters:**
- `$id_value` (string|int|bool) - The ID value to load, or `false` to create a new record
- `$id_column` (string) - The name of the ID column (default: `'id'`)

**Example:**
```php
// Create new record
$user = new Users();

// Load existing record by ID
$user = new Users(42);

// Load by custom column
$user = new Users('alice@example.com', 'email');
```

---

## CRUD Operations

### `save()`
```php
public function save(): void
```
Persist changes to the database. Alias for `writeChanges()`.

---

### `writeChanges()`
```php
public function writeChanges(): void
```
Write all pending property changes to the database.

**Throws:**
- `\InvalidArgumentException` if a property name is invalid
- `\TypeError` if type validation fails

---

### `delete()`
```php
public function delete(): void
```
Delete the current record from the database.

**Warning:** This action is irreversible!

---

### `refreshProperties()`
```php
public function refreshProperties(): void
```
Reload the record's properties from the database, discarding any unsaved changes.

---

### `exists()`
```php
public static function exists(
    int|string $id_value,
    string $id_column = 'id'
): bool
```
Check if a record exists in the database.

**Parameters:**
- `$id_value` (int|string) - The value to check
- `$id_column` (string) - The column to check against

**Returns:** `bool`

**Throws:** `\InvalidArgumentException` if the column name is invalid

---

### `getId()`
```php
public function getId(): string|int
```
Get the current record's ID.

**Returns:** `string|int` - The ID value, or `'-1'` if not saved

---

### `getConnection()`
```php
public function getConnection(): string
```
Get the connection name used by this instance.

**Returns:** `string`

---

## Query Methods

### `getAllObjects()`
```php
public static function getAllObjects(
    string $idColumn = 'id',
    array $filters = [],
    string $filterGlue = 'AND',
    bool $forceArray = false,
    ?int $limit = null,
    int $offset = 0,
    ?string $orderBy = null,
    string $orderDir = 'ASC'
): array|static
```
Retrieve multiple records from the database with optional filtering, pagination, and ordering.

**Parameters:**
- `$idColumn` (string) - The column to use as the primary key
- `$filters` (array) - Array of filters: `[[column, null, operator, value], ...]`
- `$filterGlue` (string) - Join statement for filters (`'AND'` or `'OR'`)
- `$forceArray` (bool) - Force array return even for single results
- `$limit` (int|null) - Maximum number of records to return
- `$offset` (int) - Number of records to skip
- `$orderBy` (string|null) - Column to order by
- `$orderDir` (string) - Order direction (`'ASC'` or `'DESC'`)

**Returns:** `array|static` - Array of objects, single object, or empty array

**Throws:** `\InvalidArgumentException` if column names or operators are invalid

**Supported Operators:** `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS`, `IS NOT`

**Example:**
```php
// Get all users
$users = Users::getAllObjects();

// Get active admins
$admins = Users::getAllObjects('id', [
    ['role', null, '=', 'admin'],
    ['is_active', null, '=', 1]
], 'AND');

// Paginated results
$page1 = Users::getAllObjects('id', [], 'AND', true, 10, 0, 'created_at', 'DESC');
```

---

## Finder Methods

### `findBy()`
```php
public static function findBy(
    string $column,
    mixed $value,
    string $idColumn = 'id',
    string $operator = '='
): array
```
Find all records matching a column value.

**Parameters:**
- `$column` (string) - The column to search by
- `$value` (mixed) - The value to match
- `$idColumn` (string) - The ID column name
- `$operator` (string) - The comparison operator

**Returns:** `array` - Array of model instances

---

### `findOneBy()`
```php
public static function findOneBy(
    string $column,
    mixed $value,
    string $idColumn = 'id'
): ?static
```
Find a single record matching a column value.

**Parameters:**
- `$column` (string) - The column to search by
- `$value` (mixed) - The value to match
- `$idColumn` (string) - The ID column name

**Returns:** `static|null` - The model instance or null if not found

---

### `firstOrCreate()`
```php
public static function firstOrCreate(
    array $attributes,
    array $values = [],
    string $idColumn = 'id'
): static
```
Find a record or create it if it doesn't exist.

**Parameters:**
- `$attributes` (array) - Attributes to search by
- `$values` (array) - Additional values to set when creating
- `$idColumn` (string) - The ID column name

**Returns:** `static` - The found or created model instance

**Example:**
```php
$user = Users::firstOrCreate(
    ['email' => 'alice@example.com'],
    ['name' => 'Alice', 'role' => 'user']
);
```

---

### `updateOrCreate()`
```php
public static function updateOrCreate(
    array $attributes,
    array $values = [],
    string $idColumn = 'id'
): static
```
Find a record and update it, or create it if it doesn't exist.

**Parameters:**
- `$attributes` (array) - Attributes to search by
- `$values` (array) - Values to update or set when creating
- `$idColumn` (string) - The ID column name

**Returns:** `static` - The found/updated or created model instance

---

## Counting & Aggregation

### `count()`
```php
public static function count(
    array $filters = [],
    string $filterGlue = 'AND'
): int
```
Count records matching the given filters.

**Parameters:**
- `$filters` (array) - Array of filters
- `$filterGlue` (string) - Join statement for filters

**Returns:** `int` - Number of matching records

**Example:**
```php
$totalUsers = Users::count();
$activeAdmins = Users::count([
    ['role', null, '=', 'admin'],
    ['is_active', null, '=', 1]
]);
```

---

### `pluck()`
```php
public static function pluck(
    string $column,
    array $filters = [],
    string $filterGlue = 'AND'
): array
```
Get an array of values from a single column.

**Parameters:**
- `$column` (string) - The column to pluck values from
- `$filters` (array) - Optional filters
- `$filterGlue` (string) - Join statement for filters

**Returns:** `array` - Array of column values

**Example:**
```php
$emails = Users::pluck('email');
$adminEmails = Users::pluck('email', [['role', null, '=', 'admin']]);
```

---

## Property Access

### `__get()`
```php
public function __get(string $prop): string|array|int|float|bool|null
```
Get a property value.

**Parameters:**
- `$prop` (string) - Property name

**Returns:** `string|array|int|float|bool|null` - Property value or null if not set

---

### `__set()`
```php
public function __set(
    string $prop,
    string|array|int|float|bool|null $value
): void
```
Set a property value. Validates type if `VALIDATE_TYPES` is enabled.

**Parameters:**
- `$prop` (string) - Property name
- `$value` (mixed) - Property value

**Throws:** `\TypeError` if type validation fails

---

### `__isset()`
```php
public function __isset(string $prop): bool
```
Check if a property is set.

**Parameters:**
- `$prop` (string) - Property name

**Returns:** `bool`

---

### `setMulti()`
```php
public function setMulti(array $array): void
```
Set multiple properties at once from an array.

**Parameters:**
- `$array` (array) - Associative array of property => value pairs

**Example:**
```php
$user->setMulti([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'role' => 'admin'
]);
```

---

## Dirty Checking

### `isDirty()`
```php
public function isDirty(?string $column = null): bool
```
Check if the model has unsaved changes.

**Parameters:**
- `$column` (string|null) - Check specific column, or null for any column

**Returns:** `bool`

---

### `isClean()`
```php
public function isClean(): bool
```
Check if the model has no unsaved changes.

**Returns:** `bool`

---

### `getDirty()`
```php
public function getDirty(): array
```
Get all changed properties and their new values.

**Returns:** `array` - Associative array of column => new value

---

### `getOriginal()`
```php
public function getOriginal(?string $column = null): mixed
```
Get the original value of a property before modifications.

**Parameters:**
- `$column` (string|null) - Specific column, or null for all original values

**Returns:** `mixed`

---

### `fresh()`
```php
public function fresh(): ?static
```
Return a fresh instance of the model from the database. Does not modify the current instance.

**Returns:** `static|null` - A new instance with fresh data, or null if not found

---

## Data Export

### `toArray()`
```php
public function toArray(?array $columns = null): array
```
Export the current record as an associative array.

**Parameters:**
- `$columns` (array|null) - Specific columns to include, or null for all

**Returns:** `array`

**Example:**
```php
$data = $user->toArray();
$partial = $user->toArray(['name', 'email']);
```

---

## Atomic Operations

### `increment()`
```php
public function increment(
    string $column,
    int|float $amount = 1
): void
```
Atomically increment a column value.

**Parameters:**
- `$column` (string) - The column to increment
- `$amount` (int|float) - The amount to increment by

**Throws:**
- `\InvalidArgumentException` if column name is invalid
- `\RuntimeException` if record doesn't exist in database

---

### `decrement()`
```php
public function decrement(
    string $column,
    int|float $amount = 1
): void
```
Atomically decrement a column value.

**Parameters:**
- `$column` (string) - The column to decrement
- `$amount` (int|float) - The amount to decrement by

---

## Transaction Support

### `beginTransaction()`
```php
public static function beginTransaction(?string $connectionName = null): void
```
Begin a database transaction.

**Parameters:**
- `$connectionName` (string|null) - Connection name, or null for class default

**Throws:** `\PDOException` if already in a transaction or connection fails

---

### `commit()`
```php
public static function commit(?string $connectionName = null): void
```
Commit the current transaction.

**Parameters:**
- `$connectionName` (string|null) - Connection name, or null for class default

**Throws:** `\PDOException` if no transaction is active or commit fails

---

### `rollback()`
```php
public static function rollback(?string $connectionName = null): void
```
Roll back the current transaction.

**Parameters:**
- `$connectionName` (string|null) - Connection name, or null for class default

---

### `inTransaction()`
```php
public static function inTransaction(?string $connectionName = null): bool
```
Check if currently in a transaction.

**Parameters:**
- `$connectionName` (string|null) - Connection name, or null for class default

**Returns:** `bool`

---

### `transaction()`
```php
public static function transaction(
    callable $callback,
    ?string $connectionName = null
): mixed
```
Execute a callback within a transaction. Automatically rolls back on exception.

**Parameters:**
- `$callback` (callable) - The callback to execute
- `$connectionName` (string|null) - Connection name, or null for class default

**Returns:** `mixed` - The return value of the callback

**Throws:** `\Throwable` - Re-throws any exception from the callback after rollback

**Example:**
```php
$orderId = PicoORM::transaction(function () {
    $user = new Users();
    $user->name = 'Alice';
    $user->save();

    $order = new Orders();
    $order->user_id = $user->getId();
    $order->save();

    return $order->getId();
});
```

---

### `clearConnectionCache()`
```php
public static function clearConnectionCache(?string $connectionName = null): void
```
Clear the PDO connection cache.

**Parameters:**
- `$connectionName` (string|null) - Specific connection to clear, or null for all

---

## Type Validation

### `getTableSchema()`
```php
public static function getTableSchema(?string $connectionName = null): array
```
Get the table schema (column definitions).

**Parameters:**
- `$connectionName` (string|null) - Connection name, or null for class default

**Returns:** `array<string, array>` - Column definitions with keys:
- `type` - Database column type
- `php_type` - Expected PHP type (`integer`, `float`, `string`, `boolean`)
- `nullable` - Whether NULL is allowed
- `default` - Default value
- `primary_key` - Whether this is a primary key
- `max_length` - Maximum length for string types
- `raw_type` - Raw database type string

---

### `validateColumnValue()`
```php
public function validateColumnValue(
    string $column,
    mixed $value,
    bool $throw = true
): bool
```
Validate a value against the column's expected type.

**Parameters:**
- `$column` (string) - The column name
- `$value` (mixed) - The value to validate
- `$throw` (bool) - Whether to throw exception on failure

**Returns:** `bool` - True if valid

**Throws:** `\TypeError` if value type doesn't match and `$throw` is true

---

### `validateAllChanges()`
```php
public function validateAllChanges(): bool
```
Validate all pending changes before saving.

**Returns:** `bool` - True if all values are valid

**Throws:** `\TypeError` if any value type doesn't match

---

### `clearSchemaCache()`
```php
public static function clearSchemaCache(?string $table = null): void
```
Clear the schema cache.

**Parameters:**
- `$table` (string|null) - Specific table to clear, or null for all

---

## Low-Level Query Methods

### `_fetch()`
```php
public static function _fetch(
    string $sql,
    array $valueArray = [],
    ?string $table = null
): array
```
Fetch the first matching record from the database.

**Parameters:**
- `$sql` (string) - PDO-ready SQL statement (use `_DB_` for table name)
- `$valueArray` (array) - Values for PDO parameter substitution
- `$table` (string|null) - Optional table name override

**Returns:** `array` - Associative array, or empty array if not found

---

### `_fetchAll()`
```php
public static function _fetchAll(
    string $sql,
    array $valueArray = [],
    ?string $table = null
): array
```
Fetch all matching records from the database.

**Parameters:**
- `$sql` (string) - PDO-ready SQL statement (use `_DB_` for table name)
- `$valueArray` (array) - Values for PDO parameter substitution
- `$table` (string|null) - Optional table name override

**Returns:** `array` - Array of associative arrays

---

### `_doQuery()`
```php
public static function _doQuery(
    string $sql,
    array $valueArray = [],
    ?string $table = null
): \PDOStatement
```
Execute a SQL statement and return the PDO statement.

**Parameters:**
- `$sql` (string) - PDO-ready SQL statement (use `_DB_` for table name)
- `$valueArray` (array) - Values for PDO parameter substitution
- `$table` (string|null) - Optional table name override

**Returns:** `\PDOStatement`

**Throws:**
- `\PDOException` if the database connection or query fails
- `\RuntimeException` if the prepared statement fails

**Example:**
```php
Users::_doQuery(
    'UPDATE _DB_ SET last_login = NOW() WHERE id = ?',
    [42]
);
```
