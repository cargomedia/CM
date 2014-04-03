<?php

/**
 * Class CM_Mongodb_Client
 */
class CM_MongoDB_Client extends CM_Class_Abstract {

    /** @var \MongoClient */
    private $_mongodb = null;

    /** @var MongoDB */
    private $_db = null;

    /**
     * @return MongoDB
     */
    public function getDb() {
        return $this->_db;
    }

    /**
     * @return MongoClient
     */
    public function getMongodb() {
        return $this->_mongodb;
    }

    public function __construct() {
        $config = CM_Config::get()->CM_MongoDB;
        $this->_mongodb = new MongoClient($config->server);
        $this->useDatabase($config->database);
    }

    /**
     * @return string
     */
    public function getNewId() {
        $mongoId = new MongoId();
        return (string)$mongoId;
    }

    /**
     * @param $databaseName
     */
    public function useDatabase($databaseName) {
        if ($this->_db) {
            unset($this->_db);
        }
        $this->_db = $this->_mongodb->{$databaseName};
    }

    /**
     * @param $collection
     * @return MongoCollection
     */
    public function getCollection($collection) {
        return $this->_db->{$collection};
    }

    /**
     * @param $collection
     * @param $object
     * @return array|bool
     */
    public function insert($collection, $object) {
        return $this->getCollection($collection)->insert($object);
    }

    /**
     * @param $collection
     * @param $query
     * @return array
     */
    public function findOne($collection, $query) {
        return $this->getCollection($collection)->findOne($query);
    }

    /**
     * @param $collection
     * @param $query
     * @return MongoCursor
     */
    public function find($collection, $query) {
        return $this->getCollection($collection)->find($query);
    }

    /**
     * @param     $collection
     * @param     $query
     * @param int $limit
     * @param int $skip
     * @return int
     */
    public function count($collection, $query, $limit = 0, $skip = 0) {
        return $this->getCollection($collection)->count($query, $limit, $skip);
    }

    /**
     * @param $collection
     * @return array
     */
    public function drop($collection) {
        return $this->getCollection($collection)->drop();
    }

    /**
     * @param string     $collection
     * @param array      $criteria
     * @param array      $newObject
     * @param array|null $options
     * @return MongoCursor
     */
    public function update($collection, $criteria, $newObject, $options = null) {
        $options = ($options !== null) ? $options : array();
        return $this->getCollection($collection)->update($criteria, $newObject, $options);
    }

    /**
     * @param string     $collection
     * @param array     $criteria
     * @param array|null $options
     * @return mixed
     */
    public function remove($collection, $criteria, $options = null) {
        $options = ($options !== null) ? $options : array();
        return $this->getCollection($collection)->remove($criteria, $options);
    }

    /**
     * @return CM_Mongodb_Client
     */
    public static function getInstance() {
        static $instance;
        if (!$instance) {
            $instance = new self();
        }
        return $instance;
    }
}
