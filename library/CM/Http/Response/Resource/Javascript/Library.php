<?php

class CM_Http_Response_Resource_Javascript_Library extends CM_Http_Response_Resource_Javascript_Abstract {

    protected function _process() {
        if ($this->getRequest()->getPath() === '/library.js') {
            $query = $this->getRequest()->getQuery();
            $skipLibraries = !empty($query['debug']);
            if ($skipLibraries) {
                $this->_setAsset(new CM_Asset_Javascript_Internal($this->getSite()));
            } else {
                $this->_setAsset(new CM_Asset_Javascript_Library($this->getSite()));
            }
            return;
        }
        if ($this->getRequest()->getPathPart(0) === 'translations') {
            $language = $this->getRender()->getLanguage();
            if (!$language) {
                throw new CM_Exception_Invalid('Render has no language');
            }
            $this->_setAsset(new CM_Asset_Javascript_Translations($language));
            return;
        }
        throw new CM_Exception_Invalid('Invalid path `' . $this->getRequest()->getPath() . '` provided', null, array('severity' => CM_Exception::WARN));
    }

    public static function match(CM_Http_Request_Abstract $request) {
        return $request->getPathPart(0) === 'library-js';
    }
}
