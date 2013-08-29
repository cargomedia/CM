<?php

class CM_Model_StorageAdapter_Cache extends CM_Model_StorageAdapter_AbstractAdapter {

	public function load($type, array $id) {
		return CM_Cache::get($this->_getCacheKey($type, $id));
	}

	public function save($type, array $id, array $data) {
		CM_Cache::set($this->_getCacheKey($type, $id), $data);
	}

	public function create($type, array $data) {
		$this->save($type, $this->_generateId(), $data);
	}

	public function delete($type, array $id) {
		CM_Cache::delete($this->_getCacheKey($type, $id));
	}

	/**
	 * @param int   $type
	 * @param array $id
	 * @return string
	 */
	protected function _getCacheKey($type, array $id) {
		return CM_CacheConst::CM_Model_StorageAdapter_Cache . '_type:' . $type . '_id:' . serialize($id);
	}

	/**
	 * @return array
	 */
	protected function _generateId() {
		throw new CM_Exception_NotImplemented();
	}
}
