<?php

function smarty_function_component(array $params, Smarty_Internal_Template $template) {

    /** @var CM_Frontend_Render $render */
    $render = $template->smarty->getTemplateVars('render');

    if (empty($params['name'])) {
        trigger_error('Param `name` missing.');
    }
    $classname = null;
    $name = $params['name'];
    unset($params['name']);

    if (0 === strpos($name, '*_')) {
        $name = $render->getClassnameByPartialClassname(mb_substr($name, 2));
    }

    $component = CM_Component_Abstract::factory($name, $params);
    $renderAdapter = CM_RenderAdapter_Component::factory($render, $component);
    return $renderAdapter->fetch();
}
