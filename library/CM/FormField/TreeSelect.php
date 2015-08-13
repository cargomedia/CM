<?php

class CM_FormField_TreeSelect extends CM_FormField_Abstract {

    /** @var CM_Tree_Abstract */
    protected $_tree;

    protected function _initialize() {
        $this->_tree = $this->_params->getObject('tree', 'CM_Tree_Abstract');
        parent::_initialize();
    }

    public function validate(CM_Frontend_Environment $environment, $userInput) {
        if (!$this->_tree->findNodeById($userInput)) {
            throw new CM_Exception_FormFieldValidation(new CM_I18n_Phrase('Invalid value'));
        }
        return $userInput;
    }

    public function prepare(CM_Params $renderParams, CM_Frontend_Environment $environment, CM_Frontend_ViewResponse $viewResponse) {
        $viewResponse->set('tree', $this->_tree);
    }
}
