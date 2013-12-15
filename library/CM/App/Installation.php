<?php

class CM_App_Installation {

	/** @var \Composer\Composer */
	private $_composer;

	/**
	 * @param \Composer\Composer|null $composer
	 */
	public function __construct(\Composer\Composer $composer = null) {
		if (null !== $composer) {
			$this->_composer = $composer;
		}
	}

	/**
	 * @return array [namespace => pathRelative]
	 */
	public function getModulePaths() {
		$namespacePaths = array();
		foreach ($this->getModules() as $module) {
			$namespacePaths[$module->getName()] = $module->getPath();
		}
		return $namespacePaths;
	}

	/**
	 * @return CM_App_Module[]
	 */
	public function getModules() {
		$modules = array();
		foreach ($this->getPackages() as $package) {
			foreach ($package->getModules() as $module) {
				$modules[] = $module;
			}
		}
		return $modules;
	}

	/**
	 * @return CM_App_Package[]
	 * @throws CM_Exception_Invalid
	 */
	public function getPackages() {
		$mainPackageName = 'cargomedia/cm';
		$composerPackages = $this->_getComposerPackages();
		foreach ($composerPackages as $package) {
			if ($package->getName() === $mainPackageName) {
				$composerPackagesFiltered = array($package);
			}
		}
		if (!isset($composerPackagesFiltered)) {
			throw new CM_Exception_Invalid('`' . $mainPackageName . '` package not found within composer packages');
		}

		for (; $parentPackage = current($composerPackagesFiltered); next($composerPackagesFiltered)) {
			foreach ($composerPackages as $package) {
				if (array_key_exists($parentPackage->getName(), $package->getRequires())) {
					$composerPackagesFiltered[] = $package;
				}
			}
		}

		$packages = array();
		/** @var \Composer\Package\CompletePackage[] $composerPackagesFiltered */
		foreach ($composerPackagesFiltered as $package) {
			$packages[] = $this->_getPackageFromComposerPackage($package);
		}
		return $packages;
	}

	/**
	 * @return mixed
	 */
	public function getUpdateStamp() {
		$composerJsonStamp = filemtime(DIR_ROOT . 'composer.json');
		$cacheKey = CM_CacheConst::ComposerInstalledPath;
		$fileCache = new CM_Cache_Storage_File();
		if (false === ($installedJsonPath = $fileCache->get($cacheKey)) || $composerJsonStamp > $fileCache->getCreateStamp($cacheKey)) {
			$installedJsonPath = DIR_ROOT . $this->_getComposerVendorDir() . 'composer/installed.json';
			$fileCache->set($cacheKey, $installedJsonPath);
		}
		$installedJsonStamp = filemtime($installedJsonPath);
		return max($composerJsonStamp, $installedJsonStamp);
	}

	/**
	 * @return \Composer\Package\CompletePackage[]
	 */
	protected function _getComposerPackages() {
		$repo = $this->_getComposer()->getRepositoryManager()->getLocalRepository();

		$packages = $repo->getPackages();
		$packages[] = $this->_getComposer()->getPackage();
		return $packages;
	}

	/**
	 * @return string
	 */
	protected function _getComposerVendorDir() {
		return rtrim($this->_getComposer()->getConfig()->get('vendor-dir'), '/') . '/';
	}

	/**
	 * @param \Composer\Package\CompletePackage $package
	 * @return CM_App_Package
	 * @throws CM_Exception_Invalid
	 */
	protected function _getPackageFromComposerPackage(\Composer\Package\CompletePackage $package) {
		$pathRelative = '';
		if (!$package instanceof \Composer\Package\RootPackage) {
			$vendorDir = $this->_getComposerVendorDir();
			$pathRelative = $vendorDir . $package->getPrettyName() . '/';
		}

		$extra = $package->getExtra();
		if (!array_key_exists('cm-modules', $extra)) {
			throw new CM_Exception_Invalid('Missing `cm-modules` in `' . $package->getName() . '` package composer extra');

		}
		return new CM_App_Package($package->getName(), $pathRelative, $extra['cm-modules']);
	}

	/**
	 * @return \Composer\Composer
	 */
	private function _getComposer() {
		if (null === $this->_composer) {
			$this->_composer = self::composerFactory();
		}
		return $this->_composer;
	}

	/**
	 * @return \Composer\Composer
	 */
	public static function composerFactory() {
		if (!getenv('COMPOSER_HOME') && !getenv('HOME')) {
			putenv('COMPOSER_HOME=' . sys_get_temp_dir() . 'composer/');
		}
		$oldCwd = getcwd();
		chdir(DIR_ROOT);
		$io = new \Composer\IO\NullIO();
		$composer = \Composer\Factory::create($io, DIR_ROOT . 'composer.json');
		chdir($oldCwd);
		return $composer;
	}
}
