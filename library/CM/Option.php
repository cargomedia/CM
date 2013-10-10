<?php

class CM_Option {
	/**
	 * @var CM_Option
	 */
	private static $_instance;

	/**
	 * @return CM_Option
	 */
	public static function getInstance() {
		if (!self::$_instance) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function get($key) {
		$cacheKey = CM_CacheConst::Option;
		$cache = CM_Cache_Shared::getInstance();
		if (($options = $cache->get($cacheKey)) === false) {
			$options = CM_Db_Db::select('cm_option', array('key', 'value'))->fetchAllTree();
			$cache->set($cacheKey, $options);
		}
		if (!isset($options[$key])) {
			return null;
		}
		$value = unserialize($options[$key]);
		if (false === $value) {
			throw new CM_Exception_Invalid('Cannot unserialize option `' . $key . '`.');
		}
		return $value;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value) {
		CM_Db_Db::replace('cm_option', array('key' => $key, 'value' => serialize($value)));
		$this->_clearCache();
	}

	/**
	 * @param string $key
	 */
	public function delete($key) {
		CM_Db_Db::delete('cm_option', array('key' => $key));
		$this->_clearCache();
	}

	/**
	 * @param string   $key
	 * @param int|null $change
	 * @return int New value
	 */
	public function inc($key, $change = null) {
		if (is_null($change)) {
			$change = +1;
		}
		$value = (int) $this->get($key);
		$value += (int) $change;
		$this->set($key, $value);
		return $value;
	}

	private function _clearCache() {
		$cacheKey = CM_CacheConst::Option;
		CM_Cache_Shared::getInstance()->delete($cacheKey);
	}
}
