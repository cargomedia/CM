<?php

class CM_Elasticsearch_Index_Cli extends CM_Cli_Runnable_Abstract {

    /**
     * @param string|null $indexName
     * @param bool|null   $skipIfExist
     */
    public function create($indexName = null, $skipIfExist = null) {
        if (null !== $indexName) {
            $indexes = array($this->_getIndex($indexName));
        } else {
            $indexes = $this->_getIndexes();
        }
        foreach ($indexes as $index) {
            if (!$index->getIndex()->exists() || !$skipIfExist) {
                $this->_getStreamOutput()->writeln('Creating elasticsearch index `' . $index->getIndex()->getName() . '`…');
                $index->createVersioned();
                $index->getIndex()->refresh();
            }
        }
    }

    /**
     * @param string|CM_Elasticsearch_Type_Abstract|null $indexName
     * @param string|null                                $host Elasticsearch host
     * @param int|null                                   $port Elasticsearch port
     * @throws CM_Exception_Invalid
     */
    public function update($indexName = null, $host = null, $port = null) {
        if ($indexName instanceof CM_Elasticsearch_Type_Abstract) {
            $indexes = array($indexName);
        } elseif (null !== $indexName) {
            $indexes = array($this->_getIndex($indexName, $host, $port));
        } else {
            $indexes = $this->_getIndexes($host, $port);
        }
        foreach ($indexes as $index) {
            $this->_getStreamOutput()->writeln('Updating elasticsearch index `' . $index->getIndex()->getName() . '`...');
            $indexName = $index->getIndex()->getName();
            $key = 'Search.Updates_' . $index->getType()->getName();
            try {
                $ids = $this->_getRedis()->sFlush($key);
                $ids = array_filter(array_unique($ids));
                $index->update($ids);
                $index->getIndex()->refresh();
            } catch (Exception $e) {
                $message = $indexName . '-updates failed.' . PHP_EOL;
                if (isset($ids)) {
                    $message .= 'Re-adding ' . count($ids) . ' ids to queue.' . PHP_EOL;
                    foreach ($ids as $id) {
                        $this->_getRedis()->sAdd($key, $id);
                    }
                }
                $message .= 'Reason: ' . $e->getMessage() . PHP_EOL;
                throw new CM_Exception_Invalid($message);
            }
        }
    }

    /**
     * @param string|null $indexName
     */
    public function delete($indexName = null) {
        if (null !== $indexName) {
            $indexes = array($this->_getIndex($indexName));
        } else {
            $indexes = $this->_getIndexes();
        }
        foreach ($indexes as $index) {
            if ($index->getIndex()->exists()) {
                $this->_getStreamOutput()->writeln('Deleting elasticsearch index `' . $index->getIndex()->getName() . '`…');
                $index->getIndex()->delete();
            }
        }
    }

    public function optimize() {
        foreach (CM_Service_Manager::getInstance()->getElasticsearch()->getClientList() as $client) {
            $client->optimizeAll();
        }
    }

    /**
     * @keepalive
     */
    public function startMaintenance() {
        $clockwork = new CM_Clockwork_Manager();
        $storage = new CM_Clockwork_Storage_FileSystem('search-maintenance');
        $storage->setServiceManager(CM_Service_Manager::getInstance());
        $clockwork->setStorage($storage);
        $clockwork->registerCallback('search-index-update', '1 minute', array($this, 'update'));
        $clockwork->registerCallback('search-index-optimize', '1 hour', array($this, 'optimize'));
        $clockwork->start();
    }

    /**
     * @param string|null $host
     * @param int|null    $port
     * @return CM_Elasticsearch_Type_Abstract[]
     */
    private function _getIndexes($host = null, $port = null) {
        $indexTypes = CM_Util::getClassChildren('CM_Elasticsearch_Type_Abstract');
        return array_map(function ($indexType) use ($host, $port) {
            return new $indexType($host, $port);
        }, $indexTypes);
    }

    /**
     * @param string      $indexName
     * @param string|null $host
     * @param int|null    $port
     * @throws CM_Exception_Invalid
     * @return CM_Elasticsearch_Type_Abstract
     */
    private function _getIndex($indexName, $host = null, $port = null) {
        $indexes = array_filter($this->_getIndexes($host, $port), function (CM_Elasticsearch_Type_Abstract $index) use ($indexName) {
            return $index->getIndex()->getName() == $indexName;
        });
        if (!$indexes) {
            throw new CM_Exception_Invalid('No such index: ' . $indexName);
        }
        return current($indexes);
    }

    /**
     * @return CM_Redis_Client
     */
    private function _getRedis() {
        return CM_Service_Manager::getInstance()->getRedis();
    }

    public static function getPackageName() {
        return 'search-index';
    }
}
