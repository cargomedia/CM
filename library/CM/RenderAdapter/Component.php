<?php

class CM_RenderAdapter_Component extends CM_RenderAdapter_Abstract {

    /**
     * @return string
     */
    public function fetch() {
        $frontend = $this->getRender()->getFrontend();
        $renderEnvironment = $this->getRender()->getEnvironment();
        $this->_getComponent()->checkAccessible($renderEnvironment);

        $frontendHandler = new CM_ViewFrontendHandler();
        $viewResponse = $this->_getPreparedViewResponse($renderEnvironment, $frontendHandler);
        $viewResponse->set('viewObj', $this->_getComponent());

        $frontend->treeExpand($viewResponse);

        $cssClass = implode(' ', $this->_getComponent()->getClassHierarchy());
        if (preg_match('#([^/]+)\.tpl$#', $viewResponse->getTemplateName(), $match)) {
            if ($match[1] != 'default') {
                $cssClass .= ' ' . $match[1]; // Include special-tpl name in class (e.g. 'mini')
            }
        }
        $html = '<div id="' . $viewResponse->getAutoId() . '" class="' . $cssClass . '">';
        $html .=  $this->getRender()->fetchViewResponse($viewResponse);
        $html .= '</div>';

        $frontend->registerViewResponse($viewResponse, $frontendHandler);

        $frontend->treeCollapse();
        return $html;
    }

    /**
     * @return string
     */
    protected function _getStackKey() {
        return 'components';
    }

    /**
     * @param CM_RenderEnvironment        $environment
     * @param CM_ViewFrontendHandler $frontendHandler
     * @return CM_ViewResponse
     */
    protected function _getPreparedViewResponse(CM_RenderEnvironment $environment, CM_ViewFrontendHandler $frontendHandler) {
        $component = $this->_getComponent();
        $viewResponse = new CM_ViewResponse($component);
        $viewResponse->setTemplateName('default');
        $component->prepare($environment, $viewResponse, $frontendHandler);
        return $viewResponse;
    }

    /**
     * @return CM_Component_Abstract
     */
    private function _getComponent() {
        return $this->_getView();
    }
}
