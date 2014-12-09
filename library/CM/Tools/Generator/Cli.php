<?php

class CM_Tools_Generator_Cli extends CM_Cli_Runnable_Abstract {

    /** @var CM_Tools_AppInstallation */
    protected $_appInstallation;

    /**
     * @param string       $moduleName
     * @param boolean|null $singleModuleStructure
     * @param string|null  $modulePath
     * @throws CM_Cli_Exception_Internal
     */
    public function createModule($moduleName, $singleModuleStructure = null, $modulePath = null) {
        if (!$this->_isValidModuleName($moduleName)) {
            throw new CM_Cli_Exception_Internal('Invalid module name. ');
        }
        $appInstallation = $this->_getAppInstallation();
        if ($appInstallation->moduleExists($moduleName)) {
            throw new CM_Cli_Exception_Internal('Module `' . $moduleName . '` already exists');
        }

        if ($singleModuleStructure) {
            if (count($appInstallation->getRootModules()) > 0) {
                throw new CM_Cli_Exception_Internal('Cannot create new `single-module-structure` module when some modules already exists');
            }
            if (null !== $modulePath) {
                throw new CM_Cli_Exception_Internal('Cannot specify `module-path` when using `single-module-structure`');
            }
            $modulePath = '';
        } else {
            if ($appInstallation->isSingleModuleStructure()) {
                throw new CM_Cli_Exception_Internal('Cannot add more modules to `single-module-structure` package');
            }
            if (null === $modulePath) {
                $modulePath = $appInstallation->getModulesDirectoryPath() . $moduleName . '/';
            }
            if (null == $modulePath) {
                throw new CM_Cli_Exception_Internal('Cannot find module path');
            }
        }
        $generatorApp = new CM_Tools_Generator_Application($appInstallation, $this->_getStreamOutput());
        $generatorApp->addModule($moduleName, $modulePath);
        $this->_createNamespace($moduleName, $moduleName);
    }

    /**
     * @param string $className
     * @throws CM_Exception_Invalid
     */
    public function createView($className) {
        $appInstallation = $this->_getAppInstallation();
        $generatorPhp = new CM_Tools_Generator_Class_Php($appInstallation, $this->_getStreamOutput());

        $parentClassName = $generatorPhp->getParentClassName($className);
        if (!is_subclass_of($parentClassName, 'CM_View_Abstract')) {
            throw new CM_Exception_Invalid('Detected parent className `' . $parentClassName . '` is not a subclass of `CM_View_Abstract`.');
        }
        $generatorPhp->createClassFile($className);

        $generatorJavascript = new CM_Tools_Generator_Class_Javascript($appInstallation, $this->_getStreamOutput());
        $generatorJavascript->createClassFile($className);

        $generatorLayout = new CM_Tools_Generator_Class_Layout($appInstallation, $this->_getStreamOutput());
        $generatorLayout->createTemplateFile($className);
        $generatorLayout->createStylesheetFile($className);
    }

    /**
     * @param string $className
     * @throws CM_Exception_Invalid
     */
    public function createClass($className) {
        if (class_exists($className) && !$this->_getStreamInput()->confirm('Class `' . $className . '` already exists. Replace?')) {
            return;
        }
        $generatorPhp = new CM_Tools_Generator_Class_Php($this->_getAppInstallation(), $this->_getStreamOutput());
        $generatorPhp->createClassFile($className);

        (new CM_App_Cli())->generateConfigInternal();
    }

    /**
     * @param string $className
     * @param string $name
     * @param string $domain
     * @throws CM_Exception_Invalid
     */
    public function createSite($className, $name, $domain) {
        $appInstallation = $this->_getAppInstallation();
        $generatorPhp = new CM_Tools_Generator_Class_Php($appInstallation, $this->_getStreamOutput());
        $generatorConfig = new CM_Tools_Generator_Config($appInstallation, $this->_getStreamOutput());

        $moduleName = CM_Util::getNamespace($className);
        $parentClassName = $generatorPhp->getParentClassName($className);
        if (!is_a($parentClassName, 'CM_Site_Abstract', true)) {
            throw new CM_Exception_Invalid('Detected parent className `' . $parentClassName . '` is not a `CM_Site_Abstract`.');
        }
        $classBlock = $generatorPhp->createClass($className);
        $classBlock->addMethod(new \CodeGenerator\MethodBlock('__construct', "parent::__construct();\n\$this->_setModule('{$moduleName}');"));
        $generatorPhp->createClassFileFromClass($classBlock);

        $modulePath = $appInstallation->getModulePath($moduleName);
        $file = new CM_File($modulePath . 'resources/config/default.php', $appInstallation->getFilesystem());
        $config = new CM_Config_Node();
        $config->$className->name = $name;
        $config->$className->emailAddress = 'hello@' . $domain;
        $generatorConfig->addEntries($file, $config);

        $file = new CM_File('resources/config/local.php', $appInstallation->getFilesystem());
        $config = new CM_Config_Node();
        $config->$className->url = 'http://www.' . $domain;
        $config->$className->urlCdn = 'http://origin-www.' . $domain;
        $generatorConfig->addEntries($file, $config);

        (new CM_App_Cli())->generateConfigInternal();
    }

