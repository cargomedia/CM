<?php

class CM_Model_AbstractTest extends CMTest_TestCase {

	public static function setupBeforeClass() {
		CM_Db_Db::exec("CREATE TABLE IF NOT EXISTS `modelMock` (
				`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
				`foo` VARCHAR(32)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		");
		CM_Db_Db::exec("CREATE TABLE IF NOT EXISTS `modelThasIsAnAssetMock` (
				`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
				`modelMockId` INT UNSIGNED NOT NULL,
				`bar` VARCHAR(32),
				KEY (`modelMockId`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		");
	}

	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
		CM_Db_Db::exec("DROP TABLE `modelMock`");
		CM_Db_Db::exec("DROP TABLE `modelThasIsAnAssetMock`");
	}

	public function setup() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
	}

	public function tearDown() {
		CM_Db_Db::truncate('modelMock');
		CM_Db_Db::truncate('modelThasIsAnAssetMock');
		CMTest_TH::clearEnv();
	}

	public function testConstructorWithId() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getFoo());
	}

	public function testConstructorWithoutId() {
		$schema = new CM_Model_Schema_Definition(array('foo' => array()));
		$type = 12;

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getPersistence'))
				->setConstructorArgs(array(null, null))->getMockForAbstractClass();
		$model->expects($this->never())->method('_getPersistence');
		/** @var CM_Model_Abstract $model */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame(array(), $getData->invoke($model));
		$this->assertFalse($model->hasId());
		$model->_set('foo', 12);
		$this->assertSame(12, $model->_get('foo'));
	}

	public function testConstructWithIdWithData() {
		$data = array('foo' => 12, 'bar' => 13);
		$id = 55;
		$idRaw = array('id' => $id);
		$type = 12;

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', '_getPersistence'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->never())->method('_getCache');
		$model->expects($this->never())->method('_getPersistence');
		/** @var CM_Model_Abstract $model */
		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model, $idRaw, $data);

		$this->assertSame($id, $model->getId());
		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($model));
	}

	public function testConstructWithoutIdWithData() {
		$data = array('foo' => 11, 'bar' => 'foo');
		$type = 12;

		$model = $this->getMockBuilder('CM_Model_Abstract')->disableOriginalConstructor()->getMockForAbstractClass();
		/** @var CM_Model_Abstract $model */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model, null, $data);

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($model));
	}

	public function testConstructValidateFields() {
		$data = array('foo' => 12, 'bar' => 23);

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_validateFields'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_validateFields')->with($data);
		/** @var CM_Model_Abstract $modelMock */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($modelMock, null, $data);

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($modelMock));
	}

	public function testCommit() {
		$schema = new CM_Model_Schema_Definition(array('foo' => array()));
		$idRaw = array('id' => 909);
		$type = 12;
		$data = array('foo' => 12);

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('create', 'save'))
				->getMockForAbstractClass();
		$persistence->expects($this->once())->method('create')->with($type, $data)->will($this->returnValue($idRaw));
		$persistence->expects($this->once())->method('save')->with($type, $idRaw, $data);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType', '_getSchema', '_getPersistence'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		/** @var CM_Model_Abstract $model */

		$model->__construct(null, null);

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame(array(), $getData->invoke($model));
		$this->assertFalse($model->hasId());
		$model->_set($data);
		$model->commit();

		$this->assertSame($idRaw, $model->getIdRaw());
		$model->_set($data);
	}

	public function testCommitMultipleSaves() {
		$schema = new CM_Model_Schema_Definition(array('foo' => array()));
		$idRaw = array('id' => 909);
		$type = 12;
		$data = array('foo' => 12);

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('save'))->getMockForAbstractClass();
		$persistence->expects($this->exactly(2))->method('save')->with($type, $idRaw, $data);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType', '_getSchema', '_getPersistence'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		/** @var CM_Model_Abstract $model */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model, $idRaw, $data);

		$model->commit();
		$model->commit();
	}

	public function testCommitWithId() {
		$schema = new CM_Model_Schema_Definition(array('foo' => array()));
		$id = 123;
		$idRaw = array('id' => $id);
		$data = array('foo' => 12);
		$type = 12;

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('save'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('save')->with($type, $idRaw, $data);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType', '_getSchema', '_getPersistence'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		/** @var CM_Model_Abstract $model */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model, $idRaw, $data);

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($model));
		$this->assertSame($id, $model->getId());
		$model->commit();
	}

	public function testCreate() {
		$data = array('foo' => 11, 'bar' => 'foo');
		$type = 12;
		$idRaw = array('id' => 1);
		$schema = new CM_Model_Schema_Definition(array('foo' => array(), 'bar' => array()));

		$cacheable = $this->getMock('CM_Cacheable');
		$cacheable->expects($this->once())->method('_change');

		$assetClassHierarchy = array('CM_ModelAsset_Abstract', 'CM_ModelAsset_Concrete');
		$asset = $this->getMockBuilder('CM_ModelAsset_Abstract')->setMethods(array('getClassHierarchy', '_loadAsset'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$asset->expects($this->once())->method('getClassHierarchy')->will($this->returnValue($assetClassHierarchy));
		$asset->expects($this->exactly(count($assetClassHierarchy)))->method('_loadAsset');

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('save'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('save')->with($type, $idRaw, $data);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('create'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('create')->with($type, $data)->will($this->returnValue($idRaw));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')
				->setMethods(array('getType', '_getPersistence', '_getCache', '_getSchema', '_getContainingCacheables', '_getAssets', '_onChange',
					'_onCreate'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$methodCreate = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_create');
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->once())->method('_getPersistence')->will($this->returnValue($persistence));
		$model->expects($this->once())->method('_getCache')->will($this->returnValue($cache));
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$model->expects($this->once())->method('_getContainingCacheables')->will($this->returnValue(array($cacheable)));
		$model->expects($this->once())->method('_getAssets')->will($this->returnValue(array($asset)));
		$model->expects($this->once())->method('_onChange');
		$model->expects($this->once())->method('_onCreate');
		/** @var CM_Model_Abstract $model */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model, null, $data);

		$methodCreate->invoke($model);
		$this->assertSame($idRaw, $model->getIdRaw());
	}

	/**
	 * @expectedException CM_Exception_Invalid
	 * @expectedExceptionMessage Cannot create model without persistence
	 */
	public function testCreateWithoutPersistence() {
		$type = 12;
		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType', '_getPersistence'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$methodCreate = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_create');
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->once())->method('_getPersistence')->will($this->returnValue(null));
		/** @var CM_Model_Abstract $model */

		$methodCreate->invoke($model);
	}

	public function testCreateMultiple() {
		$data = array('foo' => 11, 'bar' => 'foo');
		$type = 12;
		$idRaw1 = array('id' => 1);
		$idRaw2 = array('id' => 2);
		$schema = new CM_Model_Schema_Definition(array('foo' => array(), 'bar' => array()));

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('create'))->getMockForAbstractClass();
		$persistence->expects($this->exactly(2))->method('create')->with($type, $data)->will($this->onConsecutiveCalls($idRaw1, $idRaw2));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')
				->setMethods(array('getType', '_getPersistence', '_getCache', '_getSchema', '_onChange', '_onCreate'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$methodCreate = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_create');
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$model->expects($this->exactly(2))->method('_onChange');
		$model->expects($this->exactly(2))->method('_onCreate');
		/** @var CM_Model_Abstract $model */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model, null, $data);

		$methodCreate->invoke($model);
		$this->assertSame($idRaw1, $model->getIdRaw());

		$methodCreate->invoke($model);
		$this->assertSame($idRaw2, $model->getIdRaw());
	}

	/**
	 * @expectedException CM_Exception_Invalid
	 * @expectedExceptionMessage Model has no id
	 */
	public function testGetIdWithoutId() {
		$data = array('foo' => 12);
		$type = 12;

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType'))
				->setConstructorArgs(array(null, $data))->getMockForAbstractClass();
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model */

		$model->getId();
	}

	/**
	 * @expectedException CM_Exception_Invalid
	 * @expectedExceptionMessage Model has no id
	 */
	public function testGetIdRawWithoutId() {
		$data = array('foo' => 12);
		$type = 12;

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType'))
				->setConstructorArgs(array(null, $data))->getMockForAbstractClass();
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model */

		$model->getIdRaw();
	}

	public function testHasId() {
		$data = array('foo' => 12, 'bar' => 13);
		$id = 55;
		$type = 12;

		$model1 = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model1->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model1 */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model1, array('id' => $id), $data);

		$this->assertTrue($model1->hasId());

		$model2 = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model2->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model2 */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($model2, null, $data);

		$this->assertFalse($model2->hasId());
	}

	public function testCache() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getFoo());
		CM_Db_Db::update('modelMock', array('foo' => 'bar2'), array('id' => $modelMock->getId()));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getFoo());
		$modelMock->_change();
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar2', $modelMock->getFoo());
	}

	public function testCacheHit() {
		$data = array('foo' => 12, 'bar' => 13);
		$id = array('id' => 55);
		$type = 12;

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('load')->with($type, $id)->will($this->returnValue($data));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', 'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getCache')->will($this->returnValue($cache));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($model));
	}

	public function testCacheMiss() {
		$data = array('foo' => 12, 'bar' => 13);
		$id = array('id' => 55);
		$type = 12;

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load', 'save'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('load')->with($type, $id)->will($this->returnValue(false));
		$cache->expects($this->once())->method('save')->with($type, $id, $data);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', 'getIdRaw', '_loadData', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getCache')->will($this->returnValue($cache));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_loadData')->will($this->returnValue($data));
		/** @var CM_Model_Abstract $model */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($model));
	}

	public function testCacheSet() {
		$data = array('foo' => 12);
		$id = array('id' => 55);
		$type = 12;

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load', 'save'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('load')->with($type, $id)->will($this->returnValue($data));
		$cache->expects($this->once())->method('save')->with($type, $id, array('foo' => 12, 'bar' => 14));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', 'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getCache')->will($this->returnValue($cache));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model */

		$model->_set(array('bar' => 14));
	}

	public function testCacheDelete() {
		$id = array('id' => 55);
		$type = 12;

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('delete'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('delete')->with($type, $id);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', 'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getCache')->will($this->returnValue($cache));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model */

		$model->delete();
	}

	public function testFactoryGeneric() {
		CM_Config::get()->CM_Model_Abstract->types[CM_ModelMock::TYPE] = 'CM_ModelMock';

		$modelMock1 = CM_ModelMock::createStatic(array('foo' => 'bar'));
		$modelMock2 = CM_Model_Abstract::factoryGeneric(CM_ModelMock::TYPE, $modelMock1->getIdRaw());
		$this->assertEquals($modelMock1, $modelMock2);

		CMTest_TH::clearConfig();
	}

	public function testFactoryGenericWithData() {
		CM_Config::get()->CM_Model_Abstract->types[CM_ModelMock::TYPE] = 'CM_ModelMock';

		$modelMock1 = CM_ModelMock::createStatic(array('foo' => 'bar'));
		/** @var CM_ModelMock $modelMock2 */
		$modelMock2 = CM_Model_Abstract::factoryGeneric(CM_ModelMock::TYPE, $modelMock1->getIdRaw(), array('foo' => 'bla'));
		$this->assertSame('bla', $modelMock2->getFoo());

		CMTest_TH::clearConfig();
	}

	public function testPersistenceSet() {
		$data = array('foo' => 12, 'muh' => 2);
		$schema = new CM_Model_Schema_Definition(array('foo' => array(), 'muh' => array()));
		$id = array('id' => 55);
		$type = 12;

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load', 'save'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('load')->with($type, $id)->will($this->returnValue($data));
		$persistence->expects($this->once())->method('save')->with($type, $id, array('foo' => 15, 'muh' => 2));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getPersistence', 'getIdRaw', 'getType', '_getSchema'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		/** @var CM_Model_Abstract $model */

		$model->_set(array('foo' => 15, 'bar' => 14));
	}

	public function testPersistenceGet() {
		$data = array('foo' => 12, 'bar' => 13);
		$id = array('id' => 55);
		$type = 12;

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('load')->with($type, $id)->will($this->returnValue($data));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', 'getIdRaw', 'getType', '_getPersistence'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getCache')->will($this->returnValue(null));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		/** @var CM_Model_Abstract $model */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($model));
	}

	public function testPersistenceDelete() {
		$id = array('id' => 55);
		$type = 12;

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('delete'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('delete')->with($type, $id);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getPersistence', 'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue($id));
		$model->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $model */

		$model->delete();
	}

	public function testGet() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		try {
			$modelMock->_get('foo');
			$this->assertEquals('bar1', $modelMock->_get('foo'));
		} catch (CM_Exception $ex) {
			$this->fail('Field `foo` does not exist.');
		}
		try {
			$modelMock->_get('bar');
			$this->fail('Field `bar` exists.');
		} catch (CM_Exception $ex) {
			$this->assertTrue(true);
		}
	}

	public function testGetValidatePersistence() {
		$type = 1;
		$idRaw = array('id' => 3);
		$data = array('foo' => 12, 'bar' => 23);

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('load')->will($this->returnValue($data));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('load')->will($this->returnValue(false));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_validateFields', '_getPersistence', '_getCache', 'getIdRaw',
			'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_validateFields')->with($data);
		$modelMock->expects($this->once())->method('_getPersistence')->will($this->returnValue($persistence));
		$modelMock->expects($this->once())->method('_getCache')->will($this->returnValue($cache));
		$modelMock->expects($this->any())->method('getIdRaw')->will($this->returnValue($idRaw));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $modelMock */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($modelMock));
	}

	public function testGetValidateCache() {
		$type = 1;
		$idRaw = array('id' => 3);
		$data = array('foo' => 12, 'bar' => 23);

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('load')->will($this->returnValue($data));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_validateFields', '_getCache', 'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_validateFields')->with($data);
		$modelMock->expects($this->once())->method('_getCache')->will($this->returnValue($cache));
		$modelMock->expects($this->any())->method('getIdRaw')->will($this->returnValue($idRaw));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $modelMock */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($modelMock));
	}

	public function testGetValidateLoadData() {
		$type = 1;
		$idRaw = array('id' => 3);
		$data = array('foo' => 12, 'bar' => 23);

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('load')->will($this->returnValue(false));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_validateFields', '_getCache', '_loadData',
			'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_validateFields')->with($data);
		$modelMock->expects($this->once())->method('_getCache')->will($this->returnValue($cache));
		$modelMock->expects($this->once())->method('_loadData')->will($this->returnValue($data));
		$modelMock->expects($this->any())->method('getIdRaw')->will($this->returnValue($idRaw));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $modelMock */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$this->assertSame($data, $getData->invoke($modelMock));
	}

	public function testGetDecode() {
		$type = 1;
		$idRaw = array('id' => 3);
		$data = array('foo' => 12, 'bar' => 23);
		$schema = $this->getMockBuilder('CM_Model_Schema_Definition')->setMethods(array('decodeField'))->setConstructorArgs(array(array()))->getMock();
		$schema->expects($this->once())->method('decodeField')->with('foo', 12)->will($this->returnValue(123));

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_decodeField', '_getCache', '_loadData',
			'getIdRaw', 'getType', '_getSchema'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_getCache')->will($this->returnValue(null));
		$modelMock->expects($this->once())->method('_loadData')->will($this->returnValue($data));
		$modelMock->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$modelMock->expects($this->any())->method('getIdRaw')->will($this->returnValue($idRaw));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));

		$this->assertSame(123, $modelMock->_get('foo'));
	}

	/**
	 * @expectedException CM_Exception_Nonexistent
	 * @expectedExceptionMessage has no data
	 */
	public function testGetNonexistent() {
		$type = 1;
		$idRaw = array('id' => 3);

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('load'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('load')->will($this->returnValue(false));
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', '_getPersistence', 'getIdRaw', 'getType'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_getCache')->will($this->returnValue(null));
		$modelMock->expects($this->once())->method('_getPersistence')->will($this->returnValue($persistence));
		$modelMock->expects($this->any())->method('getIdRaw')->will($this->returnValue($idRaw));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		/** @var CM_Model_Abstract $modelMock */

		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$getData->invoke($modelMock);
	}

	public function testHas() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertTrue($modelMock->_has('foo'));
		$this->assertFalse($modelMock->_has('bar'));
	}

	public function testSet() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getFoo());
		$modelMock->_set('foo', 'bar2');
		$modelMock->_set('bar', 'foo');
		$this->assertEquals('bar2', $modelMock->getFoo());
		$this->assertEquals('foo', $modelMock->_get('bar'));
		$modelMock->_change();
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getFoo());
	}

	public function testSetNull() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->_get('foo'));
		$modelMock->_set('foo', null);
		$this->assertNull($modelMock->_get('foo'));
	}

	public function testSetDataUnsetDecoded() {
		$dataPassed = $dataEncoded = array('foo' => '1');
		$dataDecoded = array('foo' => 1);

		$schema = $this->getMockBuilder('CM_Model_Schema_Definition')->setMethods(array('decodeField', 'encodeField'))
				->setConstructorArgs(array(array('foo' => array('type' => 'CM_ModelMock'))))->getMockForAbstractClass();
		$schema->expects($this->once())->method('decodeField')->with('foo', $dataEncoded['foo'])->will($this->returnValue($dataDecoded['foo']));
		$schema->expects($this->once())->method('encodeField')->with('foo', '1')->will($this->returnValue($dataEncoded['foo']));

		$model = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getSchema', 'getType', '_getData', 'getIdRaw'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$model->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		$model->expects($this->any())->method('_getData')->will($this->returnValue($dataEncoded));
		$model->expects($this->any())->method('getIdRaw')->will($this->returnValue(array('id' => 1)));

		/** @var CM_Model_Abstract $model */
		$model->_set('foo', $dataPassed['foo']);

		$this->assertSame($dataDecoded['foo'], $model->_get('foo'));
	}

	public function testSetMultiple() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'foo1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertSame('foo1', $modelMock->getFoo());
		$modelMock->_set(array('foo' => 'foo2', 'bar' => 'bar2'));
		$this->assertSame('foo2', $modelMock->getFoo());
		$this->assertSame('bar2', $modelMock->_get('bar'));
	}

	public function testSetEncodeValidate() {
		$type = 1;
		$idRaw = array('id' => 11);
		$data = array('foo' => '1', 'bar' => '2');
		$dataEncoded = array('foo' => 1, 'bar' => 2);
		$schema = $this->getMockBuilder('CM_Model_Schema_Definition')->setMethods(array('encodeField'))->setConstructorArgs(
			array(
				array('foo' => array('type' => 'int'),
					array('bar' => array('type' => 'int')
					)
				)))->getMockForAbstractClass();

		$cache = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('save'))->getMockForAbstractClass();
		$cache->expects($this->once())->method('save')->with($type, $idRaw, $dataEncoded);

		/** @var CM_Model_StorageAdapter_AbstractAdapter $cache */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_validateFields', '_getCache', 'getType',
			'getIdRaw', '_getData', 'getSchema'))->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->once())->method('_validateFields')->with($dataEncoded);
		$modelMock->expects($this->any())->method('_getCache')->will($this->returnValue($cache));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		$modelMock->expects($this->any())->method('getIdRaw')->will($this->returnValue($idRaw));
		$modelMock->expects($this->any())->method('_getData')->will($this->returnValue($dataEncoded));
		$modelMock->expects($this->any())->method('getSchema')->will($this->returnValue($schema));

		/** @var CM_Model_Abstract $modelMock */

		$modelMock->_set($data);
		$this->assertSame((int) $data['foo'], $modelMock->_get('foo'));
		$this->assertSame((int) $data['bar'], $modelMock->_get('bar'));
	}

	public function testSetPersistenceSave() {
		$type = 1;
		$idRaw = array('id' => 11);
		$schema = new CM_Model_Schema_Definition(array('foo' => array()));
		$data = array('foo' => 12, 'bar' => 23);
		$dataSchema = array('foo' => 12);

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('save'))->getMockForAbstractClass();
		$persistence->expects($this->once())->method('save')->with($type, $idRaw, $dataSchema);
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getPersistence', 'getType', '_getSchema'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		$modelMock->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		/** @var CM_Model_Abstract $modelMock */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($modelMock, $idRaw, $data);

		$modelMock->_set($data);
	}

	public function testSetPersistenceSaveNonschemaData() {
		$type = 1;
		$idRaw = array('id' => 11);
		$schema = new CM_Model_Schema_Definition(array('foo' => array()));
		$data = array('bar' => 23);

		$persistence = $this->getMockBuilder('CM_Model_StorageAdapter_AbstractAdapter')->setMethods(array('save'))->getMockForAbstractClass();
		$persistence->expects($this->never())->method('save');
		/** @var CM_Model_StorageAdapter_AbstractAdapter $persistence */

		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getPersistence', 'getType', '_getSchema'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->any())->method('_getPersistence')->will($this->returnValue($persistence));
		$modelMock->expects($this->any())->method('getType')->will($this->returnValue($type));
		$modelMock->expects($this->any())->method('_getSchema')->will($this->returnValue($schema));
		/** @var CM_Model_Abstract $modelMock */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($modelMock, $idRaw, $data);

		$modelMock->_set($data);
	}

	public function testSetOnChange() {
		$modelMock = $this->getMockBuilder('CM_Model_Abstract')->setMethods(array('_getCache', '_getPersistence', '_onChange'))
				->disableOriginalConstructor()->getMockForAbstractClass();
		$modelMock->expects($this->any())->method('_getCache')->will($this->returnValue(null));
		$modelMock->expects($this->any())->method('_getPersistence')->will($this->returnValue(null));
		$modelMock->expects($this->once())->method('_onChange');
		/** @var CM_Model_Abstract $modelMock */

		$_construct = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_construct');
		$_construct->invoke($modelMock, array('id' => 1), array('foo' => 'bar'));

		$modelMock->_set(array('foo' => 12));
	}

	public function testSetData() {
		$data = array('bar' => '23', 'foo' => 'bar');
		$dataNew = array('baar' => '23', 'fooo' => 'bar');

		$model = $this->getMockBuilder('CM_Model_Abstract')->setConstructorArgs(array())->getMockForAbstractClass();
		/** @var CM_Model_Abstract $model */
		$getData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_getData');
		$setData = CMTest_TH::getProtectedMethod('CM_Model_Abstract', '_setData');
		$this->assertSame(array(), $getData->invoke($model));

		$setData->invoke($model, $data);
		$this->assertSame($data, $getData->invoke($model));

		$setData->invoke($model, $dataNew);
		$this->assertSame($dataNew, $getData->invoke($model));
	}

	public function testDelete() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$modelMock->delete();
		try {
			$modelMock = new CM_ModelMock($modelMock->getId());
			$this->fail('Model was not deleted');
		} catch (CM_Exception_Nonexistent $ex) {
			$this->assertTrue(true);
		}
	}

	public function testOnChange() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals(0, $modelMock->onChangeCounter);
		$modelMock->_change();
		$this->assertEquals(1, $modelMock->onChangeCounter);
	}

	public function testOnCreate() {
		/** @var CM_ModelMock $modelMock */
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$this->assertEquals(1, $modelMock->onCreateCounter);

		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals(0, $modelMock->onCreateCounter);
	}

	public function testSerializable() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getFoo());
		$this->assertEquals('bar1', unserialize(serialize($modelMock))->getFoo());

		$modelMock->_set('foo', 'bar2');
		$this->assertEquals('bar2', unserialize(serialize($modelMock))->getFoo());
	}

	public function testModelAsset() {
		$modelMock = CM_ModelMock::createStatic(array('foo' => 'bar1'));
		$modelThatIsAnAssetMock = CM_ModelThasIsAnAssetMock::createStatic(array('modelMockId' => $modelMock->getId(), 'bar' => $modelMock->getFoo()));
		$modelThatIsAnAssetMock->_change();
		$modelMock->_change();
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar1', $modelMock->getModelAsset()->getBar());
		$modelThatIsAnAssetMock->setBar('bar2');
		$modelMock = new CM_ModelMock($modelMock->getId());
		$this->assertEquals('bar2', $modelMock->getModelAsset()->getBar());
	}

	public function testGetAsset() {
		/** @var CM_ModelMock2 $modelMock */
		$modelMock = new CM_ModelMock2(1);
		$this->assertInstanceOf('CM_ModelAsset_ModelMock_ModelAssetMock', $modelMock->getModelAssetMock());
	}

	public function testLazyAsset() {
		$modelMock = new CM_ModelMock2(1);
		$this->assertFalse($modelMock->_has('CM_ModelAsset_ModelMock_ModelAssetMock:foo'));
		$modelMock->getModelAssetMock()->getFoo();
		$this->assertTrue($modelMock->_has('CM_ModelAsset_ModelMock_ModelAssetMock:foo'));
		$modelMock->_set('CM_ModelAsset_ModelMock_ModelAssetMock:foo', 'bar');

		$modelMock = new CM_ModelMock2(1);
		$this->assertTrue($modelMock->_has('CM_ModelAsset_ModelMock_ModelAssetMock:foo'));
		$this->assertEquals('bar', $modelMock->getModelAssetMock()->getFoo());
		$modelMock->_change();

		$this->assertEquals('foo', $modelMock->getModelAssetMock()->getFoo());
	}

	public function testCreateType() {
		$user = CM_Model_Abstract::createType(CM_Model_User::TYPE);
		$this->assertInstanceOf('CM_Model_User', $user);
	}

	public function testTypeConstants() {
		foreach (CM_Model_Abstract::getClassChildren() as $class) {
			$classReflection = new ReflectionClass($class);
			$this->assertArrayHasKey('TYPE', $classReflection->getConstants(), 'No `TYPE` constant defined for `' . $class . '`');
		}
	}
}

