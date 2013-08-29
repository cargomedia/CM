<?php

class CM_Model_Schema_Definition {

	/** @var array */
	private $_schema;

	/**
	 * @param array $schema
	 */
	public function __construct(array $schema) {
		$this->_schema = $schema;
	}

	/**
	 * @return string[]
	 */
	public function getFieldNames() {
		return array_keys($this->_schema);
	}

	/**
	 * @param string|string[] $key
	 * @return bool
	 */
	public function hasField($key) {
		if (is_array($key)) {
			return count(array_intersect($key, array_keys($this->_schema))) > 0;
		}
		return array_key_exists($key, $this->_schema);
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @return mixed
	 * @throws CM_Exception_Invalid
	 * @throws CM_Model_Exception_Validation
	 */
	public function validateField($key, $value) {
		if ($this->hasField($key)) {
			$schemaField = $this->_schema[$key];

			$optional = !empty($schemaField['optional']);

			if (!$optional && null === $value) {
				throw new CM_Model_Exception_Validation('Field `' . $key . '` is mandatory');
			}

			if (null !== $value) {
				$type = isset($schemaField['type']) ? $schemaField['type'] : null;
				if (null !== $type) {
					switch ($type) {
						case 'int':
							if (!is_int($value) && !(is_string($value) && $value === (string) (int) $value)) {
								throw new CM_Model_Exception_Validation('Field `' . $key . '` is not an integer');
							}
							$value = (int) $value;
							break;
						default:
							throw new CM_Exception_Invalid('Invalid type `' . $type . '`');
					}
				}
			}
		}

		return $value;
	}
}
