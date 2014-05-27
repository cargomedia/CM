<?php

class CM_Response_View_Form extends CM_Response_View_Abstract {

    /**
     * @var array
     */
    private $errors = array();

    /**
     * @var array
     */
    private $messages = array();

    /**
     * @param string $message
     * @param string $fieldName
     */
    public function addError($message, $fieldName = null) {
        if (isset($fieldName)) {
            $this->errors[] = array($message, $fieldName);
        } else {
            $this->errors[] = $message;
        }
    }

    /**
     * @param string $message
     */
    public function addMessage($message) {
        $this->messages[] = $message;
    }

    /**
     * @return bool
     */
    public function hasErrors() {
        return (bool) count($this->errors);
    }

    protected function _process() {
        $output = array();
        try {
            $success = array();
            $query = $this->_request->getQuery();
            $formInfo = $this->_getViewInfo('form');

            $className = (string) $formInfo['className'];
            $actionName = (string) $query['actionName'];
            $data = (array) $query['data'];
            $this->_setStringRepresentation($className . '::' . $actionName);

            $form = CM_Form_Abstract::factory($className);
            $form->setup();
            $success['data'] = CM_Params::encode($form->process($data, $actionName, $this));

            if (!empty($this->errors)) {
                $success['errors'] = $this->errors;
            }

            $jsCode = $this->getRender()->getJs()->getJs();
            if (!empty($jsCode)) {
                $success['exec'] = $jsCode;
            }

            if (!empty($this->messages)) {
                $success['messages'] = $this->messages;
            }
            $output['success'] = $success;
        } catch (CM_Exception $e) {
            if (!($e->isPublic() || in_array(get_class($e), self::_getConfig()->catch))) {
                throw $e;
            }
            $output['error'] = array('type' => get_class($e), 'msg' => $e->getMessagePublic($this->getRender()), 'isPublic' => $e->isPublic());
        }

        $this->setHeader('Content-Type', 'application/json');
        $this->_setContent(json_encode($output));
    }

    public static function match(CM_Request_Abstract $request) {
        return $request->getPathPart(0) === 'form';
    }
}

