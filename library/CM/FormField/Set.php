<?php

class CM_FormField_Set extends CM_FormField_Abstract {

    /** @var array */
    private $_values;

    /** @var bool */
    private $_labelsInValues;

    /** @var  bool */
    private $_translate;

    protected function _initialize() {
        $this->_values = $this->_params->getArray('values', array());
        $this->_labelsInValues = $this->_params->getBoolean('labelsInValues', false);
        $this->_translate = $this->_params->getBoolean('translate', false);
        parent::_initialize();
    }

    public function validate(CM_Frontend_Environment $environment, $userInput) {
        foreach ($userInput as $key => $value) {
            if (!in_array($value, $this->_getValues())) {
                unset($userInput[$key]);
            }
        }
        return $userInput;
    }

    public function prepare(CM_Params $renderParams, CM_Frontend_Environment $environment, CM_Frontend_ViewResponse $viewResponse) {
        $viewResponse->set('class', $renderParams->has('class') ? $renderParams->getString('class') : null);
        $viewResponse->set('optionList', $this->_getOptionList());
        $viewResponse->set('translate', $renderParams->getBoolean('translate', $this->_translate) || $renderParams->has('translatePrefix'));
        $viewResponse->set('translatePrefix', $renderParams->has('translatePrefix') ? $renderParams->getString('translatePrefix') : null);
    }

    /**
     * @return array
     */
    protected function _getOptionList() {
        if ($this->_labelsInValues || !$this->_values) {
            return $this->_values;
        } else {
            return array_combine($this->_values, $this->_values);
        }
    }

    /**
     * @return array
     */
    protected function _getValues() {
        return array_keys($this->_getOptionList());
    }
}
