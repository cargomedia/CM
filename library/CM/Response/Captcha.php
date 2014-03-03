<?php

class CM_Response_Captcha extends CM_Response_Abstract {

  protected function _process() {
    $params = $this->_request->getQuery();
    $captcha = new CM_Captcha($params['id']);

    $this->setHeader('Content-Type', 'image/png');
    $this->_setContent($captcha->render(200, 40));
  }

  public static function match(CM_Request_Abstract $request) {
    return $request->getPathPart(0) === 'captcha';
  }
}
