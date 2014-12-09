<?php

function smarty_function_locationFlag(array $params, Smarty_Internal_Template $template) {
    /** @var CM_Frontend_Render $render */
    $render = $template->smarty->getTemplateVars('render');
    /** @var CM_Model_Location $location */
    $location = $params['location'];

    $html = '';
    if ($abbreviation = $location->getAbbreviation(CM_Model_Location::LEVEL_COUNTRY)) {
        $flagUrl = $render->getUrlResource('layout', 'img/flags/' . strtolower($abbreviation) . '.png');
        $name = $location->getName(CM_Model_Location::LEVEL_COUNTRY);
        $html .= '<img class="flag" src="' . $flagUrl . '" title="' . CM_Util::htmlspecialchars($name) . '" />';
    }

    return $html;
}
