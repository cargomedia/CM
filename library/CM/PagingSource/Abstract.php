<?php

abstract class CM_PagingSource_Abstract {

	private $_cacheLifetime;
	private $_cacheLocalLifetime;

	/**
	 * Enable cache
	 * Cost: 2 memcache requests
	 *
	 * @param int $lifetime
	 */
	public function enableCache($lifetime = 600) {
		$this->_cacheLifetime = (int) $lifetime;
	}

	/**
	 * Enable local (non-invalidatable) cache
	 * Cost: 1 apc request
	 *
	 * @param int $lifetime
	 */
	public function enableCacheLocal($lifetime = 60) {
		$this->_cacheLocalLifetime = (int) $lifetime;
	}

	/**
	 * Clear cache
	 */
	public function clearCache() {
		$tag = CM_Cache::key(CM_CacheConst::PagingSource, $this->_cacheKeyBase());
		if ($this->_cacheLocalLifetime) {
			CM_CacheLocal::deleteTag($tag);
		}
		if ($this->_cacheLifetime) {
			CM_Cache::deleteTag($tag);
		}
	}

	/**
	 * @return float Chance that an item contains stale (non-processable) data (0-1)
	 */
	public function getStalenessChance() {
		return 0;
	}

	protected function _cacheSet($key, $value) {
		$tag = CM_Cache::key(CM_CacheConst::PagingSource, $this->_cacheKeyBase());
		$key = CM_Cache::key(CM_CacheConst::PagingSource, $key);
		if ($this->_cacheLocalLifetime) {
			CM_CacheLocal::setTagged($tag, $key, $value, $this->_cacheLocalLifetime);
		}
		if ($this->_cacheLifetime) {
			CM_Cache::setTagged($tag, $key, $value, $this->_cacheLifetime);
		}
	}

	protected function _cacheGet($key) {
		$tag = CM_Cache::key(CM_CacheConst::PagingSource, $this->_cacheKeyBase());
		$key = CM_Cache::key(CM_CacheConst::PagingSource, $key);
		if ($this->_cacheLocalLifetime) {
			if (($result = CM_CacheLocal::getTagged($tag, $key)) !== false) {
				return $result;
			}
		}
		if ($this->_cacheLifetime) {
			if (($result = CM_Cache::getTagged($tag, $key)) !== false) {
				return $result;
			}
		}
		return false;
	}

	/**
	 * @param int $offset
	 * @param int $count
	 * @return int
	 */
	abstract public function getCount($offset = null, $count = null);

	/**
	 * @param int $offset
	 * @param int $count
	 * @return array
	 */
	abstract public function getItems($offset = null, $count = null);

	/**
	 * @return mixed
	 */
	abstract protected function _cacheKeyBase();
}
