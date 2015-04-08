<?php

class CM_Frontend_Render extends CM_Class_Abstract implements CM_Service_ManagerAwareInterface {

    use CM_Service_ManagerAwareTrait;

    /** @var CM_Frontend_GlobalResponse|null */
    private $_js;

    /** @var NumberFormatter */
    private $_formatterCurrency;

    /** @var bool */
    private $_languageRewrite;

    /** @var CM_Menu[] */
    private $_menuList = array();

    /** @var CM_Frontend_Environment */
    private $_environment;

    /** @var Smarty|null */
    private static $_smarty;

    /**
     * @param CM_Frontend_Environment|null $environment
     * @param boolean|null                 $languageRewrite
     * @param CM_Service_Manager|null      $serviceManager
     */
    public function __construct(CM_Frontend_Environment $environment = null, $languageRewrite = null, CM_Service_Manager $serviceManager = null) {
        if (!$environment) {
            $environment = new CM_Frontend_Environment();
        }
        $this->_environment = $environment;
        $this->_languageRewrite = (bool) $languageRewrite;
        if (null === $serviceManager) {
            $serviceManager = CM_Service_Manager::getInstance();
        }
        $this->setServiceManager($serviceManager);
    }

    /**
     * @return CM_Site_Abstract
     */
    public function getSite() {
        return $this->getEnvironment()->getSite();
    }

    /**
     * @return CM_Frontend_Environment
     */
    public function getEnvironment() {
        return $this->_environment;
    }

    /**
     * @return CM_Frontend_GlobalResponse
     */
    public function getGlobalResponse() {
        if (null === $this->_js) {
            $this->_js = new CM_Frontend_GlobalResponse();
        }
        return $this->_js;
    }

    /**
     * @param string        $path
     * @param array|null    $variables
     * @param string[]|null $compileId
     * @return string
     */
    public function fetchTemplate($path, array $variables = null, array $compileId = null) {
        $compileId = (array) $compileId;
        $compileId[] = $this->getSite()->getId();
        if ($this->getLanguage()) {
            $compileId[] = $this->getLanguage()->getAbbreviation();
        }
        /** @var Smarty_Internal_TemplateBase $template */
        $template = $this->_getSmarty()->createTemplate($path, null, join('_', $compileId));
        $template->assignGlobal('render', $this);
        $template->assignGlobal('viewer', $this->getViewer());
        if ($variables) {
            $template->assign($variables);
        }
        return $template->fetch();
    }

    /**
     * @param string     $content
     * @param array|null $variables
     * @return string
     */
    public function parseTemplateContent($content, array $variables = null) {
        $content = 'string:' . $content;
        return $this->fetchTemplate($content, $variables);
    }

    /**
     * @param bool|null   $absolute True if full path required
     * @param string|null $theme
     * @param string|null $namespace
     * @return string Theme base path
     */
    public function getThemeDir($absolute = false, $theme = null, $namespace = null) {
        if (!$theme) {
            $theme = $this->getSite()->getTheme();
        }
        if (!$namespace) {
            $namespace = $this->getSite()->getModule();
        }

        $path = CM_Util::getModulePath($namespace, !$absolute);
        return $path . 'layout/' . $theme . '/';
    }

    /**
     * @param string      $template Template file name
     * @param string|null $module
     * @param string|null $theme
     * @param bool|null   $absolute
     * @param bool|null   $needed
     * @throws CM_Exception_Invalid
     * @return string Layout path based on theme
     */
    public function getLayoutPath($template, $module = null, $theme = null, $absolute = null, $needed = true) {
        $moduleList = $this->getSite()->getModules();
        if ($module !== null) {
            $moduleList = array((string) $module);
        }
        $themeList = $this->getSite()->getThemes();
        if ($theme !== null) {
            $themeList = array((string) $theme);
        }
        foreach ($moduleList as $module) {
            foreach ($themeList as $theme) {
                $file = new CM_File($this->getThemeDir(true, $theme, $module) . $template);
                if ($file->exists()) {
                    if ($absolute) {
                        return $file->getPath();
                    } else {
                        return $this->getThemeDir(false, $theme, $module) . $template;
                    }
                }
            }
        }

        if ($needed) {
            throw new CM_Exception_Invalid('Cannot find `' . $template . '` in modules `' . implode('`, `', $moduleList) . '` and themes `' .
                implode('`, `', $this->getSite()->getThemes()) . '`');
        }
        return null;
    }

    /**
     * @param string      $path
     * @param string|null $namespace
     * @return CM_File
     * @throws CM_Exception_Invalid
     */
    public function getLayoutFile($path, $namespace = null) {
        return new CM_File($this->getLayoutPath($path, $namespace, null, true));
    }

    /**
     * @return string
     */
    public function getSiteName() {
        return $this->getSite()->getName();
    }

