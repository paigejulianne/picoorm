<?php
/**
 * Package PicoORM
 *
 * @author  Paige Julianne Sullivan <paige@paigejulianne.com>
 * @license MIT
 */

class PicoORM {

    /**
     * @var mixed ID of row in database
     */
    private mixed $_id = 0;

    /**
     * @var array holds the columns out of the database table
     */
    private array $properties = [];

    /**
     * @var array list of columns that were "tainted" that need to be updated
     */
    private array $_taintedItems = [];

    /**
     * @var string name of ID column in database (usually 'id', but not always)
     */
    private ?string $_id_column = NULL;

    /**
     * constructor
     *
     * @param mixed $id_value
     * @param string $id_column
     */
    public function __construct($id_value = false, $id_column = 'id')
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
        return $this;
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
                    $this->_id = $GLOBALS["_PICO_PDO"]->lastInsertId();
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
     * @param  string  $prop
     *
     * @return mixed
     */
    public function __get(string $prop): mixed
    {
        return $this->properties[$prop];
    }

    /**
     * sets a property
     *
     * @param  string  $prop
     * @param  mixed   $value
     */
    public function __set(string $prop, mixed $value): void
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
        self::_doQuery('DELETE FROM _DB_ WHERE `' . $this->_id_column . '` = ?', $this->_id);
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
        string $idColumn = 'id', array $filters = array(), string $filterGlue = 'AND', mixed $forceArray = false
    ): array
    {
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

        $sql    = 'SELECT ' . $idColumn . ' FROM _DB_' . $filterString;

        $result = self::_fetchAll($sql, $dataArray);
        if ( ! $result) {
            return FALSE;
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
     * @param string $database technically the table name
     *
     * @return mixed
     */
    static public function _fetchAll(string $sql, array $valueArray = [], string $database = NULL): mixed
    {
        $statement = self::_doQuery($sql, $valueArray, $database);
        if ($statement->rowCount()) {
            return $statement->fetchAll(PDO::FETCH_BOTH);
        } else {
            return false;
        }
    }

    /**
     * fetch the first matching record from the database
     *
     * @param string $sql PDO ready sql statement
     * @param string $valueArray values for PDO substitution
     * @param string $database technically the table name
     *
     * @return mixed
     */
    static public function _fetch(string $sql, array $valueArray = [], string $database = NULL): mixed
    {
        $statement = self::_doQuery($sql, $valueArray, $database);
        if ($statement->rowCount()) {
            return $statement->fetch(PDO::FETCH_BOTH);
        } else {
            return false;
        }
    }

    /**
     * executes a sql statement and returns a PDO statement
     *
     * @param  string  $sql         PDO ready sql statement
     * @param  array   $valueArray  values for PDO substitution
     * @param  string  $database    technically the table name
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