class CM_ModelMock extends CM_Model_Abstract {

	const TYPE = 1;

	public $onChangeCounter = 0;
	public $onCreateCounter = 0;

	public function getFoo() {
		return (string) $this->_get('foo');
	}

	/**
	 * @return CM_ModelThasIsAnAssetMock
	 */
	public function getModelAsset() {
		return $this->_getAsset('CM_ModelAsset_ModelMock_ModelThasIsAnAssetMock')->get();
	}

	protected function _loadData() {
		return CM_Db_Db::select('modelMock', array('foo'), array('id' => $this->getId()))->fetch();
	}

	protected function _onChange() {
		$this->onChangeCounter++;
	}

	protected function _onCreate() {
		$this->onCreateCounter++;
	}

	protected function _onDelete() {
		CM_Db_Db::delete('modelMock', array('id' => $this->getId()));
	}

	protected function _getAssets() {
		return array(new CM_ModelAsset_ModelMock_ModelThasIsAnAssetMock($this));
	}

	protected static function _createStatic(array $data) {
		return new self(CM_Db_Db::insert('modelMock', array('foo' => $data['foo'])));
	}
}

class CM_ModelThasIsAnAssetMock extends CM_Model_Abstract {

	const TYPE = 2;

	public function getBar() {
		return (string) $this->_get('bar');
	}