    /**
     * @param string|null           $path
     * @param CM_Site_Abstract|null $site
     * @return string
     */
    public function getUrl($path = null, CM_Site_Abstract $site = null) {
        if (null === $path) {
            $path = '';
        }
        if (null === $site) {
            $site = $this->getSite();
        }
        $path = (string) $path;
        return $site->getUrl() . $path;
    }

    /**
     * @param CM_Page_Abstract|string $pageClassName
     * @param array|null              $params
     * @param CM_Site_Abstract|null   $site
     * @param CM_Model_Language|null  $language
     * @throws CM_Exception_Invalid
     * @return string
     */
    public function getUrlPage($pageClassName, array $params = null, CM_Site_Abstract $site = null, CM_Model_Language $language = null) {
        if (null === $site) {
            $site = $this->getSite();
        }
        if ($pageClassName instanceof CM_Page_Abstract) {
            $pageClassName = get_class($pageClassName);
        }
        $pageClassName = (string) $pageClassName;

        if (!class_exists($pageClassName) || !is_subclass_of($pageClassName, 'CM_Page_Abstract')) {
            throw new CM_Exception_Invalid('Cannot find valid class definition for page `' . $pageClassName . '`.');
        }
        if (!preg_match('/^([A-Za-z]+)_/', $pageClassName, $matches)) {
            throw new CM_Exception_Invalid('Cannot find namespace of `' . $pageClassName . '`');
        }
        $namespace = $matches[1];
        if (!in_array($namespace, $site->getModules())) {
            throw new CM_Exception_Invalid('Site `' . get_class($site) . '` does not contain namespace `' . $namespace . '`');
        }
        /** @var CM_Page_Abstract $pageClassName */
        $path = $pageClassName::getPath($params);

        $languageRewrite = $this->_languageRewrite || $language;
        if (!$language) {
            $language = $this->getLanguage();
        }
        if ($languageRewrite && $language) {
            $path = '/' . $language->getAbbreviation() . $path;
        }
        return $this->getUrl($path, $site);
    }

    /**
     * @param string|null $type
     * @param string|null $path
     * @param array|null  $options
     * @return string
     */
    public function getUrlResource($type = null, $path = null, array $options = null) {
        $options = array_merge([
            'sameOrigin' => false,
            'root'       => false,
        ], (array) $options);

        if (!$options['sameOrigin'] && $this->getSite()->getUrlCdn()) {
            $url = $this->getSite()->getUrlCdn();
        } else {
            $url = $this->getSite()->getUrl();
        }

        if (!is_null($type) && !is_null($path)) {
            $pathParts = [];
            $pathParts[] = (string) $type;
            if ($this->getLanguage()) {
                $pathParts[] = $this->getLanguage()->getAbbreviation();
            }
            $pathParts[] = $this->getSite()->getId();
            $pathParts[] = CM_App::getInstance()->getDeployVersion();
            $pathParts = array_merge($pathParts, explode('/', $path));

            if ($options['root']) {
                $url .= '/resource-' . implode('--', $pathParts);
            } else {
                $url .= '/' . implode('/', $pathParts);
            }
        }

        return $url;
    }

    /**
     * @param CM_Mail $mail
     * @return string
     * @throws CM_Exception_Invalid
     */
    public function getUrlEmailTracking(CM_Mail $mail) {
        if (!$mail->getRecipient()) {
            throw new CM_Exception_Invalid('Needs user');
        }
        $params = array('user' => $mail->getRecipient()->getId(), 'mailType' => $mail->getType());
        return CM_Util::link($this->getSite()->getUrl() . '/emailtracking/' . $this->getSite()->getId(), $params);
    }

    /**
     * @param string|null $path
     * @return string
     */
    public function getUrlStatic($path = null) {
        if ($this->getSite()->getUrlCdn()) {
            $url = $this->getSite()->getUrlCdn();
        } else {
            $url = $this->getSite()->getUrl();
        }

        $url .= '/static';
        if (null !== $path) {
            $url .= $path . '?' . CM_App::getInstance()->getDeployVersion();
        }

        return $url;
    }

    /**
     * @param CM_File_UserContent $file
     * @return string
     */
    public function getUrlUserContent(CM_File_UserContent $file) {
        return $file->getUrl();
    }

    /**
     * @return CM_Model_User|null
     */
    public function getViewer() {
        return $this->getEnvironment()->getViewer();
    }

    /**
     * @param bool|null $fallbackToDefault
     * @return CM_Model_Language|null
     */
    public function getLanguage($fallbackToDefault = null) {
        $language = $this->getEnvironment()->getLanguage();

        if (null === $language && $fallbackToDefault) {
            if ($viewer = $this->getViewer()) {
                $language = $viewer->getLanguage();
            }
            if (null === $language) {
                $language = CM_Model_Language::findDefault();
            }
        }

        return $language;
    }

    /**
     * @param string     $key
     * @param array|null $params
     * @return string
     */
    public function getTranslation($key, array $params = null) {
        $params = (array) $params;
        $translation = $key;
        if ($language = $this->getLanguage(true)) {
            $translation = $language->getTranslation($key, array_keys($params));
        }
        $translation = $this->_parseVariables($translation, $params);
        return $translation;
    }

