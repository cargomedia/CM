<?php
/**
 * @param array                    $params
 * @param Smarty_Internal_Template $template
 * @return string
 */
function smarty_function_advertisement(array $params, Smarty_Internal_Template $template) {
  if (!isset($params['zone'])) {
    trigger_error('Param `zone` missing.');
  }
  $variables = isset($params['variables']) ? $params['variables'] : null;
  return '<div class="advertisement">' . CM_Adprovider::getInstance()->getHtml($params['zone'], $variables) . '</div>';
}
