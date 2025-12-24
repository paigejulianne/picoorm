# Changelog

All notable changes to PicoORM will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2024-12-24

### Breaking Changes

This is a major release with significant new features. While backward compatibility has been maintained for existing code, some behavioral changes may affect your application:

- SQL identifiers (column names, property names) are now validated and will throw `\InvalidArgumentException` if they contain invalid characters
- SQL operators in `getAllObjects()` filters are now validated against a whitelist
- PDO exceptions are now enabled by default

### Added

#### Multiple Database Connections

PicoORM now supports multiple named database connections via a `.connections` configuration file:

- **`.connections` file support**: INI-style configuration file for defining multiple database connections
- **`setConnectionsFile()`**: Static method to specify a custom path to the connections file
- **`loadConnections()`**: Static method to manually trigger loading of connection configuration
- **`addConnection()`**: Static method to programmatically add connection configurations at runtime
- **`getConnectionNames()`**: Static method to retrieve all configured connection names
- **`hasConnection()`**: Static method to check if a named connection exists
- **`getConnection()`**: Instance method to get the connection name used by an object
- **`CONNECTION` constant**: Override in child classes to specify which connection a model uses

#### Security Enhancements

- **SQL Injection Protection**: All identifier parameters (column names, property names, operators) are now validated against whitelist patterns
- **`validateIdentifier()`**: Internal method to validate SQL identifiers
- **`validateOperator()`**: Internal method to validate SQL operators (=, !=, <>, <, >, <=, >=, LIKE, NOT LIKE, IN, NOT IN, IS, IS NOT)
- **PDO Exception Mode**: PDO is now configured with `ERRMODE_EXCEPTION` by default

#### Other Additions

- **`getLastInsertId()`**: Static method as a safer alternative to accessing `$_lastInsertId` directly
- **`__isset()`**: Magic method to properly support `isset()` checks on object properties
- **`.connections.example`**: Sample configuration file included in the distribution

### Fixed

- **Bug**: Undefined variable `$dataArray` in `getAllObjects()` - now properly initialized
- **Bug**: `getAllObjects()` with `$forceArray=true` was incorrectly including `$idColumn` in the returned array
- **Bug**: Missing null check in `__get()` could cause "Undefined array key" warnings in PHP 8.0+
- **Bug**: Constructor did not initialize `$_id` and `$_id_column` when record was not found
- Loose type comparisons (`==`) replaced with strict comparisons (`===`) throughout
- Removed `@` error suppression operators for better debugging
- INSERT queries now use cross-database compatible syntax: `INSERT INTO table (col) VALUES (?)` instead of MySQL-specific `INSERT INTO table SET col = ?`

### Changed

- Configuration now preferentially uses `.connections` file, with fallback to legacy global variables
- `$_lastInsertId` is now initialized to `0` (was uninitialized)
- `getAllObjects()` return type updated to `array|static` to accurately reflect behavior
- Internal methods now use nullable type hints (`?string`) instead of `NULL` default
- Improved docblock documentation with `@throws` annotations
- Column names in generated SQL are now wrapped in backticks for consistency
- Code reorganized with section comments for better readability

### Deprecated

- **Global variables** (`$PICOORM_DSN`, `$PICOORM_USER`, `$PICOORM_PASS`, `$PICOORM_OPTIONS`): Use `.connections` file or `addConnection()` instead
- **Direct access to `$_lastInsertId`**: Use `getLastInsertId()` instead

### Migration Guide

#### From 1.x to 2.0

1. **Create a `.connections` file** (recommended):
   ```ini
   [default]
   DSN=mysql:host=localhost;dbname=myapp
   USER=myuser
   PASS=mypassword
   ```

2. **Or continue using global variables** (deprecated but still supported):
   ```php
   global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS;
   $PICOORM_DSN = 'mysql:host=localhost;dbname=myapp';
   $PICOORM_USER = 'myuser';
   $PICOORM_PASS = 'mypassword';
   ```

3. **For multiple connections**, define them in `.connections`:
   ```ini
   [default]
   DSN=mysql:host=localhost;dbname=myapp
   USER=myuser
   PASS=mypassword

   [analytics]
   DSN=mysql:host=analytics.example.com;dbname=analytics
   USER=analytics_user
   PASS=analytics_pass
   ```

4. **Specify connection per model** using the `CONNECTION` constant:
   ```php
   class AnalyticsEvent extends PicoORM {
       const CONNECTION = 'analytics';
   }
   ```

## [1.8.4] - Previous Release

See git history for changes prior to this changelog.
