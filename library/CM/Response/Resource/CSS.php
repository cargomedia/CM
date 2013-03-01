<?php

class CM_Response_Resource_CSS extends CM_Response_Resource_Abstract {

	protected function _process() {
		switch ($this->getRequest()->getPath()) {
			case '/library.css':
				$content = '';
				foreach (CM_Util::rglob('*.css', DIR_PUBLIC . 'static/css/library/') as $path) {
					$content .= new CM_File($path);
				}

				foreach (CM_Util::rglob('*.less', DIR_PUBLIC . 'static/css/library/') as $path) {
					$css = new CM_Css(new CM_File($path));
					$content .= $css->compile($this->getRender());
				}
				break;
			case '/internal.css':
				$css = new CM_Css();

				foreach (array_reverse($this->getSite()->getNamespaces()) as $namespace) {
					foreach (array_reverse($this->getSite()->getThemes()) as $theme) {
						$path = $this->getRender()->getThemeDir(true, $theme, $namespace) . 'variables.less';
						if (is_file($path)) {
							$css->add(new CM_Css(new CM_File($path)));
						}
					}
				}

				foreach (array_reverse($this->getSite()->getNamespaces()) as $namespace) {
					foreach (array_reverse($this->getSite()->getThemes()) as $theme) {
						foreach (CM_Util::rglob('*.less', $this->getRender()->getThemeDir(true, $theme, $namespace) . 'css/') as $path) {
							$file = new CM_File($path);
							$css->add(new CM_Css($file->read()));
						}
					}
				}

				$viewClasses = CM_View_Abstract::getClasses($this->getSite()->getNamespaces(), CM_View_Abstract::CONTEXT_CSS);
				foreach ($viewClasses as $className) {
					if (!preg_match('#^([^_]+)_([^_]+)_(.+)$#', $className, $matches)) {
						throw new CM_Exception("Cannot detect namespace from component's class-name");
					}
					list($className, $namespace, $viewType, $viewName) = $matches;
					$relativePaths = array();
					foreach ($this->getRender()->getSite()->getThemes() as $theme) {
						$basePath = $this->getRender()->getThemeDir(true, $theme, $namespace) . $viewType . '/' . $viewName . '/';
						foreach (CM_Util::rglob('*.less', $basePath) as $path) {
							$relativePaths[] = preg_replace('#^' . $basePath . '#', '', $path);
						}
					}
					foreach (array_unique($relativePaths) as $path) {
						$prefix = '.' . $className;
						if ('Component' == $viewType) {
							if ($path != 'default.less' && strpos($path, '/') === false) {
								$prefix .= '.' . preg_replace('#.less$#', '', $path);
							}
						}

						$file = $this->getRender()->getLayoutFile($viewType . '/' . $viewName . '/' . $path, $namespace);
						$css->add(new CM_Css($file->read(), $prefix));
					}
				}
				$content = $css->compile($this->getRender());

				$content .= $this->_getCssSmiley();
				break;
			default:
				if (!file_exists(DIR_PUBLIC . 'static/css' . $this->getRequest()->getPath())) {
					throw new CM_Exception_Invalid('Invalid filename: `' . $this->getRequest()->getPath() . '`');
				}
				$content = new CM_File(DIR_PUBLIC . 'static/css' . $this->getRequest()->getPath());
				break;
		}

		$this->enableCache();
		$this->setHeader('Content-Type', 'text/css');
		$this->_setContent($content);
	}

	/**
	 * @return string
	 */
	private function _getCssSmiley() {
		$css = '';
		foreach (new CM_Paging_Smiley_All() as $smiley) {
			$css .= '.smiley.smiley-' . $smiley['id'] . '{';
			$css .= 'background-image: url(' . $this->getRender()->getUrlStatic('/img/smiles/' . $smiley['path']) . ')';
			$css .= '}' . PHP_EOL;
		}
		return $css;
	}

	public static function match(CM_Request_Abstract $request) {
		return $request->getPathPart(0) === 'css';
	}
}
