<?php

abstract class CM_FormField_Abstract extends CM_View_Abstract {

	/**
	 * @var mixed
	 */
	private $_value;

	/**
	 * @var array
	 */
	protected $_options = array();

	/**
	 * @var array
	 */
	protected $_tplParams = array();

	/**
	 * @return mixed|null Internal value
	 */
	public function getValue() {
		return $this->_value;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		return $this->_options;
	}

	/**
	 * @param mixed $value Internal value
	 */
	public function setValue($value) {
		$this->_value = $value;
	}

	/**
	 * @param string $userInput
	 * @return bool
	 */
	public function isEmpty($userInput) {
		if (is_array($userInput) && !empty($userInput)) {
			return false;
		}
		if (is_scalar($userInput) && strlen(trim($userInput)) > 0) {
			return false;
		}
		return true;
	}

	/**
	 * @param string|array         $userInput
	 * @param CM_Response_Abstract $response
	 * @return mixed Internal value
	 * @throws CM_Exception_FormFieldValidation
	 */
	abstract public function validate($userInput, CM_Response_Abstract $response);

	/**
	 * @param array $params
	 */
	public function prepare(array $params) {
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @return CM_FormField_Abstract
	 */
	public function setTplParam($key, $value) {
		$this->_tplParams[$key] = $value;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getTplParams() {
		return $this->_tplParams;
	}

	/**
	 * @param string $className
	 * @return CM_FormField_Abstract
	 * @throws CM_Exception
	 */
	public static function factory($className) {
		$className = (string) $className;
		if (!class_exists($className) || !is_subclass_of($className, __CLASS__)) {
			throw new CM_Exception_Invalid('Illegal field name `' . $className . '`.');
		}
		$field = new $className();
		return $field;
	}

	public static function ajax_validate(CM_Params $params, CM_ComponentFrontendHandler $handler, CM_Response_View_Ajax $response) {
		$formName = $params->getString('form');
		$fieldName = $params->getString('fieldName');
		$userInput = $params->get('userInput');

		$form = CM_Form_Abstract::factory($formName);
		$form->setup();
		$field = $form->getField($fieldName);

		$field->validate($userInput, $response);
	}
}

