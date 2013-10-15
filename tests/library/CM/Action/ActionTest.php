<?php

class CM_Action_ActionTest extends CMTest_TestCase {

	public static function setUpBeforeClass() {
		CM_Config::get()->CM_Action_Abstract->verbs['foo'] = 1;
		CM_Config::get()->CM_Model_ActionLimit_Abstract->types[CM_Model_ActionLimit_Mock::TYPE] = 'CM_Model_ActionLimit_Mock';
	}

	public function tearDown() {
		CM_Db_Db::truncate('cm_action');
		CM_Db_Db::truncate('cm_actionLimit');
		CMTest_TH::clearCache();
	}

	public function testConstruct() {
		$actor = CMTest_TH::createUser();
		/** @var CM_Action_Abstract $action */
		$action = $this->getMockForAbstractClass('CM_Action_Abstract', array('foo', $actor), '', true, true, true, array('getType'));
		$action->expects($this->any())->method('getType')->will($this->returnValue(123));

		$this->assertInstanceOf('CM_Action_Abstract', $action);
		$this->assertSame(1, $action->getVerb());
		$this->assertSame($actor, $action->getActor());
		$this->assertNull($action->getIp());

		$actor = 123456; // IP address
		/** @var CM_Action_Abstract $action */
		$action = $this->getMockForAbstractClass('CM_Action_Abstract', array('foo', $actor), '', true, true, true, array('getType'));
		$action->expects($this->any())->method('getType')->will($this->returnValue(123));
		$this->assertInstanceOf('CM_Action_Abstract', $action);
		$this->assertSame(1, $action->getVerb());
		$this->assertNull($action->getActor());
		$this->assertSame($actor, $action->getIp());

		try {
			$action = $this->getMockForAbstractClass('CM_Action_Abstract', array('foo', 'bar'));
			$this->fail("Can instantiate action with actor `bar`");
		} catch (CM_Exception_Invalid $e) {
			$this->assertTrue(true);
		}

		try {
			$action = $this->getMockForAbstractClass('CM_Action_Abstract', array('foo', null));
			$this->fail("Can instantiate action with actor `null`");
		} catch (CM_Exception_Invalid $e) {
			$this->assertTrue(true);
		}
	}

	public function testPrepare() {
		$actor = CMTest_TH::createUser();
		$action = new CM_Action_Mock('foo', $actor);
		$action->prepare();

		CM_Db_Db::insert('cm_actionLimit', array('type' => 1, 'actionType' => 1, 'actionVerb' => 1, 'role' => null, 'limit' => 0, 'period' => 0));
		CMTest_TH::clearCache();
		try {
			$action->prepare();
			$this->fail('Limited action did not throw exception');
		} catch (CM_Exception_ActionLimit $e) {
			$this->assertSame('Mock overshoot', $e->getMessage());
		}
	}

