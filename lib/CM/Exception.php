<?php

class CM_Exception extends Exception {
	private $_public;

	/**
	 * @param string $message OPTIONAL
	 * @param bool $public OPTIONAL
	 */
	public function __construct($message = null, $public = false) {
		parent::__construct($message);
		$this->_public = (bool) $public;
	}
	
	/**
	 * @return string
	 */
	public function getMessagePublic() {
		if (!$this->_public) {
			return 'Internal server error';
		}
		return $this->getMessage();
	}
	
	/**
	 * @return boolean
	 */
	public function isPublic() {
		return $this->_public;
	}
}
