<?php

class CM_Component_LogList extends CM_Component_Abstract {

    public function checkAccessible(CM_Frontend_Environment $environment) {
        if (!CM_Bootloader::getInstance()->isDebug()) {
            throw new CM_Exception_NotAllowed();
        }
    }

    public function prepare(CM_Frontend_Environment $environment, CM_Frontend_ViewResponse $viewResponse) {
        $type = $this->_params->getInt('type');
        $aggregate = $this->_params->has('aggregate') ? $this->_params->getInt('aggregate') : null;
        $urlPage = $this->_params->has('urlPage') ? $this->_params->getString('urlPage') : null;
        $urlParams = $this->_params->has('urlParams') ? $this->_params->getArray('urlParams') : null;

        $aggregationPeriod = $aggregate;
        if (0 === $aggregationPeriod) {
            $deployStamp = CM_App::getInstance()->getDeployVersion();
            $aggregationPeriod = time() - $deployStamp;
        }
        $logList = CM_Paging_Log_Abstract::factory($type, (bool) $aggregationPeriod, $aggregationPeriod);
        $logList->setPage($this->_params->getPage(), $this->_params->getInt('count', 50));

        $viewResponse->setData([
            'type'                  => $type,
            'logList'               => $logList,
            'aggregate'             => $aggregate,
            'aggregationPeriod'     => $aggregationPeriod,
            'aggregationPeriodList' => array(3600, 86400, 7 * 86400, 31 * 86400),
            'urlPage'               => $urlPage,
            'urlParams'             => $urlParams
        ]);
        $viewResponse->getJs()->setProperty('type', $type);
    }

    public function ajax_flushLog(CM_Params $params, CM_Frontend_JavascriptContainer $handler, CM_Http_Response_View_Ajax $response) {
        if (!$this->_getAllowedFlush($response->getRender()->getEnvironment())) {
            throw new CM_Exception_NotAllowed();
        }
        $type = $params->getInt('type');
        $logList = CM_Paging_Log_Abstract::factory($type);
        $logList->flush();

        $response->reloadComponent();
    }

    /**
     * @param CM_Frontend_Environment $environment
     * @return bool
     */
    protected function _getAllowedFlush(CM_Frontend_Environment $environment) {
        return CM_Bootloader::getInstance()->isDebug();
    }
}
