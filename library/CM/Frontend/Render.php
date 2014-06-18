<?php

class CM_Frontend_Render extends CM_Class_Abstract {

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
     * @param CM_Site_Abstract|null  $site
     * @param CM_Model_User|null     $viewer
     * @param CM_Model_Language|null $language
     * @param boolean|null           $languageRewrite
     * @param CM_Model_Location|null $location
     */
    public function __construct(CM_Site_Abstract $site = null, CM_Model_User $viewer = null, CM_Model_Language $language = null, $languageRewrite = null, CM_Model_Location $location = null) {
        if (!$language) {
            $language = CM_Model_Language::findDefault();
        }
        $environment = new CM_Frontend_Environment();
        if ($site) {
            $environment->setSite($site);
        }
        if ($viewer) {
            $environment->setViewer($viewer);
        }
        if ($language) {
            $environment->setLanguage($language);
        }
        if ($location) {
            $environment->setLocation($location);
        }
        $this->_environment = $environment;
        $this->_languageRewrite = (bool) $languageRewrite;
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
     * @param string     $path
     * @param array|null $variables
     * @return string
     */
    public function fetchTemplate($path, array $variables = null) {
        $compileId = $this->getSite()->getId();
        if ($this->getLanguage()) {
            $compileId .= '_' . $this->getLanguage()->getAbbreviation();
        }
        /** @var Smarty_Internal_TemplateBase $template */
        $template = $this->_getSmarty()->createTemplate($path, null, $compileId);
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
            $namespace = $this->getSite()->getNamespace();
        }

        $path = CM_Util::getNamespacePath($namespace, !$absolute);
        return $path . 'layout/' . $theme . '/';
    }

    /**
     * @param string      $tpl Template file name
     * @param string|null $namespace
     * @param bool|null   $absolute
     * @param bool|null   $needed
     * @return string Layout path based on theme
     * @throws CM_Exception_Invalid
     */
    public function getLayoutPath($tpl, $namespace = null, $absolute = null, $needed = true) {
        $namespaceList = $this->getSite()->getNamespaces();
        if ($namespace !== null) {
            $namespaceList = array((string) $namespace);
        }
        foreach ($namespaceList as $namespace) {
            foreach ($this->getSite()->getThemes() as $theme) {
                $file = new CM_File($this->getThemeDir(true, $theme, $namespace) . $tpl);
                if ($file->getExists()) {
                    if ($absolute) {
                        return $file->getPath();
                    } else {
                        return $this->getThemeDir(false, $theme, $namespace) . $tpl;
                    }
                }
            }
        }

        if ($needed) {
            throw new CM_Exception_Invalid('Cannot find `' . $tpl . '` in namespaces `' . implode('`, `', $namespaceList) . '` and themes `' .
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
        return new CM_File($this->getLayoutPath($path, $namespace, true));
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
        if (!in_array($namespace, $site->getNamespaces())) {
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
     * @return string
     */
    public function getUrlResource($type = null, $path = null) {
        $urlPath = '';
        if (!is_null($type) && !is_null($path)) {
            $type = (string) $type;
            $path = (string) $path;
            $urlPath .= '/' . $type;
            if ($this->getLanguage()) {
                $urlPath .= '/' . $this->getLanguage()->getAbbreviation();
            }
            $urlPath .= '/' . $this->getSite()->getId() . '/' . CM_App::getInstance()->getDeployVersion() . '/' . $path;
        }
        return $this->getSite()->getUrlCdn() . $urlPath;
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
        $urlPath = '/static';
        if (null !== $path) {
            $urlPath .= $path . '?' . CM_App::getInstance()->getDeployVersion();
        }
        return $this->getSite()->getUrlCdn() . $urlPath;
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
     * @return CM_Model_Language|null
     */
    public function getLanguage() {
        return $this->getEnvironment()->getLanguage();
    }

    /**
     * @param string     $key
     * @param array|null $params
     * @return string
     */
    public function getTranslation($key, array $params = null) {
        $params = (array) $params;
        $translation = $key;
        if ($this->getLanguage()) {
            $translation = $this->getLanguage()->getTranslation($key, array_keys($params));
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
            if ($templatePath = $this->getLayoutPath($templatePathRelative, $namespace, false, false)) {
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
        return $this->fetchTemplate($templatePath, $data);
    }

    /**
     * @param CM_Frontend_ViewResponse $viewResponse
     * @return string
     */
    public function fetchViewResponse(CM_Frontend_ViewResponse $viewResponse) {
        return $this->fetchViewTemplate($viewResponse->getView(), $viewResponse->getTemplateName(), $viewResponse->getData());
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
        foreach ($this->getSite()->getNamespaces() as $namespace) {
            $pluginDirs[] = CM_Util::getNamespacePath($namespace) . 'library/' . $namespace . '/SmartyPlugins';
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
