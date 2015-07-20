<?php

class CM_MongoDb_Client extends CM_Class_Abstract {

    /** @var CM_MongoDb_Client|null $_client */
    private $_client = null;

    /** @var array */
    private $_config;

    /**
     * @param array $config
     */
    public function __construct(array $config) {
        $this->_config = $config;
    }

    /**
     * @param string $collection
     * @param array  $index
     * @return array
     * @throws CM_MongoDb_Exception
     */
    public function deleteIndex($collection, array $index) {
        $result = $this->_getCollection($collection)->deleteIndex($index);
        $this->_checkResultForErrors($result);
        return $result;
    }

    /**
     * @return string[]
     */
    public function listCollectionNames() {
        return $this->_getDatabase()->getCollectionNames();
    }

    /**
     * @param string     $collection
     * @param array      $object
     * @param array|null $options
     * @return mixed insertId
     * @throws CM_MongoDb_Exception
     */
    public function insert($collection, array $object, array $options = null) {
        $options = $options ?: [];
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Insert `{$collection}`: " . CM_Params::jsonEncode($object));
        $intermediary = &$object;
        $data = $intermediary;
        $result = $this->_getCollection($collection)->insert($data, $options);
        $this->_checkResultForErrors($result);
        $id = $data['_id'];
        return $id;
    }

    /**
     * @param string     $collection
     * @param array[]    $objectList
     * @param array|null $options
     * @return mixed[] insertIds
     * @throws CM_MongoDb_Exception
     */
    public function batchInsert($collection, array $objectList, array $options = null) {
        $options = $options ?: [];
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Batch Insert `{$collection}`: " . CM_Params::jsonEncode($objectList));
        $dataList = \Functional\map($objectList, function (array $object) {
            return $object;
        });
        $result = $this->_getCollection($collection)->batchInsert($dataList, $options);
        $this->_checkResultForErrors($result);
        return \Functional\map($dataList, function (array $data) {
            return $data['_id'];
        });
    }

