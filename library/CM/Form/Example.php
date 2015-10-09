<?php

class CM_Form_Example extends CM_Form_Abstract {

    protected function _initialize() {
        $this->registerField(new CM_FormField_Text(['name' => 'text']));
        $this->registerField(new CM_FormField_Email(['name' => 'email']));
        $this->registerField(new CM_FormField_Password(['name' => 'password']));
        $this->registerField(new CM_FormField_Textarea(['name' => 'textarea']));
        $this->registerField(new CM_FormField_Float(['name' => 'float']));
        $this->registerField(new CM_FormField_Money(['name' => 'money']));
        $this->registerField(new CM_FormField_Url(['name' => 'url']));
        $this->registerField(new CM_FormField_Integer(['name' => 'int', 'min' => -10, 'max' => 20, 'step' => 2]));
        $this->registerField(new CM_FormField_Distance(['name' => 'locationSlider']));
        $this->registerField(new CM_FormField_Location(['name' => 'location', 'fieldNameDistance' => 'locationSlider']));
        $this->registerField(new CM_FormField_File(['name' => 'file', 'cardinality' => 2]));
        $this->registerField(new CM_FormField_FileImage(['name' => 'image', 'cardinality' => 2]));
        $this->registerField(new CM_FormField_Color(['name' => 'color']));
        $this->registerField(new CM_FormField_Date(['name' => 'date']));
        $this->registerField(new CM_FormField_Birthdate(['name' => 'birthdate', 'minAge' => 18, 'maxAge' => 30]));
        $this->registerField(new CM_FormField_GeoPoint(['name' => 'geopoint']));
        $this->registerField(new CM_FormField_Set(['name' => 'set', 'values' => array(1 => 'Eins', 2 => 'Zwei'), 'labelsInValues' => true]));
        $this->registerField(new CM_FormField_Boolean(['name' => 'boolean']));
        $this->registerField(new CM_FormField_Boolean(['name' => 'booleanSwitch']));
        $this->registerField(new CM_FormField_Set_Select(['name' => 'setSelect1', 'values' => [1 => 'Eins', 2 => 'Zwei'], 'labelsInValues' => true]));
        $this->registerField(new CM_FormField_Set_Select(['name' => 'setSelect2', 'values' => [1 => 'Eins', 2 => 'Zwei'], 'labelsInValues' => true]));
        $this->registerField(new CM_FormField_Set_Select(['name' => 'setSelect3', 'values' => [1 => 'Foo', 2 => 'Bar'], 'labelsInValues' => true]));
        $this->registerField(new CM_FormField_TreeSelect(['name' => 'treeselect', 'tree' => CM_Model_LanguageKey::getTree()]));
    }

    public function prepare(CM_Frontend_Environment $environment, CM_Frontend_ViewResponse $viewResponse) {
        if (CM_Http_Request_Abstract::hasInstance()) {
            $ip = CM_Http_Request_Abstract::getInstance()->getIp();
            if ($locationGuess = CM_Model_Location::findByIp($ip)) {
                $this->getField('location')->setValue($locationGuess);
            }
        }
    }

    public function ajax_validate(CM_Params $params, CM_Frontend_JavascriptContainer_View $handler, CM_Http_Response_View_Ajax $response) {
        $data = $params->getArray('data');
        $result = [
            'valid' => [],
            'invalid' => [],
        ];
        foreach ($data as $name => $value) {
            try {
                $result['valid'][$name] = $this->getField($name)->validate($response->getEnvironment(), $value);
            } catch (Exception $e) {
                $result['invalid'][$name] = get_class($e) . ': ' . $e->getMessage();
            }
        }
        return $result;
    }
}
