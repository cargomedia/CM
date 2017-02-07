<?php

class CM_Model_Entity_AbstractTest extends CMTest_TestCase {

    public static function setupBeforeClass() {
        CM_Db_Db::exec("CREATE TABLE IF NOT EXISTS `entityMock` (
				`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
				`userId` INT UNSIGNED NOT NULL,
				`foo` VARCHAR(32),
				KEY (`userId`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		");
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();
        CM_Db_Db::exec("DROP TABLE `entityMock`");
    }

    public function tearDown() {
        CM_Db_Db::truncate('entityMock');
        CMTest_TH::clearEnv();
    }

    public function testGetUserId() {
        $user = CM_Model_User::createStatic();
        CM_Model_Entity_Mock::createStatic(array('userId' => $user->getId(), 'foo' => 'bar1'));
        $entityMock = new CM_Model_Entity_Mock(1);
        $this->assertSame($user->getId(), $entityMock->getUserId());
    }

    public function testGetUser() {
        $user = CM_Model_User::createStatic();
        $user2 = CM_Model_User::createStatic();
        CM_Model_Entity_Mock::createStatic(array('userId' => $user->getId(), 'foo' => 'bar1'));
        $entityMock = new CM_Model_Entity_Mock(1);
        $this->assertEquals($user->getId(), $entityMock->getUser()->getId());
        $this->assertInstanceOf('CM_Model_User', $user);

        $this->assertNotEquals($user2, $entityMock->getUser());
        CM_Db_Db::delete('cm_user', array('userId' => $user->getId()));
        CMTest_TH::clearCache();
        try {
            $entityMock->getUser();
            $this->fail('User not deleted');
        } catch (CM_Exception_Nonexistent $ex) {
            $this->assertTrue(true);
        }
        $this->assertNull($entityMock->getUserIfExists());
    }

    public function testIsOwner() {
        $user = CMTest_TH::createUser();
        $entity = $this->getMockBuilder('CM_Model_Entity_Abstract')->setMethods(array('getUser'))->disableOriginalConstructor()->getMockForAbstractClass();
        $entity->expects($this->any())->method('getUser')->will(
            $this->onConsecutiveCalls($this->returnValue($user),
                $this->onConsecutiveCalls($this->returnValue($user)),
                $this->throwException(new CM_Exception_Nonexistent()))
        );
        /** @var $entity CM_Model_Entity_Abstract */
        $this->assertTrue($entity->isOwner($user));

        $stranger = CMTest_TH::createUser();
        $this->assertFalse($entity->isOwner($stranger));
        $this->assertFalse($entity->isOwner($stranger));
    }

    public function testArrayConvertible() {
        $user = CMTest_TH::createUser();
        $id = CM_Model_Entity_Mock::createStatic(array('userId' => $user->getId(), 'foo' => 'boo'));
        $mock = $this->getMockBuilder('CM_Model_Entity_Mock')->setConstructorArgs(array($id->getId()))->setMethods(array('getType'))->getMock();
        $mock->expects($this->any())->method('getType')->will($this->returnValue(null));
        /** @var $mock CM_Model_Entity_Abstract */
        $data = $mock->toArray();
        $this->assertArrayHasKey('_type', $data);
        $this->assertNull($data['_type']);
    }

    public function testJsonSerializable() {
        $user = CMTest_TH::createUser();
        $id = CM_Model_Entity_Mock::createStatic(array('userId' => $user->getId(), 'foo' => 'boo'));
        $mock = $this->getMockBuilder('CM_Model_Entity_Mock')->setConstructorArgs(array($id->getId()))->setMethods(array('getType'))->getMock();
        $mock->expects($this->any())->method('getType')->will($this->returnValue(null));
        /** @var $mock CM_Model_Entity_Abstract */
        $data = $mock->jsonSerialize();
        $this->assertArrayHasKey('path', $data);
        $this->assertNull($data['path']);
    }
}

class CM_Model_Entity_Mock extends CM_Model_Entity_Abstract {

    public $onLoadCounter = 0;
    public $onChangeCounter = 0;

    public function getPath() {
        return null;
    }

    public function getFoo() {
        return (string) $this->_get('foo');
    }

    protected function _loadData() {
        return CM_Db_Db::select('entityMock', array('userId', 'foo'), array('id' => $this->getId()))->fetch();
    }

    protected function _onChange() {
    }

    protected function _onDelete() {
        CM_Db_Db::delete('entityMock', array('id' => $this->getId()));
    }

    protected function _onLoad() {
    }

    protected static function _createStatic(array $data) {
        return new self(CM_Db_Db::insert('entityMock', array('userId' => $data['userId'], 'foo' => $data['foo'])));
    }

    public static function getTypeStatic() {
        return 1;
    }
}
