<?php

class CM_Frontend_ViewResponse extends CM_DataResponse {

    /** @var string|null */
    protected $_autoId;

    /** @var string */
    protected $_templateName;

    /** @var string[] */
    protected $_cssClasses;

    /** @var CM_View_Abstract */
    protected $_view;

    /** @var CM_Frontend_JavascriptContainer_View */
    protected $_js;

    /** @var array */
    protected $_dataAttributes;

    /**
     * @param CM_View_Abstract $view
     */
    public function __construct(CM_View_Abstract $view) {
        $this->_templateName = 'default';
        $this->_cssClasses = [];
        $this->_view = $view;
        $this->_dataAttributes = [];
        $this->_js = new CM_Frontend_JavascriptContainer_View();
    }

    /**
     * @param string $tagName
     * @return string
     */
    public function getAutoIdTagged($tagName) {
        return $this->getAutoId() . '-' . $tagName;
    }

    /**
     * @return string
     */
    public function getAutoId() {
        if (!$this->_autoId) {
            $this->_autoId = uniqid();
        }
        return $this->_autoId;
    }

    /**
     * @param string $name
     * @throws CM_Exception_Invalid
     */
    public function setTemplateName($name) {
        if (preg_match('/[^\w\.-]/', $name)) {
            throw new CM_Exception_Invalid('Invalid tpl-name', null, ['name' => $name]);
        }
        $this->_templateName = $name;
    }

    /**
     * @return string
     */
    public function getTemplateName() {
        return $this->_templateName;
    }

    /**
     * @param string $name
     */
    public function addCssClass($name) {
        $this->_cssClasses[] = (string) $name;
    }

    /**
     * @return string[]
     */
    public function getCssClasses() {
        $cssClasses = array_merge($this->getView()->getClassHierarchy(), $this->_cssClasses);
        $templateName = $this->getTemplateName();
        if ('default' !== $templateName) {
            $cssClasses[] = $templateName;
        }
        $cssClasses = array_unique($cssClasses);
        return $cssClasses;
    }

    /**
     * @param string[] $data
     */
    public function setDataAttributes(array $data) {
        $this->_dataAttributes = $data;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addDataAttribute($key, $value) {
        $this->_dataAttributes[(string) $key] = (string) $value;
    }

    /**
     * @return string[]
     */
    public function getDataAttributes() {
        return $this->_dataAttributes;
    }

    /**
     * @return CM_View_Abstract
     */
    public function getView() {
        return $this->_view;
    }

    /**
     * @return CM_Frontend_JavascriptContainer_View
     */
    public function getJs() {
        return $this->_js;
    }
}