	public function setBar($bar) {
		$bar = (string) $bar;
		CM_Db_Db::update('modelThasIsAnAssetMock', array('bar' => $bar), array('id' => $this->getId()));
		$this->_change();
	}

	public function getModelMock($throwNonexistent = true) {
		try {
			return new CM_ModelMock($this->getModelMockId());
		} catch (CM_Exception_Nonexistent $ex) {
			if ($throwNonexistent) {
				throw $ex;
			}
			return null;
		}
	}

	public function getModelMockId() {
		return $this->_get('modelMockId');
	}

	protected function _loadData() {
		return CM_Db_Db::select('modelThasIsAnAssetMock', array('bar', 'modelMockId'), array('id' => $this->getId()))->fetch();
	}

	protected function _onChange() {
		$this->getModelMock()->_change();
	}

	protected function _onDelete() {
		CM_Db_Db::delete('modelThasIsAnAssetMock', array('id' => $this->getId()));
	}

	protected static function _createStatic(array $data) {
		return new self(CM_Db_Db::insert('modelThasIsAnAssetMock', array('modelMockId' => $data['modelMockId'], 'bar' => $data['bar'])));
	}
}

class CM_ModelAsset_ModelMock_ModelThasIsAnAssetMock extends CM_ModelAsset_Abstract {

