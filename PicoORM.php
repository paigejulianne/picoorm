<?php

namespace PaigeJulianne;

/**
 * Package PicoORM
 *
 * A lightweight ORM for PHP 8.0+ with support for multiple database connections.
 *
 * @author    Paige Julianne Sullivan <paige@paigejulianne.com> https://paigejulianne.com
 * @copyright 2008-present Paige Julianne Sullivan
 * @license   GPL-3.0-or-later
 * @link      https://github.com/paigejulianne/picoorm
 * @version   2.1.0
 */
class PicoORM
{
    /**
     * @var string Regex pattern for validating SQL identifiers (column/table names)
     */
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * @var string Regex pattern for validating SQL operators
     */
    private const OPERATOR_PATTERN = '/^(=|!=|<>|<|>|<=|>=|LIKE|NOT LIKE|IN|NOT IN|IS|IS NOT)$/i';

    /**
     * @var string Default connections file path
     */
    private static string $connectionsFile = '.connections';

    /**
     * @var array Parsed database connections from .connections file
     */
    private static array $connections = [];

    /**
     * @var bool Whether connections have been loaded
     */
    private static bool $connectionsLoaded = false;

    /**
     * @var string The active connection name for this instance
     */
    protected string $_connection = 'default';

    /**
     * @var string ID of row in database
     */
    private string $_id;

    /**
     * @var array Holds the columns from the database table
     */
    private array $properties = [];

    /**
     * @var array List of columns that were modified and need to be updated
     */
    private array $_taintedItems = [];

    /**
     * @var array Original property values when loaded (for dirty checking)
     */
    private array $_originalProperties = [];

    /**
     * @var array<string, \PDO> Cached PDO connections for transactions
     */
    private static array $_pdoCache = [];

    /**
     * @var string Name of ID column in database (usually 'id', but not always)
     */
    private string $_id_column;

    /**
     * @var int Last inserted ID (kept public for backward compatibility)
     * @deprecated Use getLastInsertId() instead
     */
    public static int $_lastInsertId = 0;

    /**
     * Override this constant in child classes to specify a different table name
     */
    const TABLE_OVERRIDE = '';

    /**
     * Override this constant in child classes to specify which connection to use
     */
    const CONNECTION = 'default';

    /**
     * Override this constant in child classes to disable type validation
     */
    const VALIDATE_TYPES = true;

    /**
     * @var array<string, array> Cached table schemas indexed by table name
     */
    private static array $_schemaCache = [];

    /**
     * @var array Map of database types to PHP validation types
     */
    private const TYPE_MAP = [
        // Integer types
        'int' => 'integer',
        'integer' => 'integer',
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'mediumint' => 'integer',
        'bigint' => 'integer',
        'serial' => 'integer',
        'bigserial' => 'integer',
        'smallserial' => 'integer',
        // Float types
        'float' => 'float',
        'double' => 'float',
        'decimal' => 'float',
        'numeric' => 'float',
        'real' => 'float',
        'money' => 'float',
        // String types
        'char' => 'string',
        'varchar' => 'string',
        'text' => 'string',
        'tinytext' => 'string',
        'mediumtext' => 'string',
        'longtext' => 'string',
        'enum' => 'string',
        'set' => 'string',
        'uuid' => 'string',
        'character varying' => 'string',
        'character' => 'string',
        // Boolean types
        'bool' => 'boolean',
        'boolean' => 'boolean',
        // Date/Time types (stored as strings)
        'date' => 'string',
        'datetime' => 'string',
        'timestamp' => 'string',
        'time' => 'string',
        'year' => 'string',
        'interval' => 'string',
        // Binary types
        'blob' => 'string',
        'tinyblob' => 'string',
        'mediumblob' => 'string',
        'longblob' => 'string',
        'binary' => 'string',
        'varbinary' => 'string',
        'bytea' => 'string',
        // JSON types
        'json' => 'string',
        'jsonb' => 'string',
    ];

    // =========================================================================
    // Connection Management
    // =========================================================================

    /**
     * Set the path to the connections file
     *
     * @param string $path Absolute or relative path to .connections file
     * @return void
     */
    public static function setConnectionsFile(string $path): void
    {
        self::$connectionsFile = $path;
        self::$connectionsLoaded = false;
        self::$connections = [];
    }

