<?php

abstract class CM_Http_Response_View_Abstract extends CM_Http_Response_Abstract {

    /**
     * @param array $output
     * @return array
     */
    abstract protected function _processView(array $output);

    /**
     * @param array|null $additionalParams
     */
    public function reloadComponent(array $additionalParams = null) {
        $componentInfo = $this->_getViewInfo('CM_Component_Abstract');
        $componentId = $componentInfo['id'];
        $componentClassName = $componentInfo['className'];
        $componentParams = CM_Params::factory($componentInfo['params']);

        if ($additionalParams) {
            foreach ($additionalParams as $key => $value) {
                $componentParams->set($key, $value);
            }
        }
        $component = CM_Component_Abstract::factory($componentClassName, $componentParams);
        $renderAdapter = CM_RenderAdapter_Component::factory($this->getRender(), $component);
        $html = $renderAdapter->fetch();

        $frontend = $this->getRender()->getGlobalResponse();
        $autoId = $frontend->getTreeRoot()->getValue()->getAutoId();

        $componentReferenceOld = 'cm.views["' . $componentId . '"]';
        $componentReferenceNew = 'cm.views["' . $autoId . '"]';
        $frontend->getOnloadHeaderJs()->append('cm.window.appendHidden(' . json_encode($html) . ');');
        $frontend->getOnloadPrepareJs()->append($componentReferenceOld . '.getParent().registerChild(' . $componentReferenceNew . ');');
        $frontend->getOnloadPrepareJs()->append($componentReferenceOld . '.replaceWithHtml(' . $componentReferenceNew . '.$el);');
        $frontend->getOnloadReadyJs()->append('cm.views["' . $autoId . '"]._ready();');
    }

    /**
     * @param CM_Params $params
     * @return array
     */
    public function loadComponent(CM_Params $params) {
        $component = CM_Component_Abstract::factory($params->getString('className'), $params);
        $renderAdapter = new CM_RenderAdapter_Component($this->getRender(), $component);
        $html = $renderAdapter->fetch();

        $frontend = $this->getRender()->getGlobalResponse();
        $data = array(
            'autoId' => $frontend->getTreeRoot()->getValue()->getAutoId(),
            'html'   => $html,
            'js'     => $frontend->getJs(),
        );
        $frontend->clear();
        return $data;
    }

    /**
     * @param CM_Params                  $params
     * @param CM_Http_Response_View_Ajax $response
     * @throws CM_Exception_Invalid
     * @return array
     */
    public function loadPage(CM_Params $params, CM_Http_Response_View_Ajax $response) {
        $request = new CM_Http_Request_Get($params->getString('path'), $this->getRequest()->getHeaders(), $this->getRequest()->getServer(), $this->getRequest()->getViewer());

        $count = 0;
        $paths = array($request->getPath());
        do {
            $url = $this->getRender()->getUrl(CM_Util::link($request->getPath(), $request->getQuery()));
            if ($count++ > 10) {
                throw new CM_Exception_Invalid('Page redirect loop detected (' . implode(' -> ', $paths) . ').');
            }
            $responsePage = new CM_Http_Response_Page_Embed($request, $this->getServiceManager());
            $responsePage->process();
            $request = $responsePage->getRequest();

            $paths[] = $request->getPath();

            if ($redirectUrl = $responsePage->getRedirectUrl()) {
                $redirectExternal = (0 !== mb_stripos($redirectUrl, $this->getRender()->getUrl()));
                if ($redirectExternal) {
                    return array('redirectExternal' => $redirectUrl);
                }
            }
        } while ($redirectUrl);

        foreach ($responsePage->getCookies() as $name => $cookieParameters) {
            $response->setCookie($name, $cookieParameters['value'], $cookieParameters['expire'], $cookieParameters['path']);
        }
        $page = $responsePage->getPage();

        $this->_setStringRepresentation(get_class($page));

        $frontend = $responsePage->getRender()->getGlobalResponse();
        $html = $responsePage->getContent();
        $js = $frontend->getJs();
        $autoId = $frontend->getTreeRoot()->getValue()->getAutoId();

        $frontend->clear();

        $title = $responsePage->getTitle();
        $layoutClass = get_class($page->getLayout($this->getRender()->getEnvironment()));
        $menuList = array_merge($this->getSite()->getMenus(), $responsePage->getRender()->getMenuList());
        $menuEntryHashList = $this->_getMenuEntryHashList($menuList, get_class($page), $page->getParams());
        $jsTracking = $responsePage->getRender()->getServiceManager()->getTrackings()->getJs();

        return array(
            'autoId'            => $autoId,
            'html'              => $html,
            'js'                => $js,
            'title'             => $title,
            'url'               => $url,
            'layoutClass'       => $layoutClass,
            'menuEntryHashList' => $menuEntryHashList,
            'jsTracking'        => $jsTracking,
        );
    }

