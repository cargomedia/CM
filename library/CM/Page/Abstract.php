<?php

abstract class CM_Page_Abstract extends CM_Component_Abstract {

	public final function checkAccessible() {
	}

	/**
	 * Checks if the page is viewable by the current user
	 *
	 * @return bool True if page is visible
	 */
	public function isViewable() {
		return true;
	}

	/**
	 * @param CM_Response_Page $response
	 */
	public function prepareResponse(CM_Response_Page $response) {
	}

	/**
	 * @param CM_Site_Abstract $site
	 * @param string           $path
	 * @throws CM_Exception_Invalid
	 * @return string
	 */
	public static final function getClassnameByPath($site, $path) {
		$path = (string) $path;

		$pathTokens = explode('/', $path);
		array_shift($pathTokens);

		// Rewrites code-of-honor to CodeOfHonor
		foreach ($pathTokens as &$pathToken) {
			$pathToken = CM_Util::camelize($pathToken);
		}

		$pagePath = implode('/', $pathTokens);
		foreach ($site->getNamespaces() as $namespace) {
			$classPath =
				DIR_ROOT . CM_Bootloader::getInstance()->getNamespacePath($namespace) . 'library/' . $namespace . '/Page/' . $pagePath . '.php';
			if (CM_File::exists($classPath)) {
				return $namespace . '_Page_' . implode('_', $pathTokens);
			}
		}

		throw new CM_Exception_Invalid('page `' . implode('_', $pathTokens) . '` is not defined in any namespace');
	}

	/**
	 * @param array|null $params
	 * @return string
	 */
	public static function getPath(array $params = null) {
		$pageClassName = get_called_class();
		$list = explode('_', $pageClassName);

		// Remove first parts
		foreach ($list as $index => $entry) {
			unset($list[$index]);
			if ($entry == 'Page') {
				break;
			}
		}

		// Converts upper case letters to dashes: CodeOfHonor => code-of-honor
		foreach ($list as $index => $entry) {
			$list[$index] = CM_Util::uncamelize($entry);
		}

		$path = '/' . implode('/', $list);
		if ($path == '/index') {
			$path = '/';
		}
		return CM_Util::link($path, $params);
	}

	/**
	 * @return CM_Layout_Abstract
	 */
	public function getLayout() {
		$layoutname = 'Default';
		$classname = self::_getClassNamespace() . '_Layout_' . $layoutname;
		return new $classname($this);
	}
}
