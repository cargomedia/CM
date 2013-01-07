<?php

class CM_Search_Index_Cli extends CM_Cli_Runnable_Abstract {

	/**
	 * @param string|null  $indexName Index name, if not provided all application indexes will be created.
	 */
	public function create($indexName = null) {
		if ($indexName) {
			$indexes = array($this->_getIndex($indexName));
		} else {
			$indexes = $this->_getIndexes();
		}
		foreach ($indexes as $index) {
			$this->_getOutput()->writeln('Creating index `' . $index->getIndex()->getName() . '`...');
			$index->createVersioned();
			$index->getIndex()->refresh();
		}
	}

	/**
	 * @param string|null $indexName Index name, if not provided all indexes will be updated.
	 * @param string|null $host      Elasticsearch host
	 * @param int|null    $port      Elasticsearch port
	 * @throws CM_Exception_Invalid
	 */
	public function update($indexName = null, $host = null, $port = null) {
		if ($indexName) {
			$indexes = array($this->_getIndex($indexName, $host, $port));
		} else {
			$indexes = $this->_getIndexes($host, $port);
		}
		foreach ($indexes as $index) {
			$this->_getOutput()->writeln('Updating index `' . $index->getIndex()->getName() . '`...');
			$indexName = $index->getIndex()->getName();
			$key = 'Search.Updates_' . $index->getType()->getName();
			try {
				$ids = CM_Cache_Redis::sFlush($key);
				$ids = array_filter(array_unique($ids));
				$index->update($ids);
				$index->getIndex()->refresh();
			} catch (Exception $e) {
				$message = $indexName . '-updates failed.' . PHP_EOL;
				if (isset($ids)) {
					$message .= 'Re-adding ' . count($ids) . ' ids to queue.' . PHP_EOL;
					foreach ($ids as $id) {
						CM_Cache_Redis::sAdd($key, $id);
					}
				}
				$message .= 'Reason: ' . $e->getMessage();
				throw new CM_Exception_Invalid($message);
			}
		}
	}

	public function optimize() {
		$servers = CM_Config::get()->CM_Search->servers;
		foreach ($servers as $server) {
			$client = new Elastica_Client($server);
			$client->optimizeAll();
		}
	}

	/**
	 * @param null $host
	 * @param null $port
	 * @return CM_Elastica_Type_Abstract[]
	 */
	private function _getIndexes($host = null, $port = null) {
		$indexTypes = CM_Util::getClassChildren('CM_Elastica_Type_Abstract');
		return array_map(function ($indexType) use ($host, $port) {
			return new $indexType($host, $port);
		}, $indexTypes);
	}

	/**
	 * @param string      $indexName
	 * @param string|null $host
	 * @param int|null    $port
	 * @throws CM_Exception_Invalid
	 * @return CM_Elastica_Type_Abstract
	 */
	private function _getIndex($indexName, $host = null, $port = null) {
		$indexes = array_filter($this->_getIndexes($host, $port), function (CM_Elastica_Type_Abstract $index) use ($indexName) {
			return $index->getIndex()->getName() == $indexName;
		});
		if (!$indexes) {
			throw new CM_Exception_Invalid('No such index: ' . $indexName);
		}
		return current($indexes);
	}

	public static function getPackageName() {
		return 'search-index';
	}
}