    public function popinComponent() {
        $componentInfo = $this->_getViewInfo('CM_Component_Abstract');
        $this->getRender()->getGlobalResponse()->getOnloadJs()->append('cm.views["' . $componentInfo['id'] . '"].popIn();');
    }

    /**
     * Add a reload to the response.
     */
    public function reloadPage() {
        $this->getRender()->getGlobalResponse()->getOnloadJs()->append('window.location.reload(true)');
    }

    /**
     * @param CM_Page_Abstract|string $page
     * @param array|null              $params
     * @param boolean|null            $forceReload
     *
     */
    public function redirect($page, array $params = null, $forceReload = null) {
        $forceReload = (boolean) $forceReload;
        $url = $this->getRender()->getUrlPage($page, $params);
        $this->redirectUrl($url, $forceReload);
    }

    /**
     * @param string       $url
     * @param boolean|null $forceReload
     */
    public function redirectUrl($url, $forceReload = null) {
        $url = (string) $url;
        $forceReload = (boolean) $forceReload;
        $js = 'cm.router.route(' . json_encode($url) . ', ' . json_encode($forceReload) . ');';
        $this->getRender()->getGlobalResponse()->getOnloadPrepareJs()->append($js);
    }

    protected function _process() {
        $output = array();
        $this->_runWithCatching(function () use (&$output) {
            $output = $this->_processView($output);
        }, function (CM_Exception $e, array $errorOptions) use (&$output) {
            $output['error'] = array('type' => get_class($e), 'msg' => $e->getMessagePublic($this->getRender()), 'isPublic' => $e->isPublic());
        });

        $this->setHeader('Content-Type', 'application/json');
        $this->_setContent(json_encode($output));
    }

    /**
     * @param string|null $className
     * @return CM_View_Abstract
     */
    protected function _getView($className = null) {
        if (null === $className) {
            $className = 'CM_View_Abstract';
        }
        $viewInfo = $this->_getViewInfo($className);
        /** @var CM_View_Abstract $className */
        return $className::factory($viewInfo['className'], CM_Params::factory($viewInfo['params'], true));
    }

    /**
     * @param string|null $className
     * @return array
     * @throws CM_Exception_Invalid
     */
    protected function _getViewInfo($className = null) {
        if (null === $className) {
            $className = 'CM_View_Abstract';
        }
        $query = $this->_request->getQuery();
        if (!array_key_exists('viewInfoList', $query)) {
            throw new CM_Exception_Invalid('viewInfoList param not found.', null, array('severity' => CM_Exception::WARN));
        }
        $viewInfoList = $query['viewInfoList'];
        if (!array_key_exists($className, $viewInfoList)) {
            throw new CM_Exception_Invalid('View `' . $className . '` not set.', null, array('severity' => CM_Exception::WARN));
        }
        $viewInfo = $viewInfoList[$className];
        if (!is_array($viewInfo)) {
            throw new CM_Exception_Invalid('View `' . $className . '` is not an array', null, array('severity' => CM_Exception::WARN));
        }
        if (!isset($viewInfo['id'])) {
            throw new CM_Exception_Invalid('View id `' . $className . '` not set.', null, array('severity' => CM_Exception::WARN));
        }
        if (!isset($viewInfo['className']) || !class_exists($viewInfo['className']) || !is_a($viewInfo['className'], 'CM_View_Abstract', true)) {
            throw new CM_Exception_Invalid('View className `' . $className . '` is illegal: `' . $viewInfo['className'] .
                '`.', null, array('severity' => CM_Exception::WARN));
        }
        if (!isset($viewInfo['params'])) {
            throw new CM_Exception_Invalid('View params `' . $className . '` not set.', null, array('severity' => CM_Exception::WARN));
        }
        if (!isset($viewInfo['parentId'])) {
            $viewInfo['parentId'] = null;
        }
        return array(
            'id'        => (string) $viewInfo['id'],
            'className' => (string) $viewInfo['className'],
            'params'    => (array) $viewInfo['params'],
            'parentId'  => (string) $viewInfo['parentId']
        );
    }

    /**
     * @param CM_Menu[] $menuList
     * @param string    $pageName
     * @param CM_Params $pageParams
     * @return string[]
     */
    private function _getMenuEntryHashList(array $menuList, $pageName, CM_Params $pageParams) {
        $pageName = (string) $pageName;
        $menuEntryHashList = array();
        foreach ($menuList as $menu) {
            if (is_array($menuEntries = $menu->findEntries($pageName, $pageParams))) {
                foreach ($menuEntries as $menuEntry) {
                    $menuEntryHashList[] = $menuEntry->getHash();
                    foreach ($menuEntry->getParents() as $parentEntry) {
                        $menuEntryHashList[] = $parentEntry->getHash();
                    }
                }
            }
        }
        return $menuEntryHashList;
    }
}
