<?php

class CM_FormField_Hidden extends CM_FormField_Abstract {

    public function validate(CM_Frontend_Environment $environment, $userInput) {
        return $userInput;
    }
}
