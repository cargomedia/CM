<?php

class CMTest_Site_CM extends CM_Site_Abstract {

	const TYPE = 1;

	public static function match(CM_Request_Abstract $request) {
		return true;
	}
}
