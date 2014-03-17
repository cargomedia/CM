<?php

abstract class CM_Site_Abstract extends CM_Class_Abstract implements CM_ArrayConvertible, CM_Typed {

    protected $_themes = array();
    protected $_namespaces = array();
    /**
     * @var CM_EventHandler_EventHandler
     */
    protected $_eventHandler = null;

    /**
     * Default constructor to set CM namespace
     */
    public function __construct() {
        $this->_setNamespace('CM');
    }

    /**
     * @return CM_Site_Abstract[]
     */
    public static function getAll() {
        $siteList = array();
        foreach (CM_Config::get()->CM_Site_Abstract->types as $className) {
            $siteList[] = new $className();
        }
        return $siteList;
    }

    /**
     * @return string
     */
    public function getEmailAddress() {
        return self::_getConfig()->emailAddress;
    }

    /**
     * @return CM_EventHandler_EventHandler
     */
    public function getEventHandler() {
        if (!$this->_eventHandler) {
            $this->_eventHandler = new CM_EventHandler_EventHandler();
            $this->bindEvents($this->_eventHandler);
        }
        return $this->_eventHandler;
    }

    /**
     * @param CM_EventHandler_EventHandler $eventHandler
     */
    public function bindEvents(CM_EventHandler_EventHandler $eventHandler) {
    }

    /**
     * @return string
     */
    public function getNamespace() {
        return $this->_namespaces[0];
    }

    /**
     * @return string[]
     */
    public function getNamespaces() {
        return $this->_namespaces;
    }

    /**
     * @return CM_Menu[]
     */
    public function getMenus() {
        return array();
    }

    /**
     * @return string
     */
    public function getName() {
        return self::_getConfig()->name;
    }

    /**
     * @return string Theme
     */
    public function getTheme() {
        return $this->_themes[0];
    }

    /**
     * @return string[]
     */
    public function getThemes() {
        return $this->_themes;
    }

    /**
     * @return string
     */
    public function getUrlCdn() {
        return self::_getConfig()->urlCdn;
    }

    /**
     * @return string
     */
    public function getUrl() {
        return self::_getConfig()->url;
    }

    /**
     * @return string
     * @throws CM_Exception_Invalid
     */
    public function getHost() {
        $siteHost = parse_url($this->getUrl(), PHP_URL_HOST);
        if (false === $siteHost || null === $siteHost) {
            throw new CM_Exception_Invalid('Cannot detect host from `' . $this->getUrl() . '`.');
        }
        return $siteHost;
    }

    /**
     * @param CM_Response_Page $response
     */
    public function preprocessPageResponse(CM_Response_Page $response) {
    }

    /**
     * @param CM_Request_Abstract $request
     */
    public function rewrite(CM_Request_Abstract $request) {
        if ($request->getPath() == '/') {
            $request->setPath('/index');
        }
    }

    public function toArray() {
        return array('type' => $this->getType());
    }

    /**
     * @param string $theme
     * @return CM_Site_Abstract
     */
    protected function _addTheme($theme) {
        array_unshift($this->_themes, (string) $theme);
        return $this;
    }

    /**
     * @param string $namespace
     * @return CM_Site_Abstract
     */
    protected function _setNamespace($namespace) {
        array_unshift($this->_namespaces, (string) $namespace);
        // Resets themes if new namespace is set
        $this->_themes = array('default');
        return $this;
    }

    /**
     * @param CM_Request_Abstract $request
     * @return boolean
     */
    public function match(CM_Request_Abstract $request) {
        $urlRequest = $request->getHost();
        $urlSite = $this->getHost();
        return 0 === strpos(preg_replace('/^www\./', '', $urlRequest), preg_replace('/^www\./', '', $urlSite));
    }

    /**
     * @param int|null $type
     * @return CM_Site_Abstract
     * @throws CM_Class_Exception_TypeNotConfiguredException
     */
    public static function factory($type = null) {
        try {
            $class = self::_getClassName($type);
        } catch (CM_Class_Exception_TypeNotConfiguredException $ex) {
            throw new CM_Class_Exception_TypeNotConfiguredException('Site with type `' . $type . '` not configured', null, null, CM_Exception::WARN);
        }
        return new $class();
    }

    /**
     * @return int Site id
     */
    public function getId() {
        return $this->getType();
    }

    /**
     * @param CM_Request_Abstract $request
     * @return CM_Site_Abstract
     * @throws CM_Exception_Invalid
     */
    public static function findByRequest(CM_Request_Abstract $request) {
        foreach (array_reverse(static::getClassChildren()) as $className) {
            /** @var CM_Site_Abstract $site */
            $site = new $className();
            if ($site->match($request)) {
                return $site;
            }
        }
        return self::factory();
    }

    public static function fromArray(array $array) {
        $type = (int) $array['type'];
        return self::factory($type);
    }
}