	public function testAggregate() {
		CMTest_TH::timeForward(-(time() % 30)); // Make sure time() is a multiple of 30

		$time = time() - 86400;
		$values = array();
		$values[] = array(1, null, 1, 1, null, time() - 10000, 1);
		$values[] = array(1, null, 1, 1, null, $time - 1, 2);
		$values[] = array(1, null, 1, 1, null, $time - 2, 1);
		$values[] = array(1, null, 1, 1, null, $time - 5, 1);
		$values[] = array(1, null, 1, 1, null, $time - 6, 1);
		$values[] = array(1, null, 1, 1, null, $time - 7, 1);
		$values[] = array(1, null, 1, 1, null, $time - 8, 1);
		$values[] = array(1, null, 1, 1, null, $time - 9, 1);
		$values[] = array(1, null, 1, 1, null, $time - 10, 4);
		$values[] = array(null, 1, 1, 1, null, $time - 11, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 14, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 15, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 18, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 20, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 21, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 25, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 27, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 30, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 40, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 50, 1);
		$values[] = array(null, 1, 1, 1, null, $time - 60, 10);
		$values[] = array(null, 1, 2, 1, null, $time - 9, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 9, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 10, 2);
		$values[] = array(null, 1, 2, 1, null, $time - 11, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 12, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 13, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 14, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 15, 1);
		$values[] = array(null, 1, 2, 1, null, $time - 16, 1);
		$values[] = array(null, 1, 2, 1, 1, $time - 6, 1);
		$values[] = array(null, 1, 2, 1, 1, $time - 6, 2);
		$values[] = array(null, 1, 2, 1, 2, $time - 6, 3);
		$values[] = array(null, 1, 2, 1, 2, $time - 7, 4);
		$values[] = array(null, 1, 2, 1, 2, $time - 1, 5);
		$values[] = array(null, 1, 2, 1, 2, $time - 1, 6);
		$values[] = array(null, 1, 1, 2, null, $time - 17, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 18, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 19, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 20, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 21, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 22, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 23, 1);
		$values[] = array(null, 1, 1, 2, null, $time - 24, 4);
		CM_Db_Db::insert('cm_action', array('actorId', 'ip', 'verb', 'type', 'actionLimitType', 'createStamp', 'count'), $values);
		CM_Action_Abstract::aggregate(array(array('interval' => 5, 'limit' => 86400), array('interval' => 10, 'limit' => 86400 + 20),
			array('interval' => 30, 'limit' => 86400 + 30)));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 1, 'count' => 1));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 5, 'count' => 4));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 5, 'count' => 8));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 5, 'count' => 3));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 5, 'count' => 2));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 10, 'count' => 4));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'interval' => 30, 'count' => 12));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 5, 'count' => 4));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 5, 'count' => 5));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 5, 'count' => 1));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 1, 'count' => 1, 'actionLimitType' => 1));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 1, 'count' => 2, 'actionLimitType' => 1));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 1, 'count' => 3, 'actionLimitType' => 2));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 1, 'count' => 4, 'actionLimitType' => 2));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 1, 'count' => 5, 'actionLimitType' => 2));
		$this->assertRow('cm_action', array('verb' => 2, 'type' => 1, 'interval' => 1, 'count' => 6, 'actionLimitType' => 2));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 2, 'interval' => 5, 'count' => 4));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 2, 'interval' => 10, 'count' => 7));

		$this->assertEquals(18, CM_Db_Db::count('cm_action'));
	}

	public function testAggregateInvalidIntervals() {
		try {
			CM_Action_Abstract::aggregate(array(array('interval' => 5, 'limit' => 10), array('interval' => 11, 'limit' => 20)));
			$this->fail('Invalid intervals were not detected');
		} catch (CM_Exception_Invalid $e) {
			$this->assertContains('`11` is not a multiple of `5`', $e->getMessage());
		}

		try {
			CM_Action_Abstract::aggregate(array(array('interval' => 5, 'limit' => 10), array('interval' => 10, 'limit' => 20),
				array('interval' => 21, 'limit' => 30)));
			$this->fail('Invalid intervals were not detected');
		} catch (CM_Exception_Invalid $e) {
			$this->assertContains('`21` is not a multiple of `10`', $e->getMessage());
		}
	}

	public function testCollapse() {
		$values[] = array(1, null, 1, 1, null, 1, 1);
		$values[] = array(1, null, 1, 1, null, 1, 2);
		$values[] = array(1, null, 1, 1, null, 2, 1);
		$values[] = array(1, null, 1, 1, null, 3, 1);
		$values[] = array(1, null, 1, 1, null, 4, 10);
		$values[] = array(1, null, 1, 1, 1, 4, 10);
		$values[] = array(1, null, 2, 1, null, 4, 100);
		$values[] = array(1, null, 1, 2, null, 4, 100);
		$values[] = array(1, null, 1, 1, null, 5, 100);
		CM_Db_Db::insert('cm_action', array('actorId', 'ip', 'verb', 'type', 'actionLimitType', 'createStamp', 'count'), $values);
		CM_Action_Abstract::collapse(1, 4);
		$this->assertEquals(6, CM_Db_Db::count('cm_action'));
		$this->assertRow('cm_action', array('verb' => 1, 'type' => 1, 'createStamp' => 2, 'count' => 5));
	}

	public function testForceAllow() {
		$actor = CMTest_TH::createUser();
		$action = new CM_Action_Mock('foo', $actor);

		CM_Db_Db::insert('cm_actionLimit', array('type' => 1, 'actionType' => 1, 'actionVerb' => 1, 'role' => null, 'limit' => 0, 'period' => 0));

		$action->forceAllow(false);
		try {
			$action->prepare();
			$this->fail('Limited action did not throw exception');
		} catch (CM_Exception_ActionLimit $e) {
			$this->assertSame('Mock overshoot', $e->getMessage());
		}

		$action->forceAllow(true);
		$action->prepare();
		$this->assertTrue(true);
	}

	public function testGetName() {
		$actor = CMTest_TH::createUser();
		$action = new CM_Action_Mock('foo', $actor);
		$this->assertSame('Mock', $action->getName());
	}

	public function testGetLabel() {
		$actor = CMTest_TH::createUser();
		$action = new CM_Action_Mock('foo', $actor);
		$this->assertSame('Mock Test', $action->getLabel());
	}

	public function testIsAllowed() {
		$actor = CMTest_TH::createUser();
		$action = new CM_Action_Mock('foo', $actor);
		$this->assertTrue($action->isAllowed(false));
		$this->assertFalse($action->isAllowed(true));
	}

	/**
	 * @expectedException CM_Exception_NotAllowed
	 * @expectedExceptionMessage Action not allowed
	 */
	public function testPrepareNotAllowed() {
		$actor = CMTest_TH::createUser();
		$action = new CM_Action_Mock('foo', $actor);
		$action->prepare(true);
	}
}

class CM_Action_Mock extends CM_Action_Abstract {

	const TYPE = 1;

	protected function _notify() {
	}

	/**
	 * @param bool $private
	 * @return bool
	 */
	protected function _isAllowedTest($private = false) {
		return !$private;
	}

	protected function _prepare() {
	}

	public function getVerbName() {
		return 'Test';
	}
}

class CM_Model_ActionLimit_Mock extends CM_Model_ActionLimit_Abstract {

	const TYPE = 1;

	public function overshoot(CM_Action_Abstract $action, $role, $first) {
		throw new CM_Exception_ActionLimit('Mock overshoot');
	}
}
