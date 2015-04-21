<?php

class CM_RenderAdapter_Layout extends CM_RenderAdapter_Abstract {

    /** @var CM_RenderAdapter_Page */
    private $_renderAdapterPage;

    /**
     * @param CM_Frontend_Render $render
     * @param CM_Page_Abstract   $page
     */
    public function __construct(CM_Frontend_Render $render, CM_Page_Abstract $page) {
        $this->_renderAdapterPage = new CM_RenderAdapter_Page($render, $page);
        parent::__construct($render, $page);
    }

    /**
     * @return string
     */
    public function fetch() {
        $page = $this->_getPage();
        $layout = $this->_getLayout();

        $page->checkAccessible($this->getRender()->getEnvironment());
        $frontend = $this->getRender()->getGlobalResponse();

        $viewResponse = new CM_Frontend_ViewResponse($layout);
        $viewResponse->setTemplateName('default');
        $layout->prepare($this->getRender()->getEnvironment(), $viewResponse);
        $viewResponse->set('viewResponse', $viewResponse);
        $viewResponse->set('page', $page);
        $viewResponse->set('pageTitle', $this->fetchTitle());
        $viewResponse->set('pageDescription', $this->fetchDescription());
        $viewResponse->set('pageKeywords', $this->fetchKeywords());
        $viewResponse->set('renderAdapter', $this);

        $environmentDefault = new CM_Frontend_Environment($this->getRender()->getEnvironment()->getSite());
        $renderDefault = new CM_Frontend_Render($environmentDefault);
        $viewResponse->set('renderDefault', $renderDefault);
        $viewResponse->set('languageList', new CM_Paging_Language_Enabled());

        $options = array();
        $options['deployVersion'] = CM_App::getInstance()->getDeployVersion();
        $options['renderStamp'] = floor(microtime(true) * 1000);
        $options['site'] = CM_Params::encode($this->getRender()->getSite());
        $options['url'] = $this->getRender()->getSite()->getUrl();
        $options['urlCdn'] = $this->getRender()->getSite()->getUrlCdn();
        $options['urlUserContentList'] = CM_Service_Manager::getInstance()->getUserContent()->getUrlList();
        $options['language'] = $this->getRender()->getLanguage();
        $options['debug'] = CM_Bootloader::getInstance()->isDebug();
        $options['stream'] = array();
        $options['stream']['enabled'] = CM_Stream_Message::getInstance()->getEnabled();
        if (CM_Stream_Message::getInstance()->getEnabled()) {
            $options['stream']['adapter'] = CM_Stream_Message::getInstance()->getAdapterClass();
            $options['stream']['options'] = CM_Stream_Message::getInstance()->getOptions();
        }
        if ($viewer = $this->getRender()->getViewer()) {
            $options['stream']['channel']['key'] = CM_Model_StreamChannel_Message_User::getKeyByUser($viewer);
            $options['stream']['channel']['type'] = CM_Model_StreamChannel_Message_User::getTypeStatic();
        }
        $frontend->getOnloadHeaderJs()->append('cm.options = ' . CM_Params::encode($options, true));

        if ($viewer = $this->getRender()->getViewer()) {
            $frontend->getOnloadHeaderJs()->append('cm.viewer = ' . CM_Params::encode($viewer, true));
        }

        $frontend->treeExpand($viewResponse);

        $frontend->getOnloadReadyJs()->append('cm.getLayout()._ready();');
        $frontend->getOnloadHeaderJs()->append('cm.ready();');
        $html = $this->getRender()->fetchViewResponse($viewResponse);

        $frontend->treeCollapse();

        return $html;
    }

    /**
     * @return string
     */
    public function fetchPage() {
        return $this->_renderAdapterPage->fetch();
    }

    /**
     * @return string
     */
    public function fetchTitle() {
        $pageTitle = $this->_renderAdapterPage->fetchTitle();
        return $this->getRender()->fetchViewTemplate($this->_getLayout(), 'title', array('pageTitle' => $pageTitle));
    }

    /**
     * @return string
     */
    public function fetchDescription() {
        return $this->_renderAdapterPage->fetchDescription();
    }

    /**
     * @return string
     */
    public function fetchKeywords() {
        return $this->_renderAdapterPage->fetchKeywords();
    }

    /**
     * @return CM_Page_Abstract
     */
    private function _getPage() {
        return $this->_getView();
    }

    /**
     * @return CM_Layout_Abstract
     */
    private function _getLayout() {
        $environment = $this->getRender()->getEnvironment();
        return $this->_getPage()->getLayout($environment);
    }
}
