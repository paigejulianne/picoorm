<?php
/**
 * Package PicoORM
 *
 * @author  Paige Julianne Sullivan <paige@paigejulianne.com> https://paigejulianne.com
 * @copyright 2008-present Paige Julianne Sullivan
 * @license GPL-3.0-or-later
 * @link https://github.com/paigejulianne/picoorm
 */

namespace PaigeJulianne;

use PDO, PDOStatement;

class PicoORM {

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
    private string|int $_id_column;

    /**
     * @var string table name
     */
    static public string $_table;

    /**
     * constructor
     *
     * @param string $id_value
     * @param string $id_column
     * @param null|string $table pass a different table name than the class name
     */
    public function __construct(string $id_value, string $id_column = 'id', null|string $table = null)
    {
        if ($table !== null) {
            self::$_table = $table;
        }

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
        return $this;
    }

    /**
     * Refreshes the properties of the current object from the database.
     * The properties are retrieved by executing a SQL query to the database using the _id_column and _id values of the object.
     * If a result is found, the properties of the current object are updated with the retrieved values.
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
     * @param int|string $id_value The value of the id to check.
     * @param string $id_column The name of the id column to check against. Default is 'id'.
     * @return bool True if the record exists, false otherwise.
     */
    public static function exists(int|string $id_value, string $id_column = 'id'): bool
    {
        $result = self::_fetch('SELECT * FROM _DB_ WHERE `' . $id_column . '` = ?', [$id_value]);
        return (bool)$result;
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
        if ($this->_taintedItems) {
            foreach ($this->_taintedItems as $propname => $_) {
                if ($propname[0] == '_') {
                    continue;
                }
                $parts[] = "$propname = ?";
                $values[] = $this->properties[$propname];
            }
            if (@$parts || @$values) {
                if (($this->_id == -1) || ($this->_id_column == NULL)) {
                    // do an insert query and then set the new ID
                    $sql = 'INSERT INTO _DB_ SET ' . implode(', ', $parts);
                    self::_doQuery($sql, $values);
                    $this->_id = $this->properties[$this->_id_column];
                    if ($this->_id == "") $this->_id = $GLOBALS["_PICO_PDO"]->lastInsertId();
                } else {
                    $values[] = $this->_id;
                    $sql = 'UPDATE _DB_ SET ' . implode(', ', $parts) . ' WHERE `' . $this->_id_column . '` = ?';
                    self::_doQuery($sql, $values);
                }
            }
        }
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
     * @param string $prop
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
     * @param string $idColumn
     * @param array $filters column|op|data
     * @param string $filterGlue join statement for filters
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

        $sql    = 'SELECT ' . $idColumn . ' FROM _DB_' . @$filterString;

        if (!empty($dataArray)) {
            $result = self::_fetchAll($sql, $dataArray);
        } else {
            $result = self::_fetchAll($sql);
        }

        if ( ! $result) {
            return [];
        }

        if (count($result) == 1) {
            if ($forceArray) {
                return array(new $_class($result[0][$idColumn]));
            } else {
                return new $_class($result[0][$idColumn]);
            }
        } else {
            foreach ($result as $table_row) {
                $returnArray[$table_row[$idColumn]] = new $_class($table_row[$idColumn]);
            }

            return $returnArray;
        }
    }

    /**
     * fetch all matching records from the database
     *
     * @param string $sql PDO ready sql statement
     * @param array $valueArray properties and values for PDO substitution
     * @param string|null $database technically the table name
     *
     * @return mixed
     */
    static public function _fetchAll(string $sql, array $valueArray = [], string $database = NULL): array
    {
        $statement = self::_doQuery($sql, $valueArray, $database);
        if ($statement->rowCount()) {
            return $statement->fetchAll(PDO::FETCH_BOTH);
        } else {
            return [];
        }
    }

    /**
     * fetch the first matching record from the database
     *
     * @param string $sql PDO ready sql statement
     * @param array $valueArray values for PDO substitution
     * @param string|null $database technically the table name
     *
     * @return array
     */
    static public function _fetch(string $sql, array $valueArray = [], string $database = NULL): array
    {
        $statement = self::_doQuery($sql, $valueArray, $database);
        if ($statement->rowCount()) {
            return $statement->fetch(PDO::FETCH_BOTH);
        } else {
            return [];
        }
    }

    /**
     * executes a sql statement and returns a PDO statement
     *
     * @param string $sql PDO ready sql statement
     * @param array $valueArray values for PDO substitution
     * @param string|null $database technically the table name
     *
     * @return PDOStatement
     */
    static public function _doQuery(string $sql, array $valueArray = [], string $database = NULL): PDOStatement
    {
        if (@!is_object($GLOBALS['_PICO_PDO'])) {
            // @todo this shouldn't be failing and throwing an exception when the object is destroyed
            return new PDOStatement();
        }

        if ($database === NULL) {
            $database = strtolower(get_called_class());
        }

        if ($database === NULL) {
            $database = self::$_table;
        }

        @list($database, $table) = explode('\\', $database);
        if (@$table) {
            $database .= '.' . $table;
        }

        $sql      = str_replace('_DB_', $database, $sql);
        $sql      = str_replace('_db_', $database, $sql);

        $statement = $GLOBALS['_PICO_PDO']->prepare($sql);
        if ($valueArray != NULL) {
            if ( ! is_array($valueArray)) {
                $valueArray = array($valueArray);
            }
            $statement->execute($valueArray);
        } else {
            $statement->execute();
        }

        return $statement;
    }

}
