<?php

class CM_PagingSource_SearchTest extends CMTest_TestCase {

  public static function setUpBeforeClass() {
    CM_Db_Db::exec("CREATE TABLE `indexTest_1` (`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, `name` VARCHAR(8))");
    CM_Db_Db::exec("CREATE TABLE `indexTest_2` (`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, `price` INT UNSIGNED)");
    CM_Db_Db::exec("CREATE TABLE `indexTest_3` (`id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, `price` INT UNSIGNED)");
    $config = CM_Config::get();
    $config->CM_Search->enabled = true;
    CM_Config::set($config);
  }

  public static function tearDownAfterClass() {
    CM_Db_Db::exec("DROP TABLE `indexTest_1`");
    CM_Db_Db::exec("DROP TABLE `indexTest_2`");
    CM_Db_Db::exec("DROP TABLE `indexTest_3`");
  }

  public function tearDown() {
    CMTest_TH::clearDb();
  }

  public function setUp() {
    $type1 = new CM_Elastica_Type_Mock1();
    $type2 = new CM_Elastica_Type_Mock2();
    $type3 = new CM_Elastica_Type_Mock3();
    $type1->createVersioned();
    $type2->createVersioned();
    $type3->createVersioned();
    $type1->getIndex()->refresh();
    $type2->getIndex()->refresh();
    $type3->getIndex()->refresh();
  }

  public function testGet() {
    $type1 = new CM_Elastica_Type_Mock1();
    $source = new CM_PagingSource_Search($type1, new CM_SearchQuery_Mock());
    $this->assertSame(0, $source->getCount());

    $id = $type1->createEntry('foo');
    $this->assertSame(1, $source->getCount());
    $this->assertSame(array((string) $id), $source->getItems());

    $type3 = new CM_Elastica_Type_Mock3();
    $source = new CM_PagingSource_Search($type3, new CM_SearchQuery_Mock(), array('price'));
    $this->assertSame(0, $source->getCount());

    $id2 = $type3->createEntry(3);
    $this->assertSame(1, $source->getCount());
    $this->assertSame(array(array('id' => (string) $id2, 'price' => 3)), $source->getItems());
  }

  public function testMultiGet() {
    $type1 = new CM_Elastica_Type_Mock1();
    $type2 = new CM_Elastica_Type_Mock2();
    $source = new CM_PagingSource_Search(array($type1, $type2), new CM_SearchQuery_Mock());
    $this->assertSame(0, $source->getCount());

    $id1 = $type1->createEntry('foo');
    $this->assertSame(1, $source->getCount());

    $id2 = $type2->createEntry(1);
    $this->assertSame(2, $source->getCount());
    $this->assertContainsAll(array(
      array('id' => (string) $id1, 'type' => 'index_1'),
      array('id' => (string) $id2, 'type' => 'index_2')
    ), $source->getItems());

    $type3 = new CM_Elastica_Type_Mock3();
    $source = new CM_PagingSource_Search(array($type1, $type2, $type3), new CM_SearchQuery_Mock(), array('price'));
    $id3 = $type3->createEntry(5);

    $this->assertSame(3, $source->getCount());
    $this->assertContainsAll(array(
      array('id' => (string) $id1, 'type' => 'index_1'),
      array('id' => (string) $id2, 'type' => 'index_2'),
      array('id' => (string) $id3, 'type' => 'index_3', 'price' => 5)
    ), $source->getItems());
  }
}

class CM_Elastica_Type_Mock1 extends CM_Elastica_Type_Abstract {

  const INDEX_NAME = 'index_1';

  protected $_mapping = array(
    'name' => array('type' => 'string'),
  );

  protected $_indexParams = array(
    'index' => array(
      'number_of_shards'   => 1,
      'number_of_replicas' => 0
    ),
  );

  /**
   * @param string $name
   * @return int
   */
  public function createEntry($name) {
    $id = CM_Db_Db::insert('indexTest_1', array('name' => (string) $name));
    $this->update($id);
    $this->getIndex()->refresh();
    return (int) $id;
  }

  protected function _getQuery($ids = null, $limit = null) {
    return 'SELECT * FROM indexTest_1';
  }

  protected function _getDocument(array $data) {
    $doc = new Elastica_Document($data['id'],
      array(
        'name' => $data['name'],
      )
    );

    return $doc;
  }
}

class CM_Elastica_Type_Mock2 extends CM_Elastica_Type_Abstract {

  const INDEX_NAME = 'index_2';

  protected $_mapping = array(
    'price' => array('type' => 'integer'),
  );

  protected $_indexParams = array(
    'index' => array(
      'number_of_shards'   => 1,
      'number_of_replicas' => 0
    ),
  );

  /**
   * @param int $price
   * @return int
   */
  public function createEntry($price) {
    $id = CM_Db_Db::insert('indexTest_2', array('price' => (int) $price));
    $this->update($id);
    $this->getIndex()->refresh();
    return (int) $id;
  }

  protected function _getQuery($ids = null, $limit = null) {
    return 'SELECT * FROM indexTest_2';
  }

  protected function _getDocument(array $data) {
    $doc = new Elastica_Document($data['id'],
      array(
        'price' => $data['price'],
      )
    );

    return $doc;
  }
}

class CM_Elastica_Type_Mock3 extends CM_Elastica_Type_Abstract {

  const INDEX_NAME = 'index_3';

  protected $_mapping = array(
    'price' => array('type' => 'integer', 'store' => 'yes'),
  );

  protected $_indexParams = array(
    'index' => array(
      'number_of_shards'   => 1,
      'number_of_replicas' => 0
    ),
  );

  /**
   * @param int $price
   * @return int
   */
  public function createEntry($price) {
    $id = CM_Db_Db::insert('indexTest_3', array('price' => (int) $price));
    $this->update($id);
    $this->getIndex()->refresh();
    return (int) $id;
  }

  protected function _getQuery($ids = null, $limit = null) {
    return 'SELECT * FROM indexTest_3';
  }

  protected function _getDocument(array $data) {
    $doc = new Elastica_Document($data['id'],
      array(
        'price' => $data['price'],
      )
    );

    return $doc;
  }
}

class CM_SearchQuery_Mock extends CM_SearchQuery_Abstract {

}
