<?php

class CM_App {

    /**
     * @var CM_App
     */
    private static $_instance;

    /** @var CM_Service_Manager */
    private $_serviceManager;

    public function __construct() {
        $this->_serviceManager = CM_Service_Manager::getInstance();
    }

    /**
     * @return CM_App
     */
    public static function getInstance() {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function setupFilesystem() {
        $serviceManager = $this->_getServiceManager();
        $serviceManager->getFilesystems()->getData()->getAdapter()->setup();
        $serviceManager->getFilesystems()->getTmp()->getAdapter()->setup();
        $serviceManager->getFilesystems()->getTmp()->deleteByPrefix('/');
        foreach ($serviceManager->getUserContent()->getFilesystemList() as $filesystem) {
            $filesystem->getAdapter()->setup();
        }
    }

    /**
     * @param CM_OutputStream_Interface|null $output
     * @param boolean|null                   $forceReload
     */
    public function setupDatabase(CM_OutputStream_Interface $output = null, $forceReload = null) {
        $isEmptyMysql = $this->_setupDbMysql($forceReload);
        $isEmptyMongo = $this->_setupDbMongo($forceReload);
        if ($isEmptyMysql && $isEmptyMongo) {
            $this->_setInitialVersion();
            $setupProcessor = new CM_Setup_Loader($output);
            $setupProcessor->setServiceManager($this->_getServiceManager());
            $setupProcessor->load();
        }
    }

    public function setupTranslations() {
        /** @var CM_Model_Language $language */
        foreach (new CM_Paging_Language_All() as $language) {
            $path = 'translations/' . $language->getAbbreviation() . '.php';
            foreach (CM_Util::getResourceFiles($path) as $translationsFile) {
                $translationsSetter = require $translationsFile->getPath();
                if (!$translationsSetter instanceof Closure) {
                    throw new CM_Exception_Invalid('Invalid translation file. `' . $translationsFile->getPath() . '` must return callable');
                }
                $translationsSetter($language);
            }
        }
    }

    public function fillCaches() {
        /** @var CM_Asset_Javascript_Abstract[] $assetList */
        $assetList = array();
        $languageList = new CM_Paging_Language_Enabled();
        foreach (CM_Site_Abstract::getAll() as $site) {
            $assetList[] = new CM_Asset_Javascript_Internal($site);
            $assetList[] = new CM_Asset_Javascript_Library($site);
            $assetList[] = new CM_Asset_Javascript_VendorAfterBody($site);
            $assetList[] = new CM_Asset_Javascript_VendorBeforeBody($site);
            foreach ($languageList as $language) {
                $render = new CM_Frontend_Render(new CM_Frontend_Environment($site, null, $language));
                $assetList[] = new CM_Asset_Css_Vendor($render);
                $assetList[] = new CM_Asset_Css_Library($render);
            }
        }
        foreach ($languageList as $language) {
            $assetList[] = new CM_Asset_Javascript_Translations($language);
        }
        foreach ($assetList as $asset) {
            $asset->get(true);
        }
        CM_Bootloader::getInstance()->getModules();
    }

    /**
     * @param string|null $namespace
     * @return int
     */
    public function getVersion($namespace = null) {
        $namespace = (string) $namespace;
        if ($namespace) {
            $namespace = '.' . $namespace;
        }
        return (int) CM_Option::getInstance()->get('app.version' . $namespace);
    }

    /**
     * @param int         $version
     * @param string|null $namespace
     */
    public function setVersion($version, $namespace = null) {
        $version = (int) $version;
        $namespace = (string) $namespace;
        if ($namespace) {
            $namespace = '.' . $namespace;
        }
        CM_Option::getInstance()->set('app.version' . $namespace, $version);
    }

    /**
     * @return int
     */
    public function getDeployVersion() {
        return (int) CM_Config::get()->deployVersion;
    }

    /**
     * @param Closure|null $callbackBefore fn($version)
     * @param Closure|null $callbackAfter  fn($version)
     * @return int Number of version bumps
     */
    public function runUpdateScripts(Closure $callbackBefore = null, Closure $callbackAfter = null) {
        CM_Cache_Shared::getInstance()->flush();
        CM_Cache_Local::getInstance()->flush();
        $versionBumps = 0;
        foreach ($this->_getUpdateScriptPaths() as $namespace => $path) {
            $version = $versionStart = $this->getVersion($namespace);
            while (true) {
                $version++;
                if (!$this->runUpdateScript($namespace, $version, $callbackBefore, $callbackAfter)) {
                    $version--;
                    break;
                }
                $this->setVersion($version, $namespace);
            }
            $versionBumps += ($version - $versionStart);
        }
        if ($versionBumps > 0) {
            $db = $this->_getServiceManager()->getDatabases()->getMaster()->getDb();
            CM_Db_Db::exec('DROP DATABASE IF EXISTS `' . $db . '_test`');
        }
        return $versionBumps;
    }

    /**
     * @param string       $namespace
     * @param int          $version
     * @param Closure|null $callbackBefore
     * @param Closure|null $callbackAfter
     * @return int
     */
    public function runUpdateScript($namespace, $version, Closure $callbackBefore = null, Closure $callbackAfter = null) {
        try {
            $updateScript = $this->_getUpdateScriptPath($version, $namespace);
        } catch (CM_Exception_Invalid $e) {
            return 0;
        }
        if ($callbackBefore) {
            $callbackBefore($version);
        }
        require $updateScript;
        if ($callbackAfter) {
            $callbackAfter($version);
        }
        return 1;
    }

    /**
     * @return CM_Service_Manager
     */
    protected function _getServiceManager() {
        return $this->_serviceManager;
    }

    /**
     * @return string[]
     */
    private function _getUpdateScriptPaths() {
        $paths = array();
        foreach (CM_Bootloader::getInstance()->getModules() as $moduleName) {
            $paths[$moduleName] = CM_Util::getModulePath($moduleName) . 'resources/db/update/';
        }

        $rootPath = DIR_ROOT . 'resources/db/update/';
        if (!in_array($rootPath, $paths)) {
            $paths[null] = $rootPath;
        }

        return $paths;
    }

    /**
     * @param int         $version
     * @param string|null $moduleName
     * @return string
     * @throws CM_Exception_Invalid
     */
    private function _getUpdateScriptPath($version, $moduleName = null) {
        $path = DIR_ROOT;
        if ($moduleName) {
            $path = CM_Util::getModulePath($moduleName);
        }
        $file = new CM_File($path . 'resources/db/update/' . $version . '.php');
        if (!$file->getExists()) {
            throw new CM_Exception_Invalid('Update script `' . $version . '` does not exist for `' . $moduleName . '` namespace.');
        }
        return $file->getPath();
    }

    private function _setInitialVersion() {
        $app = CM_App::getInstance();
        foreach ($this->_getUpdateScriptPaths() as $namespace => $path) {
            $updateFiles = CM_Util::rglob('*.php', $path);
            $version = array_reduce($updateFiles, function ($initial, $path) {
                $filename = basename($path);
                return max($initial, (int) $filename);
            }, 0);
            $app->setVersion($version, $namespace);
        }
    }

    /**
     * @param boolean $forceReload
     * @return boolean
     * @throws CM_Exception_Invalid
     */
    private function _setupDbMongo($forceReload) {
        $mongoClient = $this->_getServiceManager()->getMongoDb();
        if ($forceReload) {
            $mongoClient->dropDatabase();
        }
        $collections = $mongoClient->listCollectionNames();
        $isEmpty = 0 === count($collections);
        if ($isEmpty) {
            foreach (CM_Util::getResourceFiles('mongo/collections.json') as $dump) {
                $collectionInfo = CM_Params::jsonDecode($dump->read());
                foreach ($collectionInfo as $collection => $indexes) {
                    $mongoClient->createCollection($collection);
                    foreach ($indexes as $indexInfo) {
                        $mongoClient->createIndex($collection, $indexInfo['key'], $indexInfo['options']);
                    }
                }
            }
        }
        return $isEmpty;
    }

    /**
     * @param boolean $forceReload
     * @return boolean
     * @throws CM_Db_Exception
     */
    private function _setupDbMysql($forceReload) {
        $mysqlClient = $this->_getServiceManager()->getDatabases()->getMaster();
        $db = $mysqlClient->getDb();
        $mysqlClient->setDb(null);
        if ($forceReload) {
            $mysqlClient->createStatement('DROP DATABASE IF EXISTS ' . $mysqlClient->quoteIdentifier($db))->execute();
        }
        $databaseExists = (bool) $mysqlClient->createStatement('SHOW DATABASES LIKE ?')->execute(array($db))->fetch();
        if (!$databaseExists) {
            $mysqlClient->createStatement('CREATE DATABASE ' . $mysqlClient->quoteIdentifier($db))->execute();
        }
        $mysqlClient->setDb($db);
        $tables = $mysqlClient->createStatement('SHOW TABLES')->execute()->fetchAll();
        $isEmpty = 0 === count($tables);
        if ($isEmpty) {
            foreach (CM_Util::getResourceFiles('db/structure.sql') as $dump) {
                CM_Db_Db::runDump($db, $dump);
            }
        }
        return $isEmpty;
    }
}
