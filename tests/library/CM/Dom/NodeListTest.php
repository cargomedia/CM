<?php

class CM_Dom_NodeListTest extends CMTest_TestCase {

  public function testConstructor() {
    new CM_Dom_NodeList('<html><body><p>hello</p></body></html>');
    new CM_Dom_NodeList('<p>hello</p>');
    new CM_Dom_NodeList('<p>hello');

    $domElement1 = new DOMElement('foo');
    $domElement2 = new DOMElement('foo');
    new CM_Dom_NodeList(array($domElement1, $domElement2));

    $this->assertTrue(true);
  }

  /**
   * @expectedException CM_Exception_Invalid
   * @expectedExceptionMessage Cannot load html
   */
  public function testConstructorInvalid() {
    new CM_Dom_NodeList('<%%%%===**>>> foo');
  }

  /**
   * @expectedException CM_Exception_Invalid
   * @expectedExceptionMessage Cant create elementList from empty string
   */
  public function testConstructorEmpty() {
    new CM_Dom_NodeList('');
  }

  public function testGetText() {
    $list = new CM_Dom_NodeList('<div>hello<strong>world</strong></div>');
    $this->assertSame('helloworld', $list->getText());
  }

  public function testGetTextEncoding() {
    $list = new CM_Dom_NodeList('<div>hello繁體字<strong>world</strong></div>');
    $this->assertSame('hello繁體字world', $list->getText());
  }

  public function testGetAttribute() {
    $list = new CM_Dom_NodeList('<div foo="bar"><div foo="foo"></div></div>');
    $this->assertSame('bar', $list->find('div')->getAttribute('foo'));
    $this->assertNull($list->getAttribute('bar'));
  }

  public function testGetAttributeList() {
    $list = new CM_Dom_NodeList('<div foo="bar" bar="foo"></div>');
    $this->assertSame(array('foo' => 'bar', 'bar' => 'foo'), $list->find('div')->getAttributeList());
  }

  public function testFind() {
    $list = new CM_Dom_NodeList('<div foo="bar">lorem ipsum dolor <p foo="foo">lorem ipsum</p></div><p foo="foo">lorem</p>');
    $this->assertSame('lorem ipsumlorem', $list->find('p')->getText());
  }

  public function testGetChildren() {
    $expected = array('lorem ipsum dolor', 'lorem ipsum', 'test', '');
    $list = new CM_Dom_NodeList('<div><span foo="bar">lorem ipsum dolor</span><p foo="foo">lorem ipsum</p><span>test</span><a></a></div>');
    $children = $list->find('div')->getChildren();

    $actual = array();
    /** @var CM_Dom_NodeList $child */
    foreach ($children as $child) {
      $actual[] = $child->getText();
    }
    $this->assertContainsAll($expected, $actual);
  }

  public function testGetChildrenEmpty() {
    $list = new CM_Dom_NodeList('<p>hello</p>');
    $list2 = $list->find('foo')->getChildren();

    $this->assertEquals(0, $list2->count());
  }

  public function testHas() {
    $list = new CM_Dom_NodeList('<div foo="bar" bar="foo"></div>');
    $this->assertTrue($list->has('div'));
    $this->assertFalse($list->has('foo'));
  }

  public function testCount() {
    $list = new CM_Dom_NodeList('<div><span foo="bar">lorem ipsum dolor</span><div foo="foo">lorem ipsum</div><span>test</span><a></a></div>');
    $this->assertCount(1, $list);
    $this->assertCount(2, $list->find('div'));
    $this->assertCount(0, $list->find('p'));
    $this->assertInstanceOf('Countable', $list);
    $this->assertSame(2, count($list->find('div')));
  }
}
