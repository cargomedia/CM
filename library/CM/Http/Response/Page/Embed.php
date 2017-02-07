<?php

class CM_Http_Response_Page_Embed extends CM_Http_Response_Page {

    /** @var string|null */
    private $_title;

    /**
     * @throws CM_Exception_Invalid
     * @return string
     */
    public function getTitle() {
        if (null === $this->_title) {
            throw new CM_Exception_Invalid('Unprocessed page has no title');
        }
        return $this->_title;
    }

    /**
     * @param CM_Page_Abstract $page
     * @return string
     */
    protected function _renderPage(CM_Page_Abstract $page) {
        $renderAdapterPage = new CM_RenderAdapter_Page($this->getRender(), $page);
        $this->_title = $renderAdapterPage->fetchTitleWithBranding();
        return $renderAdapterPage->fetch();
    }

    public static function createFromRequest(CM_Http_Request_Abstract $request, CM_Site_Abstract $site, CM_Service_Manager $serviceManager) {
        return null;
    }

}
