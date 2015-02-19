<?php

class CM_FormField_Set_Select_Radio extends CM_FormField_Set_Select {

    public function validate(CM_Frontend_Environment $environment, $userInput) {
        return $userInput;
    }

    public function prepare(CM_Params $renderParams, CM_Frontend_Environment $environment, CM_Frontend_ViewResponse $viewResponse) {
        $viewResponse->set('itemValue', $renderParams->get('item'));
    }
}
