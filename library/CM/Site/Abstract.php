<?php

use CM\Url\BaseUrl;

abstract class CM_Site_Abstract extends CM_Class_Abstract implements CM_ArrayConvertible, CM_Typed, CM_Comparable {

    /** @var BaseUrl|null */
    protected $_url = null;

    /** @var BaseUrl|null */
    protected $_urlCdn = null;

    /** @var string[] */
    protected $_themes = array();

    /** @var string[] */
    protected $_modules = array();

    /** @var CM_EventHandler_EventHandler */
    protected $_eventHandler = null;

    /**
     * Default constructor to set CM module
     */
    public function __construct() {
        $this->_setModule('CM');
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
     * @return stdClass
     */
    public function getConfig() {
        return self::_getConfig();
    }

    /**
     * @return string
     */
    public function getEmailAddress() {
        return $this->getConfig()->emailAddress;
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
    public function getModule() {
        return $this->_modules[0];
    }

    /**
     * @return string[]
     */
    public function getModules() {
        return $this->_modules;
    }

    /**
     * @return string
     */
    public function getDocument() {
        return CM_View_Document::class;
    }

    /**
     * @param CM_Frontend_Environment $environment
     * @return CM_Menu[]
     */
    public function getMenus(CM_Frontend_Environment $environment) {
        return array();
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->getConfig()->name;
    }

    /**
     * @return string Theme
     */
    public function getTheme() {
        return $this->getThemes()[0];
    }

    /**
     * @return string[]
     */
    public function getThemes() {
        return $this->_themes;
    }

    /**
     * @return BaseUrl
     */
    public function getUrl() {
        if (null === $this->_url) {
            $this->_url = BaseUrl::create($this->getUrlString());
        }
        return $this->_url;
    }

    /**
     * @return BaseUrl
     */
    public function getUrlCdn() {
        if (null === $this->_urlCdn) {
            $this->_urlCdn = BaseUrl::create($this->getUrlCdnString());
        }
        return $this->_urlCdn;
    }

    /**
     * @return string
     */
    public function getUrlString() {
        return (string) $this->getConfig()->url;
    }

    /**
     * @return string
     */
    public function getUrlCdnString() {
        return (string) $this->getConfig()->urlCdn;
    }

    /**
     * @return array
     */
    public function getWebFontLoaderConfig() {
        $config = $this->getConfig();
        if (!isset($config->webFontLoaderConfig)) {
            return null;
        }
        return $config->webFontLoaderConfig;
    }

    /**
     * @return string
     * @deprecated use getUrl() directly
     */
    public function getHost() {
        return $this->getUrl()->getHost();
    }

    /**
     * @return string
     * @deprecated
     */
    public function getPath() {
        return $this->getUrl()->getUriRelativeComponents();
    }

    /**
     * @param CM_Http_Response_Page $response
     */
    public function preprocessPageResponse(CM_Http_Response_Page $response) {
    }

    /**
     * @param CM_Http_Request_Abstract $request
     */
    public function rewrite(CM_Http_Request_Abstract $request) {
        if ($request->getPath() == '/') {
            $request->rewriteUrl('/index');
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
     * @param string $name
     * @return CM_Site_Abstract
     */
    protected function _setModule($name) {
        array_unshift($this->_modules, (string) $name);
        // Resets themes if new module is set
        $this->_themes = array('default');
        return $this;
    }

    /**
     * @param CM_Http_Request_Abstract $request
     * @return bool
     * @throws CM_Exception
     */
    public function match(CM_Http_Request_Abstract $request) {
        return $this->isUrlMatch($request->getHost(), $request->getPath());
    }

    /**
     * @param string $host
     * @param string $path
     * @return bool
     * @throws CM_Exception
     */
    public function isUrlMatch($host, $path) {
        $matchList = [
            [
                'host' => $this->getHost(),
                'path' => $this->getPath(),
            ],
            [
                'host' => preg_replace('/^www\./', '', $this->getHost()),
                'path' => $this->getPath(),
            ],
        ];

        if ($this->getUrlCdn()) {
            $matchList[] = [
                'host' => $this->getUrlCdn()->getHost(),
                'path' => $this->getUrlCdn()->getPath(),
            ];
        }

        $path = new Stringy\Stringy($path);
        return Functional\some($matchList, function ($match) use ($host, $path) {
            return ($host === $match['host'] && $path->startsWith($match['path']));
        });
    }

    /**
     * @param CM_Site_Abstract $other
     * @return boolean
     */
    public function equals(CM_Comparable $other = null) {
        if (null === $other) {
            return false;
        }
        if (get_class($other) !== get_class($this)) {
            return false;
        }
        return (string) $this->getUrl() === (string) $other->getUrl();
    }

    /**
     * @return int Site id
     */
    public function getId() {
        return $this->getType();
    }

    public static function fromArray(array $array) {
        $type = (int) $array['type'];
        return (new CM_Site_SiteFactory())->getSiteById($type);
    }
}
