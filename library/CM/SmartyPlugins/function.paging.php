<?php

function smarty_function_paging(array $params, Smarty_Internal_Template $template) {
	/** @var CM_Render $render */
	$render = $template->smarty->getTemplateVars('render');
	/** @var CM_Model_User $viewer */
	$viewer = $template->smarty->getTemplateVars('viewer');
	/** @var CM_Page_Abstract $page */
	$page = $render->getStackLast('pages');
	$path = $page ? $page::getPath() : '/';
	$query = $page ? $page->getParams()->getAllOriginal() : array();
	$component = $render->getStackLast('components');

	if (!isset($params['paging'])) {
		trigger_error('Parameter `paging` missing');
	}
	/** @var CM_Paging_Abstract $paging */
	$paging = $params['paging'];
	$ajax = !empty($params['ajax']);
	$size = 5;

	if ($paging->getPageCount() <= 1) {
		return '';
	}

	$html = '';
	$html .= '<div class="paging"><div class="paging-inner">';

	$boundDistMin = min($paging->getPage() - 1, $paging->getPageCount() - $paging->getPage());
	$sizeMax = $size - min($boundDistMin, floor($size / 2)) - 1;
	$pageMin = max(1, $paging->getPage() - $sizeMax);
	$pageMax = min($paging->getPageCount(), ($paging->getPage() + $sizeMax));

	if ($paging->getPage() > 1) {
		if ($pageMin > 1) {
			$html .= _smarty_function_paging_link($path, $query, $component, 1, '1', $ajax, 'pagingFirst');
		}
		$html .= _smarty_function_paging_link($path, $query, $component, $paging->getPage() - 1, '', $ajax, 'pagingPrev icon-arrow-left');
	}

	for ($p = $pageMin; $p <= $pageMax; $p++) {
		$classActive = ($p == $paging->getPage()) ? 'active' : '';
		$html .= _smarty_function_paging_link($path, $query, $component, $p, $p, $ajax, 'pagingNumber ' . $classActive);
	}

	if ($paging->getPage() < $paging->getPageCount()) {
		$html .= _smarty_function_paging_link($path, $query, $component, $paging->getPage() + 1, '', $ajax, 'pagingNext icon-arrow-right');
		if ($pageMax < $paging->getPageCount()) {
			$html .= _smarty_function_paging_link($path, $query, $component, $paging->getPageCount(), $paging->getPageCount(), $ajax, 'pagingLast');
		}
	}

	$html .= '</div></div>';

	return $html;
}

/**
 * @param string                $path
 * @param array                 $query
 * @param CM_Component_Abstract $component
 * @param int                   $page
 * @param string                $text
 * @param bool                  $ajax
 * @param string|null           $class
 * @return string
 */
function _smarty_function_paging_link($path, array $query, CM_Component_Abstract $component, $page, $text, $ajax, $class = null) {
	$path = (string) $path;
	if ($ajax) {
		$href = 'javascript:;';
		$onClick = 'cm.views["' . $component->getAutoId() . '"].reload(' . json_encode(array('page' => $page)) . ')';
	} else {
		$href = CM_Util::link($path, array_merge($query, array('page' => $page)));
		$onClick = null;
	}
	$html = '<a href="' . $href . '"';
	if ($onClick) {
		$html .= ' onclick="' . htmlentities($onClick) . ';return false;"';
	}
	if ($class) {
		$html .= ' class="' . $class . '"';
	}
	$html .= '>' . $text . '</a>';
	return $html;
}
