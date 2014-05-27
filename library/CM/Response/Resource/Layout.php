<?php

class CM_Response_Resource_Layout extends CM_Response_Resource_Abstract {

    protected function _process() {
        $file = null;
        if ($path = $this->getRender()->getLayoutPath('resource/' . $this->getRequest()->getPath(), null, true, false)) {
            $file = new CM_File($path);
        }
        if (!$file) {
            throw new CM_Exception_Nonexistent('Invalid filename: `' . $this->getRequest()->getPath() . '`', null, array('severity' => CM_Exception::WARN));
        }
        $this->enableCache();
        $this->setHeader('Content-Type', $file->getMimeType());
        $this->_setContent($file->read());
    }

    public static function match(CM_Request_Abstract $request) {
        return $request->getPathPart(0) === 'layout';
    }
}
