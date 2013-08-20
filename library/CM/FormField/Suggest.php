<?php

abstract class CM_FormField_Suggest extends CM_FormField_Abstract {

	/**
	 * @param int|null  $cardinality
	 * @param bool|null $enableChoiceCreate
	 */
	public function __construct($cardinality = null, $enableChoiceCreate = null) {
		$this->_options['cardinality'] = isset($cardinality) ? ((int) $cardinality) : null;
		$this->_options['enableChoiceCreate'] = isset($enableChoiceCreate) ? ((bool) $enableChoiceCreate) : false;
	}

	/**
	 * @param string    $term
	 * @param array     $options
	 * @param CM_Render $render
	 * @throws CM_Exception_NotImplemented
	 * @return array list(list('id' => $id, 'name' => $name[, 'description' => $description, 'img' => $img, 'class' => string]))
	 */
	protected function _getSuggestions($term, array $options, CM_Render $render) {
		throw new CM_Exception_NotImplemented();
	}

	/**
	 * @param mixed     $item
	 * @param CM_Render $render
	 * @return array list('id' => $id, 'name' => $name[, 'description' => $description, 'img' => $img, 'class' => string])
	 */
	abstract public function getSuggestion($item, CM_Render $render);

	public function prepare(array $params) {
		$this->setTplParam('class', isset($params['class']) ? (string) $params['class'] : null);
		$this->setTplParam('placeholder', isset($params['placeholder']) ? $params['placeholder'] : null);
	}

	public function validate($userInput, CM_Response_Abstract $response) {
		$values = explode(',', $userInput);
		$values = array_unique($values);
		if ($this->_options['cardinality'] && count($values) > $this->_options['cardinality']) {
			throw new CM_Exception_FormFieldValidation('Too many elements.');
		}
		return $values;
	}

	public static function ajax_getSuggestions(CM_Params $params, CM_ComponentFrontendHandler $handler, CM_Response_View_Ajax $response) {
		$field = new static(null);
		$suggestions = $field->_getSuggestions($params->getString('term'), $params->getArray('options'), $response->getRender());
		return $suggestions;
	}
}
