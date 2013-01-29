<?php

class CM_Paging_Smiley_AllTest extends CMTest_TestCase {

	public static function setUpBeforeClass() {

	}

	public static function tearDownAfterClass() {
		CMTest_TH::clearEnv();
	}

	public function testNoIntersection() {
		$paging = new CM_Paging_Smiley_All();
		$codes = array();
		foreach ($paging as $smiley) {
			$codes = array_merge($codes, $smiley['codes']);
		}
		for ($i = 0; $i < count($codes); $i++) {
			for ($j = $i + 1; $j < count($codes); $j++) {
				if (false !== strpos($codes[$i], $codes[$j])) {
					$this->fail('Smiley `' . $codes[$i] . '` in `' . $codes[$j] . '`');
				}
			}
		}
	}
}