    public function clearTemplates() {
        $this->_getSmarty()->clearCompiledTemplate();
    }

    /**
     * @param int         $dateType
     * @param int         $timeType
     * @param string|null $pattern
     * @return IntlDateFormatter
     */
    public function getFormatterDate($dateType, $timeType, $pattern = null) {
        return new IntlDateFormatter($this->getLocale(), $dateType, $timeType, $this->getEnvironment()->getTimeZone()->getName(), null, $pattern);
    }

    /**
     * @return NumberFormatter
     */
    public function getFormatterCurrency() {
        if (!$this->_formatterCurrency) {
            $this->_formatterCurrency = new NumberFormatter($this->getLocale(), NumberFormatter::CURRENCY);
        }
        return $this->_formatterCurrency;
    }

    /**
     * @return string
     */
    public function getLocale() {
        return $this->getEnvironment()->getLocale();
    }

    /**
     * @param CM_Menu $menu
     */
    public function addMenu(CM_Menu $menu) {
        $this->_menuList[] = $menu;
    }

    /**
     * @return CM_Menu[]
     */
    public function getMenuList() {
        return $this->_menuList;
    }

    /**
     * @param CM_View_Abstract $view
     * @param string           $templateName
     * @throws CM_Exception
     * @return string|null
     */
    public function getTemplatePath(CM_View_Abstract $view, $templateName) {
        $templateName = (string) $templateName;
        foreach ($view->getClassHierarchy() as $className) {
            if (!preg_match('/^([a-zA-Z]+)_([a-zA-Z]+)_(.+)$/', $className, $matches)) {
                throw new CM_Exception('Cannot detect namespace/view-class/view-name for `' . $className . '`.');
            }
            $templatePathRelative = $matches[2] . DIRECTORY_SEPARATOR . $matches[3] . DIRECTORY_SEPARATOR . $templateName . '.tpl';
            $namespace = $matches[1];
            if ($templatePath = $this->getLayoutPath($templatePathRelative, $namespace, null, false, false)) {
                return $templatePath;
            }
        }
        return null;
    }

    /**
     * @param CM_View_Abstract $view
     * @param string           $templateName
     * @param array|null       $data
     * @throws CM_Exception
     * @return string
     */
    public function fetchViewTemplate(CM_View_Abstract $view, $templateName, array $data = null) {
        $templatePath = $this->getTemplatePath($view, $templateName);
        if (null === $templatePath) {
            throw new CM_Exception('Cannot find template `' . $templateName . '` for `' . get_class($view) . '`.');
        }
        $viewClassName = get_class($view);
        return $this->fetchTemplate($templatePath, $data, [$viewClassName]);
    }

    /**
     * @param CM_Frontend_ViewResponse $viewResponse
     * @return string
     */
    public function fetchViewResponse(CM_Frontend_ViewResponse $viewResponse) {
        return $this->fetchViewTemplate($viewResponse->getView(), $viewResponse->getTemplateName(), $viewResponse->getData());
    }

    /**
     * @param string $classname
     * @return string
     * @throws CM_Exception_Invalid
     */
    public function getClassnameByPartialClassname($classname) {
        $classname = (string) $classname;
        foreach ($this->getSite()->getModules() as $availableNamespace) {
            $classnameWithNamespace = $availableNamespace . '_' . $classname;
            if (class_exists($classnameWithNamespace)) {
                return $classnameWithNamespace;
            }
        }
        throw new CM_Exception_Invalid('The class was not found in any namespace.', array('name' => $classname));
    }

    /**
     * @return Smarty
     */
    private function _getSmarty() {
        if (!isset(self::$_smarty)) {
            self::$_smarty = new Smarty();
            self::$_smarty->setTemplateDir(DIR_ROOT);
            self::$_smarty->setCompileDir(CM_Bootloader::getInstance()->getDirTmp() . 'smarty/');
            self::$_smarty->_file_perms = 0666;
            self::$_smarty->_dir_perms = 0777;
            self::$_smarty->compile_check = CM_Bootloader::getInstance()->isDebug();
            self::$_smarty->caching = false;
            self::$_smarty->error_reporting = error_reporting();
        }

        $pluginDirs = array(SMARTY_PLUGINS_DIR);
        foreach ($this->getSite()->getModules() as $moduleName) {
            $pluginDirs[] = CM_Util::getModulePath($moduleName) . 'library/' . $moduleName . '/SmartyPlugins';
        }
        self::$_smarty->setPluginsDir($pluginDirs);
        self::$_smarty->loadFilter('pre', 'translate');

        return self::$_smarty;
    }

    /**
     * @param string $phrase
     * @param array  $variables
     * @return string
     */
    private function _parseVariables($phrase, array $variables) {
        return preg_replace_callback('~\{\$(\w+)\}~', function ($matches) use ($variables) {
            return isset($variables[$matches[1]]) ? $variables[$matches[1]] : '';
        }, $phrase);
    }
}
