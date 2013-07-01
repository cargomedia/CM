<?php

abstract class CM_Class_Abstract {

	/**
	 * @return int
	 */
	public function getType() {
		return static::TYPE;
	}

	/**
	 * @return string[] List of class names
	 */
	public function getClassHierarchy() {
		return self::_getClassHierarchy();
	}

	/**
	 * @param int $type
	 * @return string
	 * @throws CM_Class_Exception_TypeNotConfiguredException
	 */
	protected static function _getClassName($type = null) {
		$config = self::_getConfig();
		if (null === $type || empty($config->types)) {
			if (empty($config->class)) {
				return get_called_class();
			}
			return $config->class;
		}
		$type = (int) $type;
		if (empty($config->types[$type])) {
			throw new CM_Class_Exception_TypeNotConfiguredException('Type `' . $type . '` not configured for class `' . get_called_class() . '`.');
		}
		return $config->types[$type];
	}

	/**
	 * @return stdClass
	 * @throws CM_Exception_Invalid
	 */
	protected static function _getConfig() {
		$config = CM_Config::get();
		$cacheEnabled = $config->CM_Class_Abstract->configCacheEnabled;
		$className = get_called_class();
		$cacheKey = CM_CacheConst::Config . '_className:' . $className;
		if (!$cacheEnabled || false === ($result = CM_CacheLocal::get($cacheKey))) {
			$result = self::_getConfigRaw();
			CM_CacheLocal::set($cacheKey, $result);
		}
		return $result;
	}

	/**
	 * @return stdClass
	 * @throws CM_Exception_Invalid
	 */
	protected static function _getConfigRaw() {
		$config = CM_Config::get();
		$result = array();
		foreach (self::_getClassHierarchy() as $class) {
			if (isset($config->$class)) {
				$result = array_merge((array) $config->$class, $result);
			}
		}
		if (empty($result)) {
			throw new CM_Exception_Invalid('Class `' . get_called_class() . '` has no configuration.');
		}
		return (object) $result;
	}

	/**
	 * @throws CM_Exception_Invalid
	 * @return string
	 */
	protected static function _getClassNamespace() {
		return CM_Util::getNamespace(get_called_class());
	}

	/**
	 * @return string[]
	 */
	private static function _getClassHierarchy() {
		static $classHierarchyCache = array();

		$className = get_called_class();
		if (isset($classHierarchyCache[$className])) {
			return $classHierarchyCache[$className];
		}
		$classHierarchy = array_values(class_parents($className));
		array_unshift($classHierarchy, $className);
		array_pop($classHierarchy);
		$classHierarchyCache[$className] = $classHierarchy;
		return $classHierarchy;
	}

	/**
	 * @param boolean|null $includeAbstracts
	 * @return string[]
	 */
	public static function getClassChildren($includeAbstracts = null) {
		$className = get_called_class();
		return CM_Util::getClassChildren($className, $includeAbstracts);
	}
}
