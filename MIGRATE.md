# Migration Guide

This guide helps you migrate from deprecated PicoORM patterns to the recommended approaches in version 2.0.0.

---

## Table of Contents

1. [Database Configuration](#1-database-configuration)
2. [Accessing Last Insert ID](#2-accessing-last-insert-id)
3. [Multiple Database Connections](#3-multiple-database-connections)
4. [Error Handling](#4-error-handling)
5. [Quick Migration Checklist](#quick-migration-checklist)

---

## 1. Database Configuration

### Deprecated: Global Variables

The old method of configuring database connections using global variables is deprecated:

```php
// ❌ DEPRECATED - Don't use this anymore
global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS, $PICOORM_OPTIONS;

$PICOORM_DSN = 'mysql:host=localhost;dbname=myapp';
$PICOORM_USER = 'username';
$PICOORM_PASS = 'password';
$PICOORM_OPTIONS = [PDO::ATTR_PERSISTENT => true];
```

### Recommended: `.connections` File

Create a `.connections` file in your project root:

```ini
# ✅ RECOMMENDED - Use a .connections file
[default]
DSN=mysql:host=localhost;dbname=myapp
USER=username
PASS=password
OPTIONS[PDO::ATTR_PERSISTENT]=true
```

**Benefits:**
- Credentials are separated from code
- Easy to manage multiple environments (dev, staging, prod)
- Supports multiple named connections
- Can be excluded from version control via `.gitignore`

### Alternative: Programmatic Configuration

If you need to configure connections at runtime (e.g., from environment variables):

```php
// ✅ RECOMMENDED - Programmatic configuration
use PaigeJulianne\PicoORM;

PicoORM::addConnection(
    'default',
    $_ENV['DB_DSN'] ?? 'mysql:host=localhost;dbname=myapp',
    $_ENV['DB_USER'] ?? 'username',
    $_ENV['DB_PASS'] ?? 'password',
    [PDO::ATTR_PERSISTENT => true]
);
```

### Migration Steps

1. Create a `.connections` file in your project root
2. Copy your DSN, username, and password to the file
3. Add `.connections` to your `.gitignore`
4. Remove the global variable declarations from your code
5. Test your application

**Before:**
```php
<?php
// config.php
global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS;
$PICOORM_DSN = 'mysql:host=localhost;dbname=myapp';
$PICOORM_USER = 'dbuser';
$PICOORM_PASS = 'dbpass';

require_once 'vendor/autoload.php';

$user = new Users(1);
```

**After:**
```php
<?php
// config.php
require_once 'vendor/autoload.php';

// Connection is automatically loaded from .connections file
$user = new Users(1);
```

```ini
# .connections (new file)
[default]
DSN=mysql:host=localhost;dbname=myapp
USER=dbuser
PASS=dbpass
```

---

## 2. Accessing Last Insert ID

### Deprecated: Direct Property Access

Directly accessing the static `$_lastInsertId` property is deprecated:

```php
// ❌ DEPRECATED
$user = new Users();
$user->name = 'Alice';
$user->save();

$newId = Users::$_lastInsertId;
```

### Recommended: Use `getLastInsertId()`

Use the static method instead:

```php
// ✅ RECOMMENDED
$user = new Users();
$user->name = 'Alice';
$user->save();

$newId = Users::getLastInsertId();
```

### Alternative: Use `getId()`

After saving, the object's ID is automatically updated:

```php
// ✅ ALSO RECOMMENDED
$user = new Users();
$user->name = 'Alice';
$user->save();

$newId = $user->getId();
```

### Migration Steps

1. Search your codebase for `::$_lastInsertId`
2. Replace with `::getLastInsertId()` or use `->getId()` on the saved object

**Find and replace patterns:**
```
Search:  ::$_lastInsertId
Replace: ::getLastInsertId()
```

---

## 3. Multiple Database Connections

### Old Pattern: Single Database Only

Previously, PicoORM only supported a single database connection via global variables.

### New Pattern: Named Connections

You can now define multiple connections and specify which one each model uses.

**Step 1: Define connections in `.connections`:**

```ini
[default]
DSN=mysql:host=localhost;dbname=main_app
USER=app_user
PASS=app_password

[reporting]
DSN=mysql:host=reporting-db;dbname=reports
USER=report_user
PASS=report_password

[legacy]
DSN=mysql:host=old-server;dbname=legacy
USER=legacy_user
PASS=legacy_password
```

**Step 2: Specify connection in your models:**

```php
// Uses 'default' connection (no change needed)
class Users extends PicoORM {}

// Uses 'reporting' connection
class Report extends PicoORM
{
    const CONNECTION = 'reporting';
}

// Uses 'legacy' connection with custom table name
class Customer extends PicoORM
{
    const CONNECTION = 'legacy';
    const TABLE_OVERRIDE = 'tbl_cust_master';
}
```

**Step 3: Use models normally:**

```php
// These queries go to different databases automatically
$user = new Users(1);           // -> main_app.users
$report = new Report(100);      // -> reports.report
$customer = new Customer(50);   // -> legacy.tbl_cust_master
```

---

## 4. Error Handling

### Old Behavior: Silent Failures

In version 1.x, PDO errors might not be visible depending on your configuration.

### New Behavior: Exceptions by Default

Version 2.0 enables `PDO::ERRMODE_EXCEPTION` by default, so database errors throw exceptions.

### Migration Steps

Wrap database operations in try-catch blocks:

**Before:**
```php
// Old code might not handle errors
$user = new Users(1);
$user->email = 'new@example.com';
$user->save();
// If this fails, you might not know!
```

**After:**
```php
// Properly handle potential errors
try {
    $user = new Users(1);
    $user->email = 'new@example.com';
    $user->save();
} catch (\PDOException $e) {
    // Database error (connection failed, query error, etc.)
    error_log("Database error: " . $e->getMessage());
    // Handle gracefully...
} catch (\InvalidArgumentException $e) {
    // Invalid column name or operator
    error_log("Invalid argument: " . $e->getMessage());
} catch (\RuntimeException $e) {
    // Connection not configured
    error_log("Configuration error: " . $e->getMessage());
}
```

---

## Quick Migration Checklist

Use this checklist to ensure you've migrated all deprecated patterns:

### Configuration
- [ ] Created `.connections` file with database credentials
- [ ] Added `.connections` to `.gitignore`
- [ ] Removed global `$PICOORM_DSN`, `$PICOORM_USER`, `$PICOORM_PASS`, `$PICOORM_OPTIONS` declarations
- [ ] Tested database connectivity

### Code Changes
- [ ] Replaced `::$_lastInsertId` with `::getLastInsertId()`
- [ ] Added try-catch blocks around database operations
- [ ] Updated any models that need specific connections with `const CONNECTION`

### Testing
- [ ] Verified all CRUD operations work correctly
- [ ] Tested error handling with invalid data
- [ ] Confirmed multiple connections work (if applicable)

---

## Compatibility Notes

### Backward Compatibility

Version 2.0 maintains backward compatibility for the deprecated features:

| Deprecated Feature | Still Works? | Recommended Alternative |
|-------------------|--------------|------------------------|
| Global variables | Yes | `.connections` file or `addConnection()` |
| `::$_lastInsertId` | Yes | `::getLastInsertId()` |

### When Will Deprecated Features Be Removed?

Deprecated features will continue to work in the 2.x release series. They may be removed in version 3.0. We recommend migrating as soon as practical to avoid issues in future upgrades.

### Getting Help

If you encounter issues during migration:

1. Check the [CHANGELOG.md](CHANGELOG.md) for detailed changes
2. Review the [README.md](README.md) for updated documentation
3. Open an issue on [GitHub](https://github.com/paigejulianne/picoorm/issues)

---

## Example: Complete Migration

Here's a complete before/after example of migrating a simple application:

### Before (v1.x style)

```php
<?php
// bootstrap.php
require_once 'vendor/autoload.php';

global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS;
$PICOORM_DSN = 'mysql:host=localhost;dbname=myapp';
$PICOORM_USER = 'root';
$PICOORM_PASS = 'secret';

// models/User.php
use PaigeJulianne\PicoORM;
class User extends PicoORM {}

// app.php
require_once 'bootstrap.php';

$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$user->save();

echo "Created user ID: " . User::$_lastInsertId;
```

### After (v2.x style)

```ini
# .connections
[default]
DSN=mysql:host=localhost;dbname=myapp
USER=root
PASS=secret
```

```php
<?php
// bootstrap.php
require_once 'vendor/autoload.php';
// No global variables needed!

// models/User.php
use PaigeJulianne\PicoORM;
class User extends PicoORM {}

// app.php
require_once 'bootstrap.php';

try {
    $user = new User();
    $user->name = 'Alice';
    $user->email = 'alice@example.com';
    $user->save();

    echo "Created user ID: " . User::getLastInsertId();
} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage();
}
```

```gitignore
# .gitignore
.connections
```

---

*Last updated: December 2024 for PicoORM v2.0.0*
