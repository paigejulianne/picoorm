# PicoORM

A lightweight, secure ORM for PHP 8.0+ that makes database operations simple without the overhead of a full-featured framework.

**Version 2.1.1** | [Changelog](CHANGELOG.md) | [License: GPL-3.0](LICENSE)

by Paige Julianne Sullivan
[paigejulianne.com](https://paigejulianne.com) | [GitHub](https://github.com/paigejulianne/picoorm)

---

## Features

- **Minimal footprint**: Single-file ORM (~1200 lines)
- **Multiple connections**: Support for multiple named database connections
- **Secure by default**: SQL injection protection via prepared statements and identifier validation
- **Zero dependencies**: Only requires PHP 8.0+ and PDO
- **Auto-save**: Changes are automatically persisted when objects go out of scope
- **Cross-database**: Works with MySQL, PostgreSQL, SQLite, and other PDO-supported databases
- **Transaction support**: Begin, commit, and rollback transactions with ease
- **Dirty checking**: Track which fields have been modified

---

## Installation

### Via Composer (Recommended)

```bash
composer require paigejulianne/picoorm
```

### Manual Installation

Download `PicoORM.php` and include it in your project:

```php
require_once 'PicoORM.php';
```

---

## Quick Start

### 1. Configure Your Database Connection

Create a `.connections` file in your project root:

```ini
[default]
DSN=mysql:host=localhost;dbname=myapp;charset=utf8mb4
USER=myuser
PASS=mypassword
```

> **Security Note**: Add `.connections` to your `.gitignore` to prevent committing credentials!

### 2. Create a Model Class

Create a class that extends `PicoORM`, named after your database table:

```php
use PaigeJulianne\PicoORM;

class Users extends PicoORM
{
    // That's it! PicoORM maps this class to the 'users' table
}
```

### 3. Start Using It

```php
// Create a new user
$user = new Users();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$user->save();

echo "Created user with ID: " . $user->getId();

// Load an existing user
$user = new Users(1);
echo $user->name; // Output: Alice

// Update a user
$user->name = 'Alice Smith';
$user->save();

// Delete a user
$user->delete();
```

---

## Configuration

### Option 1: Using a `.connections` File (Recommended)

The `.connections` file uses an INI-like format with sections for each database connection:

```ini
# Primary database
[default]
DSN=mysql:host=localhost;dbname=myapp;charset=utf8mb4
USER=app_user
PASS=secure_password

# Analytics database on a separate server
[analytics]
DSN=mysql:host=analytics.example.com;dbname=analytics
USER=analytics_readonly
PASS=analytics_password

# SQLite for local caching
[cache]
DSN=sqlite:/var/cache/myapp/cache.db
USER=
PASS=
```

PicoORM searches for the `.connections` file in these locations (in order):

1. The path specified via `setConnectionsFile()`
2. The parent directory of PicoORM.php
3. The current working directory

#### PDO Options

You can specify PDO options using the `OPTIONS[constant]` syntax:

```ini
[production]
DSN=mysql:host=db.example.com;dbname=prod
USER=prod_user
PASS=prod_password
OPTIONS[PDO::ATTR_PERSISTENT]=true
OPTIONS[PDO::ATTR_TIMEOUT]=10
```

### Option 2: Programmatic Configuration

Add connections at runtime using `addConnection()`:

```php
use PaigeJulianne\PicoORM;

PicoORM::addConnection(
    'default',
    'mysql:host=localhost;dbname=myapp',
    'username',
    'password',
    [PDO::ATTR_PERSISTENT => true]
);
```

### Option 3: Legacy Global Variables (Deprecated)

For backward compatibility, global variables are still supported:

```php
global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS, $PICOORM_OPTIONS;

$PICOORM_DSN = 'mysql:host=localhost;dbname=myapp';
$PICOORM_USER = 'username';
$PICOORM_PASS = 'password';
$PICOORM_OPTIONS = [PDO::ATTR_PERSISTENT => true];
```

> **Note**: This method is deprecated. Please migrate to `.connections` file or `addConnection()`.

---

## Working with Models

### Defining Models

Create a class extending `PicoORM` for each database table. The class name (lowercased) becomes the table name:

```php
use PaigeJulianne\PicoORM;

// Maps to the 'products' table
class Products extends PicoORM {}

// Maps to the 'order_items' table
class OrderItems extends PicoORM {}
```

#### Custom Table Names

Override the table name using the `TABLE_OVERRIDE` constant:

```php
class Product extends PicoORM
{
    const TABLE_OVERRIDE = 'shop_products';
}
```

#### Specifying a Connection

For models that use a non-default database connection:

```php
class AnalyticsEvent extends PicoORM
{
    const CONNECTION = 'analytics';
}
```

### Creating Records

```php
// Method 1: Set properties individually
$product = new Products();
$product->name = 'Widget';
$product->price = 29.99;
$product->category_id = 5;
$product->save();

// Method 2: Set multiple properties at once
$product = new Products();
$product->setMulti([
    'name' => 'Gadget',
    'price' => 49.99,
    'category_id' => 3
]);
$product->save();

// Get the new record's ID
$newId = Products::getLastInsertId();
// or
$newId = $product->getId();
```

### Loading Records

```php
// Load by primary key (defaults to 'id' column)
$user = new Users(42);

// Load using a different column
$user = new Users('alice@example.com', 'email');

// Check if the record was found
if ($user->getId() === '-1') {
    echo "User not found";
}
```

### Updating Records

```php
$user = new Users(1);
$user->name = 'Updated Name';
$user->email = 'newemail@example.com';
$user->save();

// Or let auto-save handle it when the object is destroyed
function updateUser($id, $name) {
    $user = new Users($id);
    $user->name = $name;
    // save() is called automatically when $user goes out of scope
}
```

### Deleting Records

```php
$user = new Users(1);
$user->delete();
```

### Checking If a Record Exists

```php
// Check by ID
if (Users::exists(42)) {
    echo "User exists";
}

// Check by another column
if (Users::exists('alice@example.com', 'email')) {
    echo "Email is registered";
}
```

### Checking Properties

```php
$user = new Users(1);

if (isset($user->phone)) {
    echo "Phone: " . $user->phone;
} else {
    echo "No phone on file";
}
```

### Refreshing Data

Reload the record from the database to get the latest values:

```php
$user = new Users(1);
// ... some time passes, data may have changed ...
$user->refreshProperties();
```

---

## Querying Multiple Records

Use `getAllObjects()` to retrieve multiple records with optional filtering:

```php
// Get all users
$users = Users::getAllObjects();

// Get users with filters
$activeAdmins = Users::getAllObjects('id', [
    ['status', null, '=', 'active'],
    ['role', null, '=', 'admin']
], 'AND');

// Use OR logic
$featured = Products::getAllObjects('id', [
    ['is_featured', null, '=', 1],
    ['is_bestseller', null, '=', 1]
], 'OR');

// Force array return (even for single results)
$results = Users::getAllObjects('id', [], 'AND', true);
```

### Filter Format

Each filter is an array: `[column, null, operator, value]`

**Supported operators**: `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS`, `IS NOT`

```php
// Find users with Gmail addresses
$gmailUsers = Users::getAllObjects('id', [
    ['email', null, 'LIKE', '%@gmail.com']
]);

// Find products in a price range
$affordableProducts = Products::getAllObjects('id', [
    ['price', null, '>=', 10],
    ['price', null, '<=', 50]
], 'AND');
```

### Pagination and Ordering

`getAllObjects()` supports pagination with `limit`, `offset`, `orderBy`, and `orderDir` parameters:

```php
// Get first 10 users ordered by name
$users = Users::getAllObjects(
    idColumn: 'id',
    filters: [],
    filterGlue: 'AND',
    forceArray: true,
    limit: 10,
    offset: 0,
    orderBy: 'name',
    orderDir: 'ASC'
);

// Get page 2 (records 11-20)
$page2 = Users::getAllObjects('id', [], 'AND', true, 10, 10, 'created_at', 'DESC');
```

---

## Finder Methods

### Finding by Column

```php
// Find all users with a specific role
$admins = Users::findBy('role', 'admin');

// Find with a different operator
$recentUsers = Users::findBy('created_at', '2024-01-01', 'id', '>=');

// Find a single record
$user = Users::findOneBy('email', 'alice@example.com');

if ($user === null) {
    echo "User not found";
}
```

### First or Create (Upsert)

Find a record or create it if it doesn't exist:

```php
// Find user by email, or create with additional attributes
$user = Users::firstOrCreate(
    ['email' => 'alice@example.com'],           // Search criteria
    ['name' => 'Alice', 'role' => 'user']       // Values for new record
);

// The returned user either existed or was just created
echo $user->name;
```

### Update or Create

Find a record and update it, or create if it doesn't exist:

```php
// Update existing user's login time, or create new user
$user = Users::updateOrCreate(
    ['email' => 'alice@example.com'],           // Search criteria
    ['last_login' => date('Y-m-d H:i:s')]       // Values to update/set
);
```

---

## Counting Records

```php
// Count all records
$totalUsers = Users::count();

// Count with filters
$activeAdmins = Users::count([
    ['role', null, '=', 'admin'],
    ['is_active', null, '=', 1]
], 'AND');

// Count with OR logic
$specialUsers = Users::count([
    ['role', null, '=', 'admin'],
    ['role', null, '=', 'moderator']
], 'OR');
```

---

## Plucking Column Values

Get an array of values from a single column:

```php
// Get all usernames
$usernames = Users::pluck('username');
// Returns: ['alice', 'bob', 'charlie']

// Get emails of active users
$emails = Users::pluck('email', [
    ['is_active', null, '=', 1]
]);
```

---

## Data Export

### Converting to Array

```php
$user = new Users(1);

// Export all properties
$data = $user->toArray();
// Returns: ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', ...]

// Export specific columns only
$data = $user->toArray(['name', 'email']);
// Returns: ['name' => 'Alice', 'email' => 'alice@example.com']
```

---

## Dirty Checking

Track which properties have been modified:

```php
$user = new Users(1);

// Check if any changes have been made
$user->isClean();  // true
$user->isDirty();  // false

// Make a change
$user->name = 'New Name';

$user->isDirty();           // true
$user->isDirty('name');     // true
$user->isDirty('email');    // false

// Get all changed properties
$changes = $user->getDirty();
// Returns: ['name' => 'New Name']

// Get original value before changes
$original = $user->getOriginal('name');  // 'Alice'

// Get all original values
$allOriginal = $user->getOriginal();
```

### Fresh Instance

Get a new instance with fresh data from the database (without modifying current instance):

```php
$user = new Users(1);
$user->name = 'Modified';

// Get a fresh copy from database
$freshUser = $user->fresh();

echo $user->name;       // 'Modified' (still has local changes)
echo $freshUser->name;  // 'Alice' (fresh from database)
```

---

## Atomic Operations

### Increment and Decrement

Atomically update numeric columns:

```php
$product = new Products(1);

// Increment view count
$product->increment('view_count');

// Increment by a specific amount
$product->increment('stock', 10);

// Decrement
$product->decrement('stock', 5);

// Works with floats too
$product->increment('price', 0.50);
```

---

## Transactions

### Manual Transaction Control

```php
use PaigeJulianne\PicoORM;

try {
    PicoORM::beginTransaction();

    $user = new Users();
    $user->name = 'Alice';
    $user->save();

    $order = new Orders();
    $order->user_id = $user->getId();
    $order->total = 99.99;
    $order->save();

    PicoORM::commit();
} catch (\Exception $e) {
    PicoORM::rollback();
    throw $e;
}
```

### Transaction Callback

A cleaner approach using a callback:

```php
$result = PicoORM::transaction(function () {
    $user = new Users();
    $user->name = 'Alice';
    $user->save();

    $order = new Orders();
    $order->user_id = $user->getId();
    $order->total = 99.99;
    $order->save();

    return $order->getId();
});

echo "Created order: $result";
```

If any exception is thrown inside the callback, the transaction is automatically rolled back.

### Transaction Status

```php
// Check if currently in a transaction
if (PicoORM::inTransaction()) {
    // ...
}

// Transactions work with multiple connections
PicoORM::beginTransaction('analytics');
// ... operations on analytics connection ...
PicoORM::commit('analytics');
```

---

## Type Validation

PicoORM automatically validates data types against your database schema, catching type mismatches before they reach the database.

### How It Works

When you set a property or save a record, PicoORM:
1. Fetches the table schema (cached per request)
2. Validates the value type matches the column type
3. Throws a `TypeError` if validation fails

```php
// Assuming 'age' is an INT column in the database
$user = new Users();
$user->name = 'Alice';           // OK - string to VARCHAR
$user->age = 25;                 // OK - int to INT
$user->age = 'twenty-five';      // TypeError: expected integer, got string

// String length validation
// Assuming 'username' is VARCHAR(50)
$user->username = str_repeat('a', 100);  // TypeError: exceeds max length of 50
```

### Disabling Validation

Disable validation for a specific model by overriding the constant:

```php
class LegacyData extends PicoORM
{
    const VALIDATE_TYPES = false;  // Disable type checking
}
```

### Nullable Columns

Null values are only allowed for nullable columns:

```php
// If 'email' is defined as NOT NULL
$user->email = null;  // TypeError: Column 'email' does not allow NULL values

// If 'phone' allows NULL
$user->phone = null;  // OK
```

### Type Coercion Rules

PicoORM allows sensible type coercions:

| Database Type | Accepts |
|---------------|---------|
| INTEGER | `int`, `bool`, numeric strings (`"123"`) |
| FLOAT/DECIMAL | `float`, `int`, numeric strings |
| VARCHAR/TEXT | `string`, `int`, `float`, `bool` (auto-converted) |
| BOOLEAN | `bool`, `0`, `1`, `"0"`, `"1"` |

### Inspecting Schema

You can inspect the detected schema:

```php
$schema = Users::getTableSchema();

print_r($schema);
// [
//     'id' => ['type' => 'int', 'php_type' => 'integer', 'nullable' => false, ...],
//     'name' => ['type' => 'varchar', 'php_type' => 'string', 'max_length' => 255, ...],
//     ...
// ]
```

### Manual Validation

Validate a value without setting it:

```php
$user = new Users(1);

// Returns true/false without throwing
$isValid = $user->validateColumnValue('age', 'invalid', throw: false);

// Or validate all pending changes
$user->validateAllChanges();
```

### Clearing Schema Cache

If your schema changes during runtime:

```php
// Clear all cached schemas
PicoORM::clearSchemaCache();

// Clear specific table
PicoORM::clearSchemaCache('users');
```

---

## Custom Queries

For complex queries, use the low-level query methods. Use `_DB_` as a placeholder for the table name:

```php
// Fetch a single record
$result = Users::_fetch(
    'SELECT * FROM _DB_ WHERE email = ? AND status = ?',
    ['alice@example.com', 'active']
);

// Fetch multiple records
$results = Users::_fetchAll(
    'SELECT * FROM _DB_ WHERE created_at > ? ORDER BY name',
    ['2024-01-01']
);

// Execute a query (INSERT, UPDATE, DELETE)
Users::_doQuery(
    'UPDATE _DB_ SET last_login = NOW() WHERE id = ?',
    [42]
);
```

---

## Multiple Database Connections

PicoORM supports connecting to multiple databases simultaneously.

### Define Connections

In your `.connections` file:

```ini
[default]
DSN=mysql:host=localhost;dbname=main_app
USER=app_user
PASS=app_password

[analytics]
DSN=mysql:host=analytics-server;dbname=analytics
USER=analytics_user
PASS=analytics_password

[legacy]
DSN=mysql:host=old-server;dbname=legacy_data
USER=legacy_user
PASS=legacy_password
```

### Use Connections in Models

```php
// Uses 'default' connection
class Users extends PicoORM {}

// Uses 'analytics' connection
class PageView extends PicoORM
{
    const CONNECTION = 'analytics';
}

// Uses 'legacy' connection
class OldCustomer extends PicoORM
{
    const CONNECTION = 'legacy';
    const TABLE_OVERRIDE = 'tbl_customers';  // Legacy table name
}
```

### Connection Management

```php
// List all configured connections
$connections = PicoORM::getConnectionNames();
// Returns: ['default', 'analytics', 'legacy']

// Check if a connection exists
if (PicoORM::hasConnection('analytics')) {
    // Analytics connection is configured
}

// Get the connection used by an instance
$pageView = new PageView();
echo $pageView->getConnection(); // Output: analytics
```

---

## Security

PicoORM includes several security measures:

### SQL Injection Protection

1. **Prepared Statements**: All values are passed through PDO prepared statements
2. **Identifier Validation**: Column names and operators are validated against strict patterns
3. **Operator Whitelist**: Only approved SQL operators are allowed in filters

### Valid Identifiers

Column and table names must:
- Start with a letter (a-z, A-Z) or underscore (_)
- Contain only letters, numbers, and underscores

```php
// Valid
$user->first_name = 'Alice';     // OK
$user->address_line_1 = '123';   // OK

// Invalid - will throw InvalidArgumentException
$user->{'first-name'} = 'Alice'; // Error: hyphens not allowed
```

### Credentials Protection

- Store credentials in `.connections` file (not in code)
- Add `.connections` to `.gitignore`
- Use environment-specific connection files in production

---

## Error Handling

PicoORM throws exceptions for error conditions:

```php
use PaigeJulianne\PicoORM;

try {
    $user = new Users(1);
    $user->name = 'New Name';
    $user->save();
} catch (\PDOException $e) {
    // Database connection or query error
    error_log("Database error: " . $e->getMessage());
} catch (\InvalidArgumentException $e) {
    // Invalid column name, operator, or filter
    error_log("Invalid argument: " . $e->getMessage());
} catch (\RuntimeException $e) {
    // Connection not configured, statement preparation failed
    error_log("Runtime error: " . $e->getMessage());
}
```

---

## Database Compatibility

PicoORM works with any PDO-supported database:

| Database   | Status | Notes |
|------------|--------|-------|
| MySQL      | Full   | Primary development target |
| MariaDB    | Full   | Compatible with MySQL |
| PostgreSQL | Full   | Tested and supported |
| SQLite     | Full   | Great for development/testing |
| SQL Server | Partial| May require adjustments |

**Note**: Column names are escaped with backticks, which is MySQL/MariaDB syntax. For maximum compatibility, use simple alphanumeric column names.

---

## API Reference

### Static Methods

| Method | Description |
|--------|-------------|
| `setConnectionsFile($path)` | Set the path to the connections file |
| `loadConnections()` | Manually load connection configuration |
| `addConnection($name, $dsn, $user, $pass, $options)` | Add a connection programmatically |
| `getConnectionNames()` | Get list of configured connection names |
| `hasConnection($name)` | Check if a connection exists |
| `exists($id, $column)` | Check if a record exists |
| `getAllObjects($idColumn, $filters, $glue, $forceArray, $limit, $offset, $orderBy, $orderDir)` | Retrieve multiple records with pagination |
| `getLastInsertId()` | Get the last auto-increment ID |
| `count($filters, $filterGlue)` | Count records matching filters |
| `pluck($column, $filters, $filterGlue)` | Get array of values from a single column |
| `findBy($column, $value, $idColumn, $operator)` | Find all records matching a column value |
| `findOneBy($column, $value, $idColumn)` | Find a single record matching a column value |
| `firstOrCreate($attributes, $values, $idColumn)` | Find or create a record |
| `updateOrCreate($attributes, $values, $idColumn)` | Find and update, or create a record |
| `beginTransaction($connectionName)` | Begin a database transaction |
| `commit($connectionName)` | Commit the current transaction |
| `rollback($connectionName)` | Roll back the current transaction |
| `inTransaction($connectionName)` | Check if currently in a transaction |
| `transaction($callback, $connectionName)` | Execute callback within a transaction |
| `clearConnectionCache($connectionName)` | Clear cached PDO connections |
| `getTableSchema($connectionName)` | Get table column definitions |
| `clearSchemaCache($table)` | Clear cached schema information |
| `_fetch($sql, $values, $table)` | Fetch single record with custom SQL |
| `_fetchAll($sql, $values, $table)` | Fetch multiple records with custom SQL |
| `_doQuery($sql, $values, $table)` | Execute custom SQL |

### Instance Methods

| Method | Description |
|--------|-------------|
| `getId()` | Get the record's primary key value |
| `getConnection()` | Get the connection name used by this instance |
| `save()` | Persist changes to the database |
| `writeChanges()` | Alias for save() |
| `delete()` | Delete the record from the database |
| `refreshProperties()` | Reload data from the database |
| `setMulti($array)` | Set multiple properties at once |
| `toArray($columns)` | Export record as associative array |
| `isDirty($column)` | Check if model has unsaved changes |
| `isClean()` | Check if model has no unsaved changes |
| `getDirty()` | Get all changed properties and their values |
| `getOriginal($column)` | Get original value(s) before modifications |
| `fresh()` | Return a fresh instance from the database |
| `increment($column, $amount)` | Atomically increment a column value |
| `decrement($column, $amount)` | Atomically decrement a column value |
| `validateColumnValue($column, $value, $throw)` | Validate a value against column type |
| `validateAllChanges()` | Validate all pending changes |

### Class Constants

| Constant | Description |
|----------|-------------|
| `TABLE_OVERRIDE` | Override the default table name |
| `CONNECTION` | Specify which database connection to use |
| `VALIDATE_TYPES` | Enable/disable type validation (default: true) |

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Submit a pull request

For bugs and feature requests, use the [GitHub issue tracker](https://github.com/paigejulianne/picoorm/issues).

---

## License

PicoORM is released under the [GPL-3.0-or-later](LICENSE) license.

Copyright 2008-present Paige Julianne Sullivan