    /**
     * @param string|null $projectName
     * @param string|null $domain
     * @param string|null $moduleName
     * @throws CM_Cli_Exception_Internal
     */
    public function bootstrapProject($projectName = null, $domain = null, $moduleName = null) {
        $generatorApp = new CM_Tools_Generator_Application($this->_getAppInstallation(), $this->_getStreamOutput());

        $projectName = $this->_read('Please provide project name (vendor/project):', $projectName, null, function ($value) use ($generatorApp) {
            if (!$generatorApp->isValidProjectName($value)) {
                throw new CM_InputStream_InvalidValueException('Project name needs to be in `vendor/project` format');
            }
        });
        list($vendorName, $appName) = explode('/', $projectName);

        $defaultDomain = $appName . '.dev';
        $hint = "Please provide local domain [{$defaultDomain}]:";
        $domain = $this->_read($hint, $domain, $defaultDomain, function ($value) use ($generatorApp) {
            if (!$generatorApp->isValidDomain($value)) {
                throw new CM_InputStream_InvalidValueException('Invalid domain name');
            }
        });

        $defaultModuleName = CM_Util::camelize($appName);
        $moduleName = $this->_read("Please provide module name [{$defaultModuleName}]:", $moduleName, $defaultModuleName, function ($value) {
            if (!$this->_isValidModuleName($value)) {
                throw new CM_InputStream_InvalidValueException('Invalid name');
            }
        });

        $siteName = $appName;
        $siteClassName = $moduleName . '_Site_' . CM_Util::camelize($siteName);

        try {
            $generatorApp->configureDevEnvironment($appName, $domain);
            $generatorApp->setProjectName($projectName);
            $this->createModule($moduleName);
            $this->createSite($siteClassName, $siteName, $domain);
        } catch (CM_Exception_Invalid $e) {
            throw new CM_Cli_Exception_Internal("Bootstrap failed! {$e->getMessage()}");
        }
    }

    /**
     * @param string $moduleName
     * @param string $namespace
     * @throws CM_Cli_Exception_Internal
     */
    protected function _createNamespace($moduleName, $namespace) {
        $appInstallation = $this->_getAppInstallation();
        if (!$appInstallation->moduleExists($moduleName)) {
            throw new CM_Cli_Exception_Internal('Module `' . $moduleName . '` must exist! Existing modules: ' .
                join(', ', $appInstallation->getModuleNames()));
        }
        if (array_key_exists($namespace, $appInstallation->getNamespaces())) {
            throw new CM_Cli_Exception_Internal('Namespace `' . $namespace . '` already exists');
        }
        $namespacePath = $appInstallation->getModulePath($moduleName) . 'library/' . $namespace;
        $generatorApp = new CM_Tools_Generator_Application($appInstallation, $this->_getStreamOutput());
        $generatorApp->addNamespace($namespace, $namespacePath);
        $generatorApp->dumpAutoload();
    }

    /**
     * @return CM_Tools_AppInstallation
     */
    protected function _getAppInstallation() {
        if (null === $this->_appInstallation) {
            $this->_appInstallation = new CM_Tools_AppInstallation(DIR_ROOT);
        }
        return $this->_appInstallation;
    }

    /**
     * @param string       $hint
     * @param string|null  $initialValue
     * @param string|null  $default
     * @param Closure|null $validateCallback
     * @return string
     */
    private function _read($hint, $initialValue = null, $default = null, Closure $validateCallback = null) {
        $value = $initialValue;
        if (null === $value) {
            $value = $this->_getStreamInput()->read($hint, $default, $validateCallback);
        }
        if (null !== $validateCallback) {
            $validateCallback($value);
        }
        return $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    private function _isValidModuleName($name) {
        return (bool) preg_match('/[A-Z][a-zA-Z]+/', $name);
    }

    public static function getPackageName() {
        return 'generator';
    }
}