    /**
     * @param string $name
     * @param array  $options
     * @return MongoCollection
     */
    public function createCollection($name, array $options = null) {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "create collection {$name}: " . CM_Params::jsonEncode($options));
        return $this->_getDatabase()->createCollection($name, $options);
    }

    /**
     * @param string $collection
     * @param array  $keys
     * @param array  $options
     * @return array
     * @throws CM_MongoDb_Exception
     */
    public function createIndex($collection, array $keys, array $options = null) {
        $options = $options ?: [];
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "create index on {$collection}: " .
            CM_Params::jsonEncode($keys) . ' ' . CM_Params::jsonEncode($options));
        $result = $this->_getCollection($collection)->createIndex($keys, $options);
        $this->_checkResultForErrors($result);
        return $result;
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $update
     * @param array|null $projection
     * @param array|null $options
     * @return array|null
     */
    public function findAndModify($collection, $criteria = null, $update = null, $projection = null, $options = null) {
        return $this->_getCollection($collection)->findAndModify($criteria, $update, $projection, $options);
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $projection
     * @param array|null $aggregation
     * @return array|null
     */
    public function findOne($collection, array $criteria = null, array $projection = null, array $aggregation = null) {
        $criteria = (array) $criteria;
        $projection = (array) $projection;
        if ($aggregation) {
            array_push($aggregation, ['$limit' => 1]);
            $resultSet = $this->find($collection, $criteria, $projection, $aggregation);
            $resultSet->rewind();
            $result = $resultSet->current();
        } else {
            $result = $this->_getCollection($collection)->findOne($criteria, $projection);
            CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "findOne `{$collection}`: " .
                CM_Params::jsonEncode(['projection' => $projection, 'criteria' => $criteria]));
        }

        return $result;
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $projection
     * @param array|null $aggregation
     * @return Iterator
     *
     * When using aggregation, $criteria and $projection, if defined, automatically
     * function as `$match` and `$project` operator respectively at the front of the pipeline
     */
    public function find($collection, array $criteria = null, array $projection = null, array $aggregation = null) {
        $batchSize = null;
        if (isset(self::_getConfig()->batchSize)) {
            $batchSize = (int) self::_getConfig()->batchSize;
        }
        $criteria = (array) $criteria;
        $projection = (array) $projection;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "find `{$collection}`: " .
            CM_Params::jsonEncode(['projection' => $projection, 'criteria' => $criteria, 'aggregation' => $aggregation]));
        $collection = $this->_getCollection($collection);
        if ($aggregation) {
            $pipeline = $aggregation;
            if ($projection) {
                array_unshift($pipeline, ['$project' => $projection]);
            }
            if ($criteria) {
                array_unshift($pipeline, ['$match' => $criteria]);
            }
            $options = [];
            if (null !== $batchSize) {
                $options['cursor'] = ['batchSize' => $batchSize];
            }
            $resultCursor = $collection->aggregateCursor($pipeline, $options);
        } else {
            $resultCursor = $collection->find($criteria, $projection);
        }
        if (null !== $batchSize) {
            $resultCursor->batchSize($batchSize);
        }
        return $resultCursor;
    }

    /**
     * @param $collection
     * @return array
     */
    public function getIndexInfo($collection) {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "indexInfo {$collection}");
        $indexInfo = $this->_getCollection($collection)->getIndexInfo();
        return $indexInfo;
    }

    /**
     * @param string $collection
     * @param array  $index
     * @return bool
     */
    public function hasIndex($collection, array $index) {
        $indexInfo = $this->getIndexInfo($collection);
        return \Functional\some($indexInfo, function ($indexInfo) use ($index) {
            return array_keys($index) === array_keys($indexInfo['key']) && $index == $indexInfo['key'];
        });
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $aggregation
     * @param int|null   $limit
     * @param int|null   $offset
     * @return int
     */
    public function count($collection, array $criteria = null, array $aggregation = null, $limit = null, $offset = null) {
        $criteria = (array) $criteria;
        $limit = (int) $limit;
        $offset = (int) $offset;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "count `{$collection}`: " .
            CM_Params::jsonEncode(['criteria' => $criteria, 'aggregation' => $aggregation]));
        if ($aggregation) {
            $pipeline = $aggregation;
            if ($criteria) {
                array_unshift($pipeline, ['$match' => $criteria]);
            }
            if ($offset) {
                array_push($pipeline, ['$skip' => $offset]);
            }
            if ($limit) {
                array_push($pipeline, ['$limit' => $limit]);
            }
            array_push($pipeline, ['$group' => ['_id' => null, 'count' => ['$sum' => 1]]]);
            array_push($pipeline, ['$project' => ['_id' => 0, 'count' => 1]]);
            $result = $this->_getCollection($collection)->aggregate($pipeline);
            if (!empty($result['result'])) {
                return $result['result'][0]['count'];
            }
            return 0;
        } else {
            $count = $this->_getCollection($collection)->count($criteria);
            if ($offset) {
                $count -= $offset;
            }
            if ($limit) {
                $count = min($count, $limit);
            }
            return max(0, $count);
        }
    }

    /**
     * @param string $collection
     * @return array
     * @throws CM_MongoDb_Exception
     */
    public function drop($collection) {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "drop `{$collection}`");
        $result = $this->_getCollection($collection)->drop();
        $this->_checkResultForErrors($result);
        return $result;
    }

    /**
     * @return array
     */
    public function dropDatabase() {
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "drop database {$this->_getDatabaseName()}");
        return $this->_getDatabase()->drop();
    }

    /**
     * @return bool
     */
    public function databaseExists() {
        return \Functional\contains($this->_listDatabaseNames(), $this->_getDatabaseName());
    }

    /**
     * @param string $collection
     * @return boolean
     */
    public function existsCollection($collection) {
        return \Functional\contains($this->listCollectionNames(), (string) $collection);
    }

    /**
     * @param string|MongoId $id
     * @return boolean
     */
    public function isValidObjectId($id) {
        return MongoId::isValid($id);
    }

    /**
     * @param string     $collection
     * @param array      $criteria
     * @param array      $newObject
     * @param array|null $options
     * @return MongoCursor
     * @throws CM_MongoDb_Exception
     */
    public function update($collection, array $criteria, array $newObject, array $options = null) {
        $options = (array) $options;
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Update `{$collection}`: " .
            CM_Params::jsonEncode(['criteria' => $criteria, 'newObject' => $newObject]));
        $result = $this->_getCollection($collection)->update($criteria, $newObject, $options);
        $this->_checkResultForErrors($result);
        return is_array($result) ? $result['n'] : $result;
    }

    /**
     * @param string     $collection
     * @param array|null $criteria
     * @param array|null $options
     * @return mixed
     * @throws CM_MongoDb_Exception
     */
    public function remove($collection, array $criteria = null, array $options = null) {
        $criteria = $criteria ?: array();
        $options = $options ?: array();
        CM_Service_Manager::getInstance()->getDebug()->incStats('mongo', "Remove `{$collection}`: " . CM_Params::jsonEncode($criteria));
        $result = $this->_getCollection($collection)->remove($criteria, $options);
        $this->_checkResultForErrors($result);
        return is_array($result) ? $result['n'] : $result;
    }

    /**
     * @param string|null $id
     * @return MongoId
     */
    public function getObjectId($id = null) {
        return new MongoId($id);
    }

    /**
     * @param array|boolean $result
     * @throws CM_MongoDb_Exception
     */
    protected function _checkResultForErrors($result) {
        if (true !== $result && empty($result['ok'])) {
            throw new CM_MongoDb_Exception('Cannot perform mongodb operation', ['result' => $result]);
        }
    }

    /**
     * @return MongoClient
     */
    protected function _getClient() {
        if (null === $this->_client) {
            $this->_client = new MongoClient($this->_config['server'], $this->_config['options']);
        }
        return $this->_client;
    }

    /**
     * @return string[]
     */
    protected function _listDatabaseNames() {
        $databasesInfo = $this->_getClient()->listDBs();
        return \Functional\pluck($databasesInfo['databases'], 'name');
    }

    /**
     * @return string
     */
    protected function _getDatabaseName() {
        return CM_Bootloader::getInstance()->getDataPrefix() . $this->_config['db'];
    }

    /**
     * @return MongoDB
     * @throws CM_Exception_Nonexistent
     */
    protected function _getDatabase() {
        return $this->_getClient()->selectDB($this->_getDatabaseName());
    }

    /**
     * @param string $collection
     * @return MongoCollection
     */
    protected function _getCollection($collection) {
        $collection = (string) $collection;
        return $this->_getDatabase()->selectCollection($collection);
    }
}
