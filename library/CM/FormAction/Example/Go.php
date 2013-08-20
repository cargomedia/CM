<?php

class CM_FormAction_Example_Go extends CM_FormAction_Abstract {

	protected function _getRequiredFields() {
		return array('text', 'color');
	}

	protected function _process(CM_Params $params, CM_Response_View_Form $response, CM_Form_Abstract $form) {
		//$response->reloadComponent();
		$response->addMessage(nl2br($this->_printVar($params->getAll())));
	}

	private function _printVar($var) {
		$str = '';
		if (is_object($var)) {
			$str .= get_class($var);
		} elseif (is_array($var)) {
			$str .= '{';
			if (!empty($var)) {
				$str .= PHP_EOL;
			}
			foreach ($var as $key => $value) {
				$str .= $key . ': ' . $this->_printVar($value) . PHP_EOL;
			}
			$str .= '}';
		} else {
			$str .= (string) $var;
		}
		return $str;
	}
}
