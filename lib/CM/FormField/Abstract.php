<?php

abstract class CM_FormField_Abstract extends CM_Renderable_Abstract {
	/**
	 * @var string
	 */
	private $name;

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
	 * Constructor.
	 *
	 * @param string $field_name
	 */
	public function __construct($field_name) {
		$this->name = $field_name;
	}

	/**
	 * Get a field name.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return mixed|null Internal value
	 */
	protected function _getValue() {
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
	 * @param string|array $userInput
	 * @return mixed Internal value
	 * @throws CM_FormFieldValidationException
	 */
	abstract public function validate($userInput);

	/**
	 * @param array             $params
	 * @param CM_Form_Abstract  $form
	 */
	public function render(array $params, CM_Form_Abstract $form) {
		$this->setTplParam('this', $this);
		$this->setTplParam('id', $form->getTagAutoId($this->getName() . '-input'));
		$this->setTplParam('name', $this->getName());
		$this->setTplParam('value', $this->_getValue());
		$this->setTplParam('options', $this->getOptions());
		$this->setTplParam('params', $params);
		$this->setTplParam('form', $form);
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @return CM_Component_Abstract
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

}

