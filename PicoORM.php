<?php

namespace PaigeJulianne;
/**
 * Package PicoORM
 *
 * @author    Paige Julianne Sullivan <paige@paigejulianne.com> https://paigejulianne.com
 * @copyright 2008-present Paige Julianne Sullivan
 * @license   GPL-3.0-or-later
 * @link      https://github.com/paigejulianne/picoorm
 */
class PicoORM
{

    /**
     * @var mixed ID of row in database
     */
    private string $_id;

    /**
     * @var array holds the columns out of the database table
     */
    private array $properties = [];

    /**
     * @var array list of columns that were "tainted" that need to be updated
     */
    private array $_taintedItems = [];

    /**
     * @var string|int name of ID column in database (usually 'id', but not always)
     */
    private string $_id_column;

    public static int $_lastInsertId;

    const TABLE_OVERRIDE = '';

    public function __construct(string|bool $id_value = false, string $id_column = 'id')
    {
        if (!$id_value) {
            $this->_id = -1;
            $this->_id_column = $id_column;
        } else {
            $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $id_column . '` = ?', [$id_value]);
            if ($result) {
                $this->_id = $id_value;
                $this->_id_column = $id_column;
                $this->properties = $result;
            }
        }
    }

    /**
     * Refreshes the properties of the current object from the database.
     * The properties are retrieved by executing a SQL query to the database using the _id_column and _id values of the
     * object. If a result is found, the properties of the current object are updated with the retrieved values.
     *
     * @return void
     */
    public function refreshProperties(): void
    {
        $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $this->_id_column . '` = ?', [$this->_id]);
        if ($result) {
            $this->properties = $result;
        }
    }

    /**
     * Check if a record exists in the database based on the given id_value and id_column.
     *
     * @param int|string $id_value  The value of the id to check.
     * @param string     $id_column The name of the id column to check against. Default is 'id'.
     * @return bool True if the record exists, false otherwise.
     */
    public static function exists(int|string $id_value, string $id_column = 'id'): bool
    {
        $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $id_column . '` = ?', [$id_value]);
        return (bool)$result;
    }

    public function getId(): string|int {
        return $this->_id;
    }

    /**
     * destructor
     */
    public function __destruct()
    {
        $this->writeChanges();
    }

    /**
     * alias for writeChanges()
     */
    public function save(): void
    {
        $this->writeChanges();
    }

    /**
     * write properties to the database immediately
     */
    public function writeChanges(): void
    {
        $parts = $values = [];
        foreach ($this->_taintedItems as $propname => $_) {
            if ($propname[0] == '_') {
                continue;
            }
            $parts[] = "$propname = ?";
            $values[] = $this->properties[$propname];
        }

        if (@$parts && @$values) {
            if ($this->_id == -1) {
                // do an insert query and then set the new ID
                $sql = 'INSERT INTO _DB_ SET ' . implode(', ', $parts);
                self::_doQuery($sql, $values);
                $this->_id = self::$_lastInsertId;
            } else {
                $values[] = $this->_id;
                $sql = 'UPDATE _DB_ SET ' . implode(', ', $parts) . ' WHERE `' . $this->_id_column . '` = ?';
                self::_doQuery($sql, $values);
            }
        }
        $this->_taintedItems = [];  // we just saved everything, so clear the tainted items array
    }

    /**
     * gets a property
     *
     * @param string $prop
     *
     * @return string|array|int|float|bool|null
     */
    public function __get(string $prop): string|array|int|float|bool|null
    {
        return $this->properties[$prop];
    }

    /**
     * sets a property
     *
     * @param string                           $prop
     * @param string|array|int|float|bool|null $value
     */
    public function __set(string $prop, string|array|int|float|bool|null $value): void
    {
        if ($prop[0] != '_') {
            $this->_taintedItems[$prop] = $prop;
        }
        $this->properties[$prop] = $value;
    }

    /**
     * sets a number of properties from an array
     *
     * @param array $array
     */
    public function setMulti(array $array): void
    {
        foreach ($array as $prop => $value) {
            $this->__set($prop, $value);
        }
    }

    /**
     * ! DANGER WILL ROBINSON ! deletes the current row from the database
     */
    public function delete(): void
    {
        self::_doQuery('DELETE FROM _DB_ WHERE `' . $this->_id_column . '` = ?', [$this->_id]);
    }


    /**
     * retrieves multiple rows/objects from the database based on parameters
     *
     * @param string  $idColumn
     * @param array   $filters    column|op|data
     * @param string  $filterGlue join statement for filters
     * @param boolean $forceArray force an array even if only a single result is returned
     *
     * @return array
     */
    static public function getAllObjects(
        string $idColumn = 'id', array $filters = array(), string $filterGlue = 'AND', bool $forceArray = false
    ): array
    {
        $filterArray = [];
        $filterString = '';
        $returnArray = [];
        $_class = get_called_class();

        // this is to build the string that will be used as the expression by PDO
        foreach ($filters as $filter) {
            if (isset($filter[2])) {
                $filterArray[] = $filter[0] . ' ' . $filter[2] . ' ?';
                $dataArray[] = $filter[3];
            }
        }

        if ($filters) {
            $filterString = ' WHERE ' . implode(' ' . $filterGlue . ' ', $filterArray);
        }

        $sql = 'SELECT ' . $idColumn . ' FROM _DB_' . @$filterString;

        if (!empty($dataArray)) {
            $result = self::_fetchAll($sql, $dataArray);
        } else {
            $result = self::_fetchAll($sql);
        }

        if (!$result) {
            return [];
        }

        if (count($result) == 1) {
            if ($forceArray) {
                return array(new $_class($result[0][$idColumn]), $idColumn);
            } else {
                return new $_class($result[0][$idColumn], $idColumn);
            }
        } else {
            foreach ($result as $table_row) {
                $returnArray[$table_row[$idColumn]] = new $_class($table_row[$idColumn], $idColumn);
            }

            return $returnArray;
        }
    }

    /**
     * fetch all matching records from the database
     *
     * @param string      $sql        PDO ready sql statement
     * @param array       $valueArray properties and values for PDO substitution
     * @param string|null $database   technically the table name
     *
     * @return mixed
     */
    static public function _fetchAll(string $sql, array $valueArray = [], string $database = NULL): array
    {
        $statement = self::_doQuery($sql, $valueArray, $database);
        if ($statement->rowCount()) {
            return $statement->fetchAll();
        } else {
            return [];
        }
    }

    /**
     * fetch the first matching record from the database
     *
     * @param string      $sql        PDO ready sql statement
     * @param array       $valueArray values for PDO substitution
     * @param string|null $database   technically the table name
     *
     * @return array
     */
    static public function _fetch(string $sql, array $valueArray = [], string $database = NULL): array
    {
        $statement = self::_doQuery($sql, $valueArray, $database);
        if ($statement->rowCount()) {
            return $statement->fetch();
        } else {
            return [];
        }
    }

    /**
     * executes a sql statement and returns a PDO statement
     *
     * @param string      $sql        PDO ready sql statement
     * @param array       $valueArray values for PDO substitution
     * @param string|null $table      technically the table name
     *
     * @return PDOStatement
     */
    static public function _doQuery(string $sql, array $valueArray = [], string $table = NULL): \PDOStatement
    {
        global $PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS, $PICOORM_OPTIONS;

        $conn = new \PDO($PICOORM_DSN, $PICOORM_USER, $PICOORM_PASS, $PICOORM_OPTIONS);

        if ($table === NULL) {
            $table = strtolower(static::class);
            if (strpos($table, '\\') !== false) {
                @list($database, $table) = explode('\\', $table);
                $table = $database . '.' . $table;
            }

        }

        if (static::class::TABLE_OVERRIDE != null) {
            $table = static::class::TABLE_OVERRIDE;
        }

        $sql = str_replace('_DB_', $table, $sql);
        $sql = str_replace('_db_', $table, $sql);

        $statement = $conn->prepare($sql);
        if ($valueArray != NULL) {
            if (!is_array($valueArray)) {
                $valueArray = array($valueArray);
            }
            $statement->execute($valueArray);
        } else {
            $statement->execute();
        }

        self::$_lastInsertId = $conn->lastInsertId();
        return $statement;
    }

}
