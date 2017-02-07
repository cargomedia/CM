<?php

class CM_FormField_Distance extends CM_FormField_Slider {

    public function validate(CM_Frontend_Environment $environment, $userInput) {
        return parent::validate($environment, $userInput) * 1609;
    }

    /**
     * @return int External Value
     */
    public function getValue() {
        return parent::getValue() / 1609;
    }
}