    /**
     * Load and parse the connections file
     *
     * The file format is INI-like with sections for each connection:
     *
     * [default]
     * DSN=mysql:host=localhost;dbname=mydb
     * USER=username
     * PASS=password
     *
     * [analytics]
     * DSN=mysql:host=analytics.example.com;dbname=analytics
     * USER=analytics_user
     * PASS=analytics_pass
     *
     * @return void
     * @throws \RuntimeException If the file cannot be read or parsed
     */
    public static function loadConnections(): void
    {
        if (self::$connectionsLoaded) {
            return;
        }

        // Check multiple possible locations for the connections file
        $possiblePaths = [
            self::$connectionsFile,
            dirname(__DIR__) . '/' . self::$connectionsFile,
            getcwd() . '/' . self::$connectionsFile,
        ];

        $filePath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $filePath = $path;
                break;
            }
        }

        if ($filePath === null) {
            // No connections file found - will fall back to legacy global variables
            self::$connectionsLoaded = true;
            return;
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read connections file: $filePath");
        }

        self::parseConnectionsFile($contents);
        self::$connectionsLoaded = true;
    }

    /**
     * Parse the contents of a connections file
     *
     * @param string $contents The file contents to parse
     * @return void
     */
    private static function parseConnectionsFile(string $contents): void
    {
        $lines = explode("\n", $contents);
        $currentSection = null;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            // Check for section header [connection_name]
            if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $line, $matches)) {
                $currentSection = $matches[1];
                if (!isset(self::$connections[$currentSection])) {
                    self::$connections[$currentSection] = [
                        'DSN' => '',
                        'USER' => '',
                        'PASS' => '',
                        'OPTIONS' => [],
                    ];
                }
                continue;
            }

            // Parse key=value pairs
            if ($currentSection !== null && str_contains($line, '=')) {
                $equalPos = strpos($line, '=');
                $key = trim(substr($line, 0, $equalPos));
                $value = trim(substr($line, $equalPos + 1));

                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                // Handle OPTIONS array syntax: OPTIONS[KEY]=value
                if (preg_match('/^OPTIONS\[(.+)\]$/', $key, $optMatch)) {
                    $optionKey = $optMatch[1];
                    // Convert PDO constant names to values
                    if (defined($optionKey)) {
                        $optionKey = constant($optionKey);
                    }
                    if (defined($value)) {
                        $value = constant($value);
                    }
                    self::$connections[$currentSection]['OPTIONS'][$optionKey] = $value;
                } else {
                    self::$connections[$currentSection][$key] = $value;
                }
            }
        }
    }

    /**
     * Manually add or update a connection configuration
     *
     * @param string $name    Connection name
     * @param string $dsn     PDO DSN string
     * @param string $user    Database username
     * @param string $pass    Database password
     * @param array  $options PDO options array
     * @return void
     */
    public static function addConnection(
        string $name,
        string $dsn,
        string $user = '',
        string $pass = '',
        array $options = []
    ): void {
        self::$connections[$name] = [
            'DSN' => $dsn,
            'USER' => $user,
            'PASS' => $pass,
            'OPTIONS' => $options,
        ];
        self::$connectionsLoaded = true;
    }

    /**
     * Get all configured connection names
     *
     * @return array List of connection names
     */
    public static function getConnectionNames(): array
    {
        self::loadConnections();
        return array_keys(self::$connections);
    }

    /**
     * Check if a named connection exists
     *
     * @param string $name Connection name
     * @return bool
     */
    public static function hasConnection(string $name): bool
    {
        self::loadConnections();
        return isset(self::$connections[$name]);
    }

    /**
     * Get the connection configuration for this class
     *
     * @param string|null $connectionName Override connection name (uses class CONNECTION constant if null)
     * @return array Connection configuration array with DSN, USER, PASS, OPTIONS
     * @throws \RuntimeException If the connection is not configured
     */
    protected static function getConnectionConfig(?string $connectionName = null): array
    {
        self::loadConnections();

        $name = $connectionName ?? static::CONNECTION;

        // Check if we have this connection configured
        if (isset(self::$connections[$name])) {
            return self::$connections[$name];
        }

        // Fall back to legacy global variables for 'default' connection
        if ($name === 'default') {
            global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS, $PICOORM_OPTIONS;

            if (!empty($PICOORM_DSN)) {
                return [
                    'DSN' => $PICOORM_DSN,
                    'USER' => $PICOORM_USER ?? '',
                    'PASS' => $PICOORM_PASS ?? '',
                    'OPTIONS' => $PICOORM_OPTIONS ?? [],
                ];
            }
        }

        throw new \RuntimeException(
            "Database connection '$name' is not configured. " .
            "Please add it to your .connections file or use PicoORM::addConnection()."
        );
    }

    /**
     * Get the last insert ID in a safe manner
     *
     * @return int
     */
    public static function getLastInsertId(): int
    {
        return self::$_lastInsertId;
    }

    // =========================================================================
    // Validation Methods
    // =========================================================================

    /**
     * Validates a SQL identifier (column name, table name) to prevent SQL injection
     *
     * @param string $identifier The identifier to validate
     * @param string $type Description of what's being validated (for error messages)
     * @return string The validated identifier
     * @throws \InvalidArgumentException If the identifier is invalid
     */
    private static function validateIdentifier(string $identifier, string $type = 'identifier'): string
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid $type: '$identifier'. Only alphanumeric characters and underscores are allowed, " .
                "and it must start with a letter or underscore."
            );
        }
        return $identifier;
    }

    /**
     * Validates a SQL operator to prevent SQL injection
     *
     * @param string $operator The operator to validate
     * @return string The validated operator
     * @throws \InvalidArgumentException If the operator is invalid
     */
    private static function validateOperator(string $operator): string
    {
        if (!preg_match(self::OPERATOR_PATTERN, $operator)) {
            throw new \InvalidArgumentException(
                "Invalid SQL operator: '$operator'. Allowed operators: =, !=, <>, <, >, <=, >=, " .
                "LIKE, NOT LIKE, IN, NOT IN, IS, IS NOT."
            );
        }
        return strtoupper($operator);
    }

    // =========================================================================
    // Schema Validation Methods
    // =========================================================================

    /**
     * Get the table name for this model
     *
     * @return string The table name
     */
    protected static function getTableName(): string
    {
        if (static::TABLE_OVERRIDE !== null && static::TABLE_OVERRIDE !== '') {
            return static::TABLE_OVERRIDE;
        }

        $table = strtolower(static::class);
        if (str_contains($table, '\\')) {
            $parts = explode('\\', $table, 2);
            $table = $parts[0] . '.' . ($parts[1] ?? $parts[0]);
        }

        return $table;
    }

    /**
     * Get the database driver type from the DSN
     *
     * @param string|null $connectionName Connection name (null for class default)
     * @return string The driver type (mysql, pgsql, sqlite, etc.)
     */
    protected static function getDatabaseDriver(?string $connectionName = null): string
    {
        $config = static::getConnectionConfig($connectionName);
        $dsn = $config['DSN'] ?? '';

        if (preg_match('/^([a-z]+):/', $dsn, $matches)) {
            return strtolower($matches[1]);
        }

        return 'unknown';
    }

    /**
     * Get the table schema (column definitions)
     *
     * Returns an associative array of column name => column info.
     * Column info includes: type, nullable, default, primary_key, max_length
     *
     * @param string|null $connectionName Connection name (null for class default)
     * @return array<string, array> Column definitions
     */
    public static function getTableSchema(?string $connectionName = null): array
    {
        $table = static::getTableName();
        $cacheKey = ($connectionName ?? static::CONNECTION) . '.' . $table;

        if (isset(self::$_schemaCache[$cacheKey])) {
            return self::$_schemaCache[$cacheKey];
        }

        $driver = static::getDatabaseDriver($connectionName);
        $schema = [];

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $schema = static::getMySQLSchema($table, $connectionName);
                break;
            case 'pgsql':
                $schema = static::getPostgreSQLSchema($table, $connectionName);
                break;
            case 'sqlite':
                $schema = static::getSQLiteSchema($table, $connectionName);
                break;
            default:
                // For unknown drivers, return empty schema (no validation)
                $schema = [];
        }

        self::$_schemaCache[$cacheKey] = $schema;
        return $schema;
    }

    /**
     * Get MySQL/MariaDB table schema
     *
     * @param string      $table          Table name
     * @param string|null $connectionName Connection name
     * @return array Column definitions
     */
    private static function getMySQLSchema(string $table, ?string $connectionName = null): array
    {
        $schema = [];

        // Handle schema.table format
        $tableParts = explode('.', $table);
        $tableName = end($tableParts);

        $results = static::_fetchAll(
            "SHOW COLUMNS FROM `$tableName`",
            [],
            $table
        );

        foreach ($results as $row) {
            $type = strtolower($row['Type'] ?? '');
            $baseType = preg_replace('/\(.*\)/', '', $type);
            $baseType = preg_replace('/\s+unsigned/', '', $baseType);
            $baseType = trim($baseType);

            // Extract max length for string types
            $maxLength = null;
            if (preg_match('/\((\d+)\)/', $type, $matches)) {
                $maxLength = (int)$matches[1];
            }

            $schema[$row['Field']] = [
                'type' => $baseType,
                'php_type' => self::TYPE_MAP[$baseType] ?? 'string',
                'nullable' => ($row['Null'] ?? 'NO') === 'YES',
                'default' => $row['Default'] ?? null,
                'primary_key' => ($row['Key'] ?? '') === 'PRI',
                'max_length' => $maxLength,
                'raw_type' => $type,
            ];
        }

        return $schema;
    }

    /**
     * Get PostgreSQL table schema
     *
     * @param string      $table          Table name
     * @param string|null $connectionName Connection name
     * @return array Column definitions
     */
    private static function getPostgreSQLSchema(string $table, ?string $connectionName = null): array
    {
        $schema = [];

        // Handle schema.table format
        $schemaName = 'public';
        $tableName = $table;
        if (str_contains($table, '.')) {
            [$schemaName, $tableName] = explode('.', $table, 2);
        }

        $sql = "
            SELECT
                c.column_name,
                c.data_type,
                c.is_nullable,
                c.column_default,
                c.character_maximum_length,
                CASE WHEN pk.column_name IS NOT NULL THEN true ELSE false END as is_primary
            FROM information_schema.columns c
            LEFT JOIN (
                SELECT ku.column_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage ku
                    ON tc.constraint_name = ku.constraint_name
                WHERE tc.constraint_type = 'PRIMARY KEY'
                    AND tc.table_schema = ?
                    AND tc.table_name = ?
            ) pk ON c.column_name = pk.column_name
            WHERE c.table_schema = ?
            AND c.table_name = ?
        ";

        $pdo = static::getPdo($connectionName);
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$schemaName, $tableName, $schemaName, $tableName]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $type = strtolower($row['data_type'] ?? '');

            $schema[$row['column_name']] = [
                'type' => $type,
                'php_type' => self::TYPE_MAP[$type] ?? 'string',
                'nullable' => ($row['is_nullable'] ?? 'NO') === 'YES',
                'default' => $row['column_default'] ?? null,
                'primary_key' => (bool)$row['is_primary'],
                'max_length' => $row['character_maximum_length'] ? (int)$row['character_maximum_length'] : null,
                'raw_type' => $type,
            ];
        }

        return $schema;
    }

    /**
     * Get SQLite table schema
     *
     * @param string      $table          Table name
     * @param string|null $connectionName Connection name
     * @return array Column definitions
     */
    private static function getSQLiteSchema(string $table, ?string $connectionName = null): array
    {
        $schema = [];

        // Handle schema.table format for SQLite
        $tableParts = explode('.', $table);
        $tableName = end($tableParts);

        $pdo = static::getPdo($connectionName);
        $stmt = $pdo->prepare("PRAGMA table_info(`$tableName`)");
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $type = strtolower($row['type'] ?? 'text');
            $baseType = preg_replace('/\(.*\)/', '', $type);
            $baseType = trim($baseType);

            // SQLite type affinity mapping
            $phpType = 'string';
            if (in_array($baseType, ['integer', 'int', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
                $phpType = 'integer';
            } elseif (in_array($baseType, ['real', 'double', 'float', 'numeric', 'decimal'])) {
                $phpType = 'float';
            } elseif ($baseType === 'boolean' || $baseType === 'bool') {
                $phpType = 'boolean';
            }

            // Extract max length
            $maxLength = null;
            if (preg_match('/\((\d+)\)/', $type, $matches)) {
                $maxLength = (int)$matches[1];
            }

            $schema[$row['name']] = [
                'type' => $baseType ?: 'text',
                'php_type' => $phpType,
                'nullable' => ((int)($row['notnull'] ?? 0)) === 0,
                'default' => $row['dflt_value'] ?? null,
                'primary_key' => ((int)($row['pk'] ?? 0)) === 1,
                'max_length' => $maxLength,
                'raw_type' => $type,
            ];
        }

        return $schema;
    }

    /**
     * Validate a value against the column's expected type
     *
     * @param string $column The column name
     * @param mixed  $value  The value to validate
     * @param bool   $throw  Whether to throw exception on failure (default: true)
     *
     * @return bool True if valid, false otherwise
     * @throws \TypeError If value type doesn't match column type and $throw is true
     */
    public function validateColumnValue(string $column, mixed $value, bool $throw = true): bool
    {
        // Skip validation if disabled for this class
        if (!static::VALIDATE_TYPES) {
            return true;
        }

        // Skip validation for internal properties
        if (str_starts_with($column, '_')) {
            return true;
        }

        // Null values are allowed for nullable columns or always allowed if no schema
        $schema = static::getTableSchema();

        // If we can't get schema info, skip validation
        if (empty($schema) || !isset($schema[$column])) {
            return true;
        }

        $columnInfo = $schema[$column];

        // Allow null for nullable columns
        if ($value === null) {
            if ($columnInfo['nullable']) {
                return true;
            }
            if ($throw) {
                throw new \TypeError(
                    "Column '$column' does not allow NULL values"
                );
            }
            return false;
        }

        $expectedType = $columnInfo['php_type'];
        $actualType = gettype($value);

        // Type coercion rules
        $valid = match ($expectedType) {
            'integer' => is_int($value) || is_bool($value) || (is_string($value) && ctype_digit(ltrim($value, '-'))),
            'float' => is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)),
            'string' => is_string($value) || is_int($value) || is_float($value) || is_bool($value),
            'boolean' => is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1',
            default => true,
        };

        // Check max length for strings
        if ($valid && $expectedType === 'string' && $columnInfo['max_length'] !== null) {
            $stringValue = is_string($value) ? $value : (string)$value;
            if (strlen($stringValue) > $columnInfo['max_length']) {
                if ($throw) {
                    throw new \TypeError(
                        "Value for column '$column' exceeds maximum length of {$columnInfo['max_length']} characters (got " . strlen($stringValue) . ")"
                    );
                }
                return false;
            }
        }

        if (!$valid && $throw) {
            throw new \TypeError(
                "Invalid type for column '$column': expected {$expectedType}, got {$actualType}. " .
                "Database column type is '{$columnInfo['raw_type']}'"
            );
        }

        return $valid;
    }

    /**
     * Validate all pending changes before saving
     *
     * @return bool True if all values are valid
     * @throws \TypeError If any value type doesn't match its column type
     */
    public function validateAllChanges(): bool
    {
        if (!static::VALIDATE_TYPES) {
            return true;
        }

        foreach ($this->_taintedItems as $column => $_) {
            if (isset($this->properties[$column])) {
                $this->validateColumnValue($column, $this->properties[$column]);
            }
        }

        return true;
    }

    /**
     * Clear the schema cache
     *
     * Useful for testing or when schema changes during runtime.
     *
     * @param string|null $table Specific table to clear, or null for all
     * @return void
     */
    public static function clearSchemaCache(?string $table = null): void
    {
        if ($table !== null) {
            foreach (self::$_schemaCache as $key => $value) {
                if (str_ends_with($key, '.' . $table)) {
                    unset(self::$_schemaCache[$key]);
                }
            }
        } else {
            self::$_schemaCache = [];
        }
    }

    // =========================================================================
    // Constructor and Core Methods
    // =========================================================================

    /**
     * Create a new PicoORM instance
     *
     * @param string|int|bool $id_value  The ID value to load, or false to create a new record
     * @param string          $id_column The name of the ID column (default: 'id')
     */
    public function __construct(string|int|bool $id_value = false, string $id_column = 'id')
    {
        $id_column = self::validateIdentifier($id_column, 'column name');
        $this->_connection = static::CONNECTION;

        if (!$id_value) {
            $this->_id = '-1';
            $this->_id_column = $id_column;
        } else {
            $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $id_column . '` = ?', [$id_value]);
            if ($result) {
                $this->_id = (string)$id_value;
                $this->_id_column = $id_column;
                $this->properties = $result;
                $this->_originalProperties = $result;
            } else {
                $this->_id = '-1';
                $this->_id_column = $id_column;
            }
        }
    }

    /**
     * Refreshes the properties of the current object from the database
     *
     * @return void
     */
    public function refreshProperties(): void
    {
        $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $this->_id_column . '` = ?', [$this->_id]);
        if ($result) {
            $this->properties = $result;
            $this->_originalProperties = $result;
            $this->_taintedItems = [];
        }
    }

    /**
     * Check if a record exists in the database
     *
     * @param int|string $id_value  The value of the id to check
     * @param string     $id_column The name of the id column (default: 'id')
     * @return bool True if the record exists, false otherwise
     * @throws \InvalidArgumentException If the column name is invalid
     */
    public static function exists(int|string $id_value, string $id_column = 'id'): bool
    {
        $id_column = self::validateIdentifier($id_column, 'column name');
        $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $id_column . '` = ?', [$id_value]);
        return !empty($result);
    }

    /**
     * Get the current record's ID
     *
     * @return string|int
     */
    public function getId(): string|int
    {
        return $this->_id;
    }

    /**
     * Get the connection name used by this instance
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->_connection;
    }

    /**
     * Destructor - automatically saves changes when object is destroyed
     */
    public function __destruct()
    {
        $this->writeChanges();
    }

    /**
     * Save changes to the database (alias for writeChanges)
     *
     * @return void
     */
    public function save(): void
    {
        $this->writeChanges();
    }

    /**
     * Write all pending property changes to the database
     *
     * @return void
     * @throws \InvalidArgumentException If a property name is invalid
     */
    public function writeChanges(): void
    {
        // Validate all changes before saving
        $this->validateAllChanges();

        $parts = [];
        $columns = [];
        $values = [];

        foreach ($this->_taintedItems as $propname => $_) {
            if ($propname[0] === '_') {
                continue;
            }
            // Validate property name to prevent SQL injection
            self::validateIdentifier($propname, 'property name');
            $columns[] = '`' . $propname . '`';
            $parts[] = '`' . $propname . '` = ?';
            $values[] = $this->properties[$propname];
        }

        if (!empty($parts) && !empty($values)) {
            if ($this->_id === '-1') {
                // Use cross-database compatible INSERT syntax
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $sql = 'INSERT INTO _DB_ (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
                self::_doQuery($sql, $values);
                $this->_id = (string)self::$_lastInsertId;
            } else {
                $values[] = $this->_id;
                $sql = 'UPDATE _DB_ SET ' . implode(', ', $parts) . ' WHERE `' . $this->_id_column . '` = ?';
                self::_doQuery($sql, $values);
            }
        }
        $this->_taintedItems = [];
    }

    // =========================================================================
    // Property Access (Magic Methods)
    // =========================================================================

    /**
     * Get a property value
     *
     * @param string $prop Property name
     * @return string|array|int|float|bool|null Property value or null if not set
     */
    public function __get(string $prop): string|array|int|float|bool|null
    {
        return $this->properties[$prop] ?? null;
    }

    /**
     * Check if a property is set
     *
     * @param string $prop Property name
     * @return bool True if the property exists and is not null
     */
    public function __isset(string $prop): bool
    {
        return isset($this->properties[$prop]);
    }

    /**
     * Set a property value
     *
     * @param string                           $prop  Property name
     * @param string|array|int|float|bool|null $value Property value
     * @return void
     */
    public function __set(string $prop, string|array|int|float|bool|null $value): void
    {
        // Validate type before setting (if validation is enabled)
        if ($prop[0] !== '_' && static::VALIDATE_TYPES) {
            $this->validateColumnValue($prop, $value);
        }

        if ($prop[0] !== '_') {
            $this->_taintedItems[$prop] = $prop;
        }
        $this->properties[$prop] = $value;
    }

    /**
     * Set multiple properties at once from an array
     *
     * @param array $array Associative array of property => value pairs
     * @return void
     */
    public function setMulti(array $array): void
    {
        foreach ($array as $prop => $value) {
            $this->__set($prop, $value);
        }
    }

    // =========================================================================
    // Record Operations
    // =========================================================================

    /**
     * Delete the current record from the database
     *
     * WARNING: This action is irreversible!
     *
     * @return void
     */
    public function delete(): void
    {
        self::_doQuery('DELETE FROM _DB_ WHERE `' . $this->_id_column . '` = ?', [$this->_id]);
    }

    /**
     * Retrieve multiple records from the database based on filter criteria
     *
     * Note: For backward compatibility, returns a single object when only one result
     * is found and $forceArray is false. Use $forceArray = true to always get an array.
     *
     * @param string      $idColumn   The column to use as the primary key
     * @param array       $filters    Array of filters: [[column, null, operator, value], ...]
     * @param string      $filterGlue Join statement for filters ('AND' or 'OR')
     * @param bool        $forceArray Force array return even for single results
     * @param int|null    $limit      Maximum number of records to return
     * @param int         $offset     Number of records to skip (requires limit)
     * @param string|null $orderBy    Column to order by
     * @param string      $orderDir   Order direction ('ASC' or 'DESC')
     *
     * @return array|static Array of objects, single object, or empty array
     * @throws \InvalidArgumentException If column names or operators are invalid
     */
    public static function getAllObjects(
        string $idColumn = 'id',
        array $filters = [],
        string $filterGlue = 'AND',
        bool $forceArray = false,
        ?int $limit = null,
        int $offset = 0,
        ?string $orderBy = null,
        string $orderDir = 'ASC'
    ): array|static {
        // Validate the ID column
        $idColumn = self::validateIdentifier($idColumn, 'column name');

        // Validate filter glue
        $filterGlue = strtoupper(trim($filterGlue));
        if ($filterGlue !== 'AND' && $filterGlue !== 'OR') {
            throw new \InvalidArgumentException("Invalid filter glue: '$filterGlue'. Must be 'AND' or 'OR'.");
        }

        // Validate order direction
        $orderDir = strtoupper(trim($orderDir));
        if ($orderDir !== 'ASC' && $orderDir !== 'DESC') {
            throw new \InvalidArgumentException("Invalid order direction: '$orderDir'. Must be 'ASC' or 'DESC'.");
        }

        $filterArray = [];
        $dataArray = [];
        $returnArray = [];
        $_class = get_called_class();

        // Build the filter string for PDO
        foreach ($filters as $filter) {
            if (isset($filter[2])) {
                // Validate column name and operator
                $column = self::validateIdentifier($filter[0], 'filter column');
                $operator = self::validateOperator($filter[2]);
                $filterArray[] = '`' . $column . '` ' . $operator . ' ?';
                $dataArray[] = $filter[3] ?? null;
            }
        }

        $filterString = '';
        if (!empty($filterArray)) {
            $filterString = ' WHERE ' . implode(' ' . $filterGlue . ' ', $filterArray);
        }

        $sql = 'SELECT `' . $idColumn . '` FROM _DB_' . $filterString;

        // Add ORDER BY clause
        if ($orderBy !== null) {
            $orderBy = self::validateIdentifier($orderBy, 'order by column');
            $sql .= ' ORDER BY `' . $orderBy . '` ' . $orderDir;
        }

        // Add LIMIT and OFFSET clause
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int)$limit;
            if ($offset > 0) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }

        if (!empty($dataArray)) {
            $result = self::_fetchAll($sql, $dataArray);
        } else {
            $result = self::_fetchAll($sql);
        }

        if (empty($result)) {
            return [];
        }

        if (count($result) === 1 && !$forceArray) {
            return new $_class($result[0][$idColumn], $idColumn);
        }

        foreach ($result as $table_row) {
            $returnArray[$table_row[$idColumn]] = new $_class($table_row[$idColumn], $idColumn);
        }

        return $returnArray;
    }

    // =========================================================================
    // Database Query Methods
    // =========================================================================

    /**
     * Fetch all matching records from the database
     *
     * @param string      $sql        PDO-ready SQL statement (use _DB_ for table name)
     * @param array       $valueArray Values for PDO parameter substitution
     * @param string|null $table      Optional table name override
     *
     * @return array Array of associative arrays containing the results
     * @throws \PDOException If the query fails
     */
    public static function _fetchAll(string $sql, array $valueArray = [], ?string $table = null): array
    {
        $statement = self::_doQuery($sql, $valueArray, $table);
        if ($statement->rowCount() > 0) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        }
        return [];
    }

    /**
     * Fetch the first matching record from the database
     *
     * @param string      $sql        PDO-ready SQL statement (use _DB_ for table name)
     * @param array       $valueArray Values for PDO parameter substitution
     * @param string|null $table      Optional table name override
     *
     * @return array Associative array containing the result, or empty array
     * @throws \PDOException If the query fails
     */
    public static function _fetch(string $sql, array $valueArray = [], ?string $table = null): array
    {
        $statement = self::_doQuery($sql, $valueArray, $table);
        if ($statement->rowCount() > 0) {
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
            return $result !== false ? $result : [];
        }
        return [];
    }

    /**
     * Execute a SQL statement and return the PDO statement
     *
     * @param string      $sql        PDO-ready SQL statement (use _DB_ for table name)
     * @param array       $valueArray Values for PDO parameter substitution
     * @param string|null $table      Optional table name override
     *
     * @return \PDOStatement
     * @throws \PDOException If the database connection or query fails
     * @throws \RuntimeException If the prepared statement fails
     */
    public static function _doQuery(string $sql, array $valueArray = [], ?string $table = null): \PDOStatement
    {
        // Get connection configuration
        $config = static::getConnectionConfig();

        // Ensure PDO throws exceptions on errors
        $options = $config['OPTIONS'] ?? [];
        if (!isset($options[\PDO::ATTR_ERRMODE])) {
            $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        }

        $conn = new \PDO($config['DSN'], $config['USER'], $config['PASS'], $options);

        // Determine table name
        if ($table === null) {
            $table = strtolower(static::class);
            if (str_contains($table, '\\')) {
                $parts = explode('\\', $table, 2);
                $table = $parts[0] . '.' . ($parts[1] ?? $parts[0]);
            }
        }

        if (static::TABLE_OVERRIDE !== null && static::TABLE_OVERRIDE !== '') {
            $table = static::TABLE_OVERRIDE;
        }

        $sql = str_replace(['_DB_', '_db_'], $table, $sql);

        $statement = $conn->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('Failed to prepare SQL statement: ' . $sql);
        }

        if (!empty($valueArray)) {
            if (!is_array($valueArray)) {
                $valueArray = [$valueArray];
            }
            $statement->execute($valueArray);
        } else {
            $statement->execute();
        }

        self::$_lastInsertId = (int)$conn->lastInsertId();
        return $statement;
    }

    // =========================================================================
    // Counting and Aggregation Methods
    // =========================================================================

    /**
     * Count records matching the given filters
     *
     * @param array  $filters    Array of filters: [[column, null, operator, value], ...]
     * @param string $filterGlue Join statement for filters ('AND' or 'OR')
     *
     * @return int Number of matching records
     * @throws \InvalidArgumentException If column names or operators are invalid
     */
    public static function count(array $filters = [], string $filterGlue = 'AND'): int
    {
        // Validate filter glue
        $filterGlue = strtoupper(trim($filterGlue));
        if ($filterGlue !== 'AND' && $filterGlue !== 'OR') {
            throw new \InvalidArgumentException("Invalid filter glue: '$filterGlue'. Must be 'AND' or 'OR'.");
        }

        $filterArray = [];
        $dataArray = [];

        foreach ($filters as $filter) {
            if (isset($filter[2])) {
                $column = self::validateIdentifier($filter[0], 'filter column');
                $operator = self::validateOperator($filter[2]);
                $filterArray[] = '`' . $column . '` ' . $operator . ' ?';
                $dataArray[] = $filter[3] ?? null;
            }
        }

        $filterString = '';
        if (!empty($filterArray)) {
            $filterString = ' WHERE ' . implode(' ' . $filterGlue . ' ', $filterArray);
        }

        $sql = 'SELECT COUNT(*) as cnt FROM _DB_' . $filterString;
        $result = self::_fetch($sql, $dataArray);

        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Get an array of values from a single column
     *
     * @param string $column     The column to pluck values from
     * @param array  $filters    Optional filters
     * @param string $filterGlue Join statement for filters ('AND' or 'OR')
     *
     * @return array Array of column values
     * @throws \InvalidArgumentException If column name is invalid
     */
    public static function pluck(string $column, array $filters = [], string $filterGlue = 'AND'): array
    {
        $column = self::validateIdentifier($column, 'column name');

        // Validate filter glue
        $filterGlue = strtoupper(trim($filterGlue));
        if ($filterGlue !== 'AND' && $filterGlue !== 'OR') {
            throw new \InvalidArgumentException("Invalid filter glue: '$filterGlue'. Must be 'AND' or 'OR'.");
        }

        $filterArray = [];
        $dataArray = [];

        foreach ($filters as $filter) {
            if (isset($filter[2])) {
                $filterColumn = self::validateIdentifier($filter[0], 'filter column');
                $operator = self::validateOperator($filter[2]);
                $filterArray[] = '`' . $filterColumn . '` ' . $operator . ' ?';
                $dataArray[] = $filter[3] ?? null;
            }
        }

        $filterString = '';
        if (!empty($filterArray)) {
            $filterString = ' WHERE ' . implode(' ' . $filterGlue . ' ', $filterArray);
        }

        $sql = 'SELECT `' . $column . '` FROM _DB_' . $filterString;
        $results = self::_fetchAll($sql, $dataArray);

        return array_column($results, $column);
    }

    // =========================================================================
    // Finder Methods
    // =========================================================================

    /**
     * Find all records matching a column value
     *
     * @param string     $column   The column to search by
     * @param mixed      $value    The value to match
     * @param string     $idColumn The ID column name
     * @param string     $operator The comparison operator (default: '=')
     *
     * @return array Array of model instances
     * @throws \InvalidArgumentException If column name or operator is invalid
     */
    public static function findBy(
        string $column,
        mixed $value,
        string $idColumn = 'id',
        string $operator = '='
    ): array {
        return static::getAllObjects($idColumn, [
            [$column, null, $operator, $value]
        ], 'AND', true);
    }

    /**
     * Find a single record matching a column value
     *
     * @param string $column   The column to search by
     * @param mixed  $value    The value to match
     * @param string $idColumn The ID column name
     *
     * @return static|null The model instance or null if not found
     * @throws \InvalidArgumentException If column name is invalid
     */
    public static function findOneBy(string $column, mixed $value, string $idColumn = 'id'): ?static
    {
        $column = self::validateIdentifier($column, 'column name');
        $idColumn = self::validateIdentifier($idColumn, 'id column');

        $result = self::_fetch(
            'SELECT `' . $idColumn . '` FROM _DB_ WHERE `' . $column . '` = ? LIMIT 1',
            [$value]
        );

        if (empty($result)) {
            return null;
        }

        return new static($result[$idColumn], $idColumn);
    }

    /**
     * Find a record or create it if it doesn't exist
     *
     * @param array  $attributes Attributes to search by
     * @param array  $values     Additional values to set when creating
     * @param string $idColumn   The ID column name
     *
     * @return static The found or created model instance
     * @throws \InvalidArgumentException If column names are invalid
     */
    public static function firstOrCreate(
        array $attributes,
        array $values = [],
        string $idColumn = 'id'
    ): static {
        // Build filters from attributes
        $filters = [];
        foreach ($attributes as $column => $value) {
            $filters[] = [$column, null, '=', $value];
        }

        // Try to find existing record
        $results = static::getAllObjects($idColumn, $filters, 'AND', true);

        if (!empty($results)) {
            return reset($results);
        }

        // Create new record
        $model = new static();
        $model->setMulti(array_merge($attributes, $values));
        $model->save();

        return $model;
    }

    /**
     * Find a record and update it, or create it if it doesn't exist
     *
     * @param array  $attributes Attributes to search by
     * @param array  $values     Values to update or set when creating
     * @param string $idColumn   The ID column name
     *
     * @return static The found/updated or created model instance
     * @throws \InvalidArgumentException If column names are invalid
     */
    public static function updateOrCreate(
        array $attributes,
        array $values = [],
        string $idColumn = 'id'
    ): static {
        // Build filters from attributes
        $filters = [];
        foreach ($attributes as $column => $value) {
            $filters[] = [$column, null, '=', $value];
        }

        // Try to find existing record
        $results = static::getAllObjects($idColumn, $filters, 'AND', true);

        if (!empty($results)) {
            $model = reset($results);
            $model->setMulti($values);
            $model->save();
            return $model;
        }

        // Create new record
        $model = new static();
        $model->setMulti(array_merge($attributes, $values));
        $model->save();

        return $model;
    }

    // =========================================================================
    // Data Export Methods
    // =========================================================================

    /**
     * Export the current record as an associative array
     *
     * @param array|null $columns Specific columns to include (null for all)
     *
     * @return array Associative array of property values
     */
    public function toArray(?array $columns = null): array
    {
        if ($columns === null) {
            return $this->properties;
        }

        $result = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $this->properties)) {
                $result[$column] = $this->properties[$column];
            }
        }

        return $result;
    }

    // =========================================================================
    // Dirty Checking Methods
    // =========================================================================

    /**
     * Check if the model has unsaved changes
     *
     * @param string|null $column Check specific column, or null for any column
     *
     * @return bool True if there are unsaved changes
     */
    public function isDirty(?string $column = null): bool
    {
        if ($column !== null) {
            return isset($this->_taintedItems[$column]);
        }

        return !empty($this->_taintedItems);
    }

    /**
     * Check if the model has no unsaved changes
     *
     * @return bool True if there are no unsaved changes
     */
    public function isClean(): bool
    {
        return empty($this->_taintedItems);
    }

    /**
     * Get the original value of a property (before modifications)
     *
     * @param string|null $column Specific column, or null for all original values
     *
     * @return mixed Original value(s)
     */
    public function getOriginal(?string $column = null): mixed
    {
        if ($column !== null) {
            return $this->_originalProperties[$column] ?? null;
        }

        return $this->_originalProperties;
    }

    /**
     * Get all changed properties and their new values
     *
     * @return array Associative array of changed column => new value
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->_taintedItems as $column => $_) {
            $dirty[$column] = $this->properties[$column] ?? null;
        }

        return $dirty;
    }

    /**
     * Return a fresh instance of the model from the database
     *
     * This does not modify the current instance.
     *
     * @return static|null A new instance with fresh data, or null if not found
     */
    public function fresh(): ?static
    {
        if ($this->_id === '-1') {
            return null;
        }

        $fresh = new static($this->_id, $this->_id_column);

        if ($fresh->getId() === '-1') {
            return null;
        }

        return $fresh;
    }

    // =========================================================================
    // Atomic Operations
    // =========================================================================

    /**
     * Increment a column value atomically
     *
     * @param string    $column The column to increment
     * @param int|float $amount The amount to increment by (default: 1)
     *
     * @return void
     * @throws \InvalidArgumentException If column name is invalid
     * @throws \RuntimeException If record doesn't exist in database
     */
    public function increment(string $column, int|float $amount = 1): void
    {
        if ($this->_id === '-1') {
            throw new \RuntimeException('Cannot increment column on unsaved record');
        }

        $column = self::validateIdentifier($column, 'column name');

        self::_doQuery(
            'UPDATE _DB_ SET `' . $column . '` = `' . $column . '` + ? WHERE `' . $this->_id_column . '` = ?',
            [$amount, $this->_id]
        );

        // Refresh the property value
        $this->refreshProperties();
    }

    /**
     * Decrement a column value atomically
     *
     * @param string    $column The column to decrement
     * @param int|float $amount The amount to decrement by (default: 1)
     *
     * @return void
     * @throws \InvalidArgumentException If column name is invalid
     * @throws \RuntimeException If record doesn't exist in database
     */
    public function decrement(string $column, int|float $amount = 1): void
    {
        $this->increment($column, -$amount);
    }

    // =========================================================================
    // Transaction Support
    // =========================================================================

    /**
     * Get or create a PDO connection for the specified connection name
     *
     * This caches connections to allow transaction support across multiple queries.
     *
     * @param string|null $connectionName Connection name (null for default)
     *
     * @return \PDO The PDO connection
     */
    protected static function getPdo(?string $connectionName = null): \PDO
    {
        $name = $connectionName ?? static::CONNECTION;

        if (!isset(self::$_pdoCache[$name])) {
            $config = static::getConnectionConfig($name);

            $options = $config['OPTIONS'] ?? [];
            if (!isset($options[\PDO::ATTR_ERRMODE])) {
                $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
            }

            self::$_pdoCache[$name] = new \PDO(
                $config['DSN'],
                $config['USER'],
                $config['PASS'],
                $options
            );
        }

        return self::$_pdoCache[$name];
    }

    /**
     * Begin a database transaction
     *
     * @param string|null $connectionName Connection name (null for class default)
     *
     * @return void
     * @throws \PDOException If already in a transaction or connection fails
     */
    public static function beginTransaction(?string $connectionName = null): void
    {
        $pdo = static::getPdo($connectionName);
        $pdo->beginTransaction();
    }

    /**
     * Commit the current transaction
     *
     * @param string|null $connectionName Connection name (null for class default)
     *
     * @return void
     * @throws \PDOException If no transaction is active or commit fails
     */
    public static function commit(?string $connectionName = null): void
    {
        $pdo = static::getPdo($connectionName);
        $pdo->commit();
    }

    /**
     * Roll back the current transaction
     *
     * @param string|null $connectionName Connection name (null for class default)
     *
     * @return void
     * @throws \PDOException If no transaction is active or rollback fails
     */
    public static function rollback(?string $connectionName = null): void
    {
        $pdo = static::getPdo($connectionName);
        $pdo->rollBack();
    }

    /**
     * Check if currently in a transaction
     *
     * @param string|null $connectionName Connection name (null for class default)
     *
     * @return bool True if in a transaction
     */
    public static function inTransaction(?string $connectionName = null): bool
    {
        $name = $connectionName ?? static::CONNECTION;

        if (!isset(self::$_pdoCache[$name])) {
            return false;
        }

        return self::$_pdoCache[$name]->inTransaction();
    }

    /**
     * Execute a callback within a transaction
     *
     * If the callback throws an exception, the transaction is rolled back.
     * Otherwise, the transaction is committed.
     *
     * @param callable    $callback       The callback to execute
     * @param string|null $connectionName Connection name (null for class default)
     *
     * @return mixed The return value of the callback
     * @throws \Throwable Re-throws any exception from the callback after rollback
     */
    public static function transaction(callable $callback, ?string $connectionName = null): mixed
    {
        static::beginTransaction($connectionName);

        try {
            $result = $callback();
            static::commit($connectionName);
            return $result;
        } catch (\Throwable $e) {
            static::rollback($connectionName);
            throw $e;
        }
    }

    /**
     * Clear the PDO connection cache
     *
     * Useful for testing or when connections need to be refreshed.
     *
     * @param string|null $connectionName Specific connection to clear, or null for all
     *
     * @return void
     */
    public static function clearConnectionCache(?string $connectionName = null): void
    {
        if ($connectionName !== null) {
            unset(self::$_pdoCache[$connectionName]);
        } else {
            self::$_pdoCache = [];
        }
    }
}