	/**
	 * @return CM_ModelThasIsAnAssetMock
	 */
	public function get() {
		if (($modelMock = $this->_cacheGet('modelMock')) === false) {
			try {
				$modelMockId = CM_Db_Db::select('modelThasIsAnAssetMock', 'id', array('modelMockId' => $this->_model->getId()))->fetchColumn();
				$modelMock = new CM_ModelThasIsAnAssetMock($modelMockId);
				$this->_cacheSet('modelMock', $modelMock);
			} catch (CM_Exception_Nonexistent $ex) {
				$modelMock = null;
			}
		}
		return $modelMock;
	}

	public function _loadAsset() {
		$this->get();
	}

	public function _onModelDelete() {
		if ($this->get()) {
			$this->get()->delete();
		}
	}
}

class CM_ModelMock2 extends CM_Model_Abstract {

	const TYPE = 3;

	protected function _loadData() {
		return array();
	}

	protected function _getAssets() {
		return array(new CM_ModelAsset_ModelMock_ModelAssetMock($this));
	}

	/**
	 * @return CM_ModelAsset_ModelMock2_ModelAssetMock
	 */
	public function getModelAssetMock() {
		return $this->_getAsset('CM_ModelAsset_Abstract');
	}
}

class CM_ModelAsset_ModelMock_ModelAssetMock extends CM_ModelAsset_Abstract {

	public function _onModelDelete() {
	}

	public function _loadAsset() {
	}

	public function getFoo() {
		if (($foo = $this->_cacheGet('foo')) === false) {
			$foo = 'foo';
			$this->_cacheSet('foo', $foo);
		}
		return $foo;
	}
}
