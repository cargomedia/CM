<?php

class CM_App_ComposerFactory extends CM_Class_Abstract {

    /**
     * @param string $rootDir
     * @return \Composer\Composer
     */
    public function createComposerFromRootDir($rootDir) {
        $composerPath = $rootDir . 'composer.json';
        $composerFile = new Composer\Json\JsonFile($composerPath);
        $composerFile->validateSchema(Composer\Json\JsonFile::LAX_SCHEMA);
        $localConfig = $composerFile->read();

        $composerFactory = new CM_App_ComposerFactory();
        $composer = $composerFactory->createComposer($localConfig, $rootDir);

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $vendorConfig = new Composer\Json\JsonFile($vendorDir . '/composer/installed.json');
        $vendorRepository = new Composer\Repository\InstalledFilesystemRepository($vendorConfig);
        $composer->getRepositoryManager()->setLocalRepository($vendorRepository);
        return $composer;
    }

    /**
     * @param array  $localConfig
     * @param string $baseDir
     * @return \Composer\Composer
     */
    public function createComposer(array $localConfig, $baseDir) {
        $baseDir = rtrim($baseDir, '/');

        $io = new \Composer\IO\NullIO();
        $composer = new \Composer\Composer();

        $composerConfig = new \Composer\Config(false, $baseDir);
        $composerConfig->merge(array('config' => array('home' => CM_Bootloader::getInstance()->getDirTmp() . 'composer/')));
        $composerConfig->merge($localConfig);
        $composer->setConfig($composerConfig);

        $im = $this->createInstallationManager();
        $composer->setInstallationManager($im);

        $this->createDefaultInstallers($im, $composer, $io);

        $dispatcher = new \Composer\EventDispatcher\EventDispatcher($composer, $io);
        $composer->setEventDispatcher($dispatcher);

        $generator = new \Composer\Autoload\AutoloadGenerator($dispatcher);
        $composer->setAutoloadGenerator($generator);

        $rm = $this->createRepositoryManager($composer, $io);
        $composer->setRepositoryManager($rm);

        $loader = new \Composer\Package\Loader\RootPackageLoader($rm, $composerConfig);
        $package = $loader->load($localConfig);
        $composer->setPackage($package);

        return $composer;
    }

    /**
     * @param Composer\Composer       $composer
     * @param Composer\IO\IOInterface $io
     * @return \Composer\Repository\RepositoryManager
     */
    public function createRepositoryManager(\Composer\Composer $composer, Composer\IO\IOInterface $io) {
        $config = $composer->getConfig();
        $rm = new \Composer\Repository\RepositoryManager($io, $config);

        $rm->setRepositoryClass('composer', 'Composer\Repository\ComposerRepository');
        $rm->setRepositoryClass('vcs', 'Composer\Repository\VcsRepository');
        $rm->setRepositoryClass('package', 'Composer\Repository\PackageRepository');
        $rm->setRepositoryClass('pear', 'Composer\Repository\PearRepository');
        $rm->setRepositoryClass('git', 'Composer\Repository\VcsRepository');
        $rm->setRepositoryClass('svn', 'Composer\Repository\VcsRepository');
        $rm->setRepositoryClass('hg', 'Composer\Repository\VcsRepository');
        $rm->setRepositoryClass('artifact', 'Composer\Repository\ArtifactRepository');
        return $rm;
    }

    /**
     * @param \Composer\Installer\InstallationManager $im
     * @param \Composer\Composer                      $composer
     * @param \Composer\IO\IOInterface                $io
     */
    public function createDefaultInstallers(Composer\Installer\InstallationManager $im, Composer\Composer $composer, \Composer\IO\IOInterface $io) {
        $im->addInstaller(new \Composer\Installer\LibraryInstaller($io, $composer, null));
        $im->addInstaller(new \Composer\Installer\PearInstaller($io, $composer, 'pear-library'));
        $im->addInstaller(new \Composer\Installer\PluginInstaller($io, $composer));
        $im->addInstaller(new \Composer\Installer\MetapackageInstaller($io));
    }

    /**
     * @return \Composer\Installer\InstallationManager
     */
    public function createInstallationManager() {
        return new \Composer\Installer\InstallationManager();
    }
}
