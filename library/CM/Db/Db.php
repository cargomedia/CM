<?php

class CM_Db_Db extends CM_Class_Abstract {

    /**
     * @param string            $table
     * @param string|array|null $where Associative array field=>value OR string
     * @return int
     */
    public static function count($table, $where = null) {
        $client = self::getClient();
        $query = new CM_Db_Query_Count($client, $table, $where);
        return (int) $query->execute()->fetchColumn();
    }

    /**
     * @param string            $table
     * @param string|array|null $where
     * @return int
     */
    public static function delete($table, $where = null) {
        $client = self::getClient();
        $query = new CM_Db_Query_Delete($client, $table, $where);
        return $query->execute()->getAffectedRows();
    }

    /**
     * @param string     $table
     * @param string     $column
     * @param array      $whereRow
     * @param array|null $where
     */
    public static function deleteSequence($table, $column, array $whereRow, array $where = null) {
        if (null === $where) {
            $where = array();
        }
        $sequenceMax = self::count($table, $where);
        if ($sequenceMax) {
            self::updateSequence($table, $column, $sequenceMax, $whereRow, $where);
            self::delete($table, array_merge($whereRow, $where));
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @return CM_Db_Schema_Column
     */
    public static function describeColumn($table, $column) {
        return new CM_Db_Schema_Column(self::getClient(), $table, $column);
    }

    /**
     * @param string            $sqlTemplate
     * @param array|null        $parameters
     * @param bool|null         $disableQueryBuffering
     * @param CM_Db_Client|null $client
     * @return CM_Db_Result
     */
    public static function exec($sqlTemplate, array $parameters = null, $disableQueryBuffering = null, CM_Db_Client $client = null) {
        if (!$client) {
            $client = self::getClient();
        }
        $disableQueryBuffering = (bool) $disableQueryBuffering;
        $result = $client->createStatement($sqlTemplate)->execute($parameters, $disableQueryBuffering);
        return $result;
    }

    /**
     * @param string     $sqlTemplate
     * @param array|null $parameters
     * @param bool|null  $disableQueryBuffering
     * @return CM_Db_Result
     */
    public static function execRead($sqlTemplate, array $parameters = null, $disableQueryBuffering = null) {
        $client = CM_Service_Manager::getInstance()->getDatabases()->getRead();
        return self::exec($sqlTemplate, $parameters, $disableQueryBuffering, $client);
    }

    /**
     * @param string     $sqlTemplate
     * @param array|null $parameters
     * @param bool|null  $disableQueryBuffering
     * @return CM_Db_Result
     */
    public static function execReadMaintenance($sqlTemplate, array $parameters = null, $disableQueryBuffering = null) {
        $client = CM_Service_Manager::getInstance()->getDatabases()->getReadMaintenance();
        return self::exec($sqlTemplate, $parameters, $disableQueryBuffering, $client);
    }

    /**
     * @param string $table
     * @param string $column
     * @return bool
     */
    public static function existsColumn($table, $column) {
        $client = self::getClient();
        return (bool) self::exec('SHOW COLUMNS FROM ' . $client->quoteIdentifier($table) . ' LIKE ?', array($column))->fetch();
    }

    /**
     * @param string $table
     * @param string $index
     * @return bool
     */
    public static function existsIndex($table, $index) {
        $client = self::getClient();
        return (bool) self::exec('SHOW INDEX FROM ' . $client->quoteIdentifier($table) . ' WHERE Key_name = ?', array($index))->fetch();
    }

    /**
     * @param string $table
     * @return bool
     */
    public static function existsTable($table) {
        return (bool) self::exec('SHOW TABLES LIKE ?', array($table))->getAffectedRows();
    }

    /**
     * @param string            $table
     * @param string|array      $fields Column-name OR Column-names array OR associative field=>value pair
     * @param string|array|null $values Column-value OR Column-values array OR Multiple Column-values array(array)
     * @param array|null        $onDuplicateKeyValues
     * @param string|null       $statement
     * @return string|null
     */
    public static function insert($table, $fields, $values = null, array $onDuplicateKeyValues = null, $statement = null) {
        if (null === $statement) {
            $statement = 'INSERT';
        }
        $client = self::getClient();
        $query = new CM_Db_Query_Insert($client, $table, $fields, $values, $onDuplicateKeyValues, $statement);
        $query->execute();
        return $client->getLastInsertId();
    }

    /**
     * @param string            $table
     * @param string|string[]   $fields
     * @param string|array|null $values
     * @return string|null
     */
    public static function insertIgnore($table, $fields, $values = null) {
        return self::insert($table, $fields, $values, null, 'INSERT IGNORE');
    }

    /**
     * @param string            $table
     * @param string|array      $fields Column-name OR Column-names array OR associative field=>value pair
     * @param string|array|null $values Column-value OR Column-values array OR Multiple Column-values array(array)
     * @param array|null        $onDuplicateKeyValues
     * @return string|null
     */
    public static function insertDelayed($table, $fields, $values = null, array $onDuplicateKeyValues = null) {
        $statement = (self::_getConfig()->delayedEnabled) ? 'INSERT DELAYED' : 'INSERT';
        return self::insert($table, $fields, $values, $onDuplicateKeyValues, $statement);
    }

    /**
     * @param string            $table
     * @param string|array      $fields Column-name OR Column-names array OR associative field=>value pair
     * @param string|array|null $values Column-value OR Column-values array OR Multiple Column-values array(array)
     * @return string|null
     */
    public static function replace($table, $fields, $values = null) {
        return self::insert($table, $fields, $values, null, 'REPLACE');
    }

    /**
     * @param string            $table
     * @param string|array      $fields Column-name OR Column-names array OR associative field=>value pair
     * @param string|array|null $values Column-value OR Column-values array OR Multiple Column-values array(array)
     * @return string|null
     */
    public static function replaceDelayed($table, $fields, $values = null) {
        $statement = (self::_getConfig()->delayedEnabled) ? 'REPLACE DELAYED' : 'REPLACE';
        return self::insert($table, $fields, $values, null, $statement);
    }

    /**
     * @param string            $table
     * @param string|array      $fields Column-name OR Column-names array
     * @param string|array|null $where  Associative array field=>value OR string
     * @param string|null       $order
     * @return CM_Db_Result
     */
    public static function select($table, $fields, $where = null, $order = null) {
        $client = self::getClient();
        $query = new CM_Db_Query_Select($client, $table, $fields, $where, $order);
        return $query->execute();
    }

    /**
     * @param string          $table
     * @param string|string[] $fields    Column-name OR Column-names array
     * @param array[]         $whereList Outer array-entries are combined using OR, inner arrays using AND
     * @param string|null     $order
     * @return CM_Db_Result
     */
    public static function selectMultiple($table, $fields, array $whereList, $order = null) {
        $client = self::getClient();
        $query = new CM_Db_Query_SelectMultiple($client, $table, $fields, $whereList, $order);
        return $query->execute();
    }

    /**
     * @param string $table
     */
    public static function truncate($table) {
        $client = self::getClient();
        $query = new CM_Db_Query_Truncate($client, $table);
        $query->execute();
    }

    /**
     * @param string            $table
     * @param array             $values Associative array field=>value
     * @param string|array|null $where  Associative array field=>value OR string
     * @return int
     */
    public static function update($table, array $values, $where = null) {
        $client = self::getClient();
        $query = new CM_Db_Query_Update($client, $table, $values, $where);
        return $query->execute()->getAffectedRows();
    }

    /**
     * @param string            $table
     * @param array             $values Associative array field=>value
     * @param string|array|null $where  Associative array field=>value OR string
     * @return int
     */
    public static function updateIgnore($table, array $values, $where = null) {
        $client = self::getClient();
        $query = new CM_Db_Query_Update($client, $table, $values, $where, 'UPDATE IGNORE');
        return $query->execute()->getAffectedRows();
    }

    /**
     * @param string     $table
     * @param string     $column
     * @param int        $position
     * @param array      $whereRow Associative array field=>value
     * @param array|null $where    Associative array field=>value
     * @throws CM_Exception_Invalid
     */
    public static function updateSequence($table, $column, $position, array $whereRow, array $where = null) {
        $table = (string) $table;
        $column = (string) $column;
        $position = (int) $position;
        if (null === $where) {
            $where = array();
        }

        if ($position <= 0 || $position > CM_Db_Db::count($table, $where)) {
            throw new CM_Exception_Invalid('Sequence out of bounds.');
        }

        $whereMerged = array_merge($whereRow, $where);
        $positionOld = CM_Db_Db::select($table, $column, $whereMerged)->fetchColumn();
        if (false === $positionOld) {
            throw new CM_Exception_Invalid('Could not retrieve original sequence number.');
        }
        $positionOld = (int) $positionOld;

        if ($position > $positionOld) {
            $upperBound = $position;
            $lowerBound = $positionOld;
            $direction = -1;
        } else {
            $upperBound = $positionOld;
            $lowerBound = $position;
            $direction = 1;
        }

        $client = self::getClient();
        $query = new CM_Db_Query_UpdateSequence($client, $table, $column, $direction, $where, $lowerBound, $upperBound);
        $query->execute();

        self::update($table, array($column => $position), $whereMerged);
    }

    /**
     * @param array|null  $tables
     * @param bool|null   $skipData
     * @param bool|null   $skipStructure
     * @param string|null $dbName
     * @return string
     */
    public static function getDump(array $tables = null, $skipData = null, $skipStructure = null, $dbName = null) {
        $client = CM_Service_Manager::getInstance()->getDatabases()->getMaster();
        if (null === $dbName) {
            $dbName = $client->getDb();
        }
        $args = array();
        $args[] = '--compact';
        $args[] = '--add-drop-table';
        $args[] = '--extended-insert';
        if ($skipData) {
            $args[] = '--no-data';
        }
        if ($skipStructure) {
            $args[] = '--no-create-info';
        }
        $args[] = '--host=' . $client->getHost();
        $args[] = '--port=' . $client->getPort();
        $args[] = '--user=' . $client->getUsername();
        $args[] = '--password=' . $client->getPassword();
        $args[] = $dbName;
        if ($tables) {
            foreach ($tables as $table) {
                $args[] = $table;
            }
        }

        $dump = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";' . PHP_EOL;
        $dump .= '/*!40101 SET NAMES utf8 */;' . PHP_EOL;
        if (array() !== $tables) {
            $queries = CM_Util::exec('mysqldump', $args);
            $queries = preg_replace('#(\s+)AUTO_INCREMENT\s*=\s*\d+\s+#', '$1', $queries);
            $queries = preg_replace('#/\*.*?\*/;#', '', $queries);
            $dump .= $queries;
        }

        return $dump;
    }

    /**
     * @param string  $dbName
     * @param CM_File $dump
     */
    public static function runDump($dbName, CM_File $dump) {
        $client = CM_Service_Manager::getInstance()->getDatabases()->getMaster();
        $args = array();
        $args[] = '--host=' . $client->getHost();
        $args[] = '--port=' . $client->getPort();
        $args[] = '--user=' . $client->getUsername();
        $args[] = '--password=' . $client->getPassword();
        $args[] = $dbName;
        CM_Util::exec('mysql', $args, null, $dump->getPath());
    }

    /**
     * @param string      $table
     * @param string      $column
     * @param string|null $where
     * @return int
     * @throws CM_DB_Exception
     */
    public static function getRandId($table, $column, $where = null) {
        $client = self::getClient();
        $idGuess = self::_getRandIdGuess($table, $column, $where);
        $columnQuoted = $client->quoteIdentifier($column);
        $whereGuessId = (null === $where ? '' : $where . ' AND ') . $columnQuoted . " <= $idGuess";
        $id = CM_Db_Db::exec('SELECT ' . $columnQuoted . ' FROM ' . $table . ' WHERE ' . $whereGuessId . ' ORDER BY ' . $columnQuoted .
            ' DESC LIMIT 1')->fetchColumn();

        if (!$id) {
            $id = CM_Db_Db::select($table, $column, $where)->fetchColumn();
        }
        if (!$id) {
            throw new CM_Db_Exception('Cannot find random id');
        }
        return (int) $id;
    }

    /**
     * @throws CM_Db_Exception
     * @return CM_Db_Client
     */
    public static function getClient() {
        return CM_Service_Manager::getInstance()->getDatabases()->getMaster();
    }

    /**
     * @param string      $table
     * @param string      $column
     * @param string|null $where
     * @return int
     */
    private static function _getRandIdGuess($table, $column, $where = null) {
        $client = self::getClient();
        $columnQuoted = $client->quoteIdentifier($column);
        $sql = 'SELECT MIN(' . $columnQuoted . ') AS min, MAX(' . $columnQuoted . ') AS max FROM ' . $client->quoteIdentifier($table);
        if (null !== $where) {
            $sql .= ' WHERE ' . (string) $where;
        }
        $idBounds = CM_Db_Db::exec($sql)->fetch();
        return rand($idBounds['min'], $idBounds['max']);
    }
}
