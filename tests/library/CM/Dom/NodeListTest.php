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
     * @expectedExceptionMessage Cannot load html: htmlParseStartTag: invalid element name
     */
    public function testConstructorInvalid() {
        $list = new CM_Dom_NodeList('<%%%%===**>>> foo');
    }

    public function testConstructorInvalidIgnoreErrors() {
        $list = new CM_Dom_NodeList('<%%%%===**>>> foo', true);
        $this->assertSame('<p>&gt;&gt; foo</p>', $list->getHtml());
    }

    public function testGetHtml() {
        $list = new CM_Dom_NodeList('<div>hello</div> world');
        $this->assertSame('<div>hello</div> world', $list->getHtml());
    }

    public function testGetHtmlEmpty() {
        $list = new CM_Dom_NodeList('');
        $this->assertSame('', $list->getHtml());
    }

    public function testGetHtmlRecursive() {
        $list = new CM_Dom_NodeList('<div><p>hello</p></div> world');
        $this->assertSame('<p>hello</p>', $list->find('p')->getHtml());
    }

    public function testGetHtmlWithHtml() {
        $list = new CM_Dom_NodeList('<html><body><p>hello</p></body></html>');
        $this->assertSame('<p>hello</p>', $list->getHtml());
    }

    public function testGetHtmlWithHtmlHead() {
        $list = new CM_Dom_NodeList('<html><head></head><body><p>hello</p></body></html>');
        $this->assertSame('<p>hello</p>', $list->getHtml());
    }

    public function testGetHtmlWeirdDocument() {
        $list = new CM_Dom_NodeList('<!-- comment --><html><!-- comment --><body><p>hello</p></body><!-- comment --></html><!-- comment -->');
        $this->assertSame('<p>hello</p>', $list->getHtml());
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
        $list = new CM_Dom_NodeList('<div class="outer"><div foo="bar"><div foo="foo"></div></div></div>');
        $this->assertSame('bar', $list->find('div')->getAttribute('foo'));
        $this->assertNull($list->getAttribute('bar'));
    }

    public function testGetAttributeList() {
        $list = new CM_Dom_NodeList('<div foo="bar" bar="foo"></div>');
        $this->assertSame(array('foo' => 'bar', 'bar' => 'foo'), $list->getAttributeList());
    }

    public function testGetAttributeListTextNode() {
        $list = new CM_Dom_NodeList('<div>text</div>');
        foreach ($list->getChildren() as $child) {
            /** @var CM_Dom_NodeList $child */
            $this->assertSame(array(), $child->getAttributeList());
        }
    }

    public function testFind() {
        $list = new CM_Dom_NodeList('
          <div class="foo">
            lorem ipsum dolor
            <div class="bar" id="bar1">
              lorem ipsum
              <div class="bar" id="bar2">
                hello world
               </div>
            </div>
          </div>
          <div class="foo">lorem</div>
        ');
        $this->assertSame(0, $list->find('.foo')->count());
        $this->assertSame(2, $list->find('.bar')->count());
        $this->assertSame(1, $list->find('#bar1 .bar')->count());
        $this->assertSame(1, $list->find('#bar2')->count());
        $this->assertSame(1, $list->find('> .bar')->count());
        $this->assertSame(1, $list->find('.bar > .bar')->count());
    }

    public function testFindChained() {
        $list = new CM_Dom_NodeList('
          <div class="foo">
            lorem ipsum dolor
            <div class="bar">
              lorem ipsum
              <div class="bar">
                hello
               </div>
               <div class="bar">
                world
               </div>
            </div>
          </div>
          <div class="foo">lorem</div>
        ');
        $this->assertSame(0, $list->find('.foo')->count());
        $this->assertSame(2, $list->find('> .bar')->find('> .bar')->count());
    }

    public function testGetChildren() {
        $expected = array('lorem ipsum dolor', 'lorem ipsum', 'test', '');
        $list = new CM_Dom_NodeList('<div><span foo="bar">lorem ipsum dolor</span><p foo="foo">lorem ipsum</p><span>test</span><a></a></div>');
        $children = $list->getChildren();

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

    public function testGetChildrenTextNode() {
        $list = new CM_Dom_NodeList('<div>text</div>');
        foreach ($list->find('div')->getChildren() as $child) {
            /** @var CM_Dom_NodeList $child */
            $this->assertSame(0, $child->getChildren()->count());
        }
    }

    public function testGetChildrenFilterType() {
        $list = new CM_Dom_NodeList('<div><b>mega</b><i>cool</i>hello</div>');

        $childrenText = $list->getChildren(XML_TEXT_NODE);
        $this->assertSame(1, $childrenText->count());
        $this->assertSame('hello', $childrenText->getText());

        $childrenElement = $list->getChildren(XML_ELEMENT_NODE);
        $this->assertSame(2, $childrenElement->count());
        $this->assertSame('megacool', $childrenElement->getText());
    }

    public function testHas() {
        $list = new CM_Dom_NodeList('<div class="outer"><div foo="bar" bar="foo"></div></div>');
        $this->assertTrue($list->has('div'));
        $this->assertFalse($list->has('foo'));
    }

    public function testCount() {
        $list = new CM_Dom_NodeList('
            <div>
                <span foo="bar">lorem ipsum dolor</span>
                <div foo="foo">lorem ipsum</div>
                <span>test</span>
                <a></a>
            </div>
        ');
        $this->assertCount(1, $list);
        $this->assertCount(1, $list->find('div'));
        $this->assertCount(2, $list->find('span'));
        $this->assertCount(0, $list->find('p'));
        $this->assertInstanceOf('Countable', $list);
        $this->assertSame(1, count($list->find('div')));
    }

    public function testArrayAccess() {
        $el1 = new DOMElement('span', 1);
        $el2 = new DOMElement('span', 2);
        $el3 = new DOMElement('span', 3);
        $el4 = new DOMElement('span', 4);
        $el5 = new DOMElement('span', 5);
        $domNodeList = new CM_Dom_NodeList([$el1, $el2, $el3]);

        // offsetGet()
        $this->assertInstanceOf('CM_Dom_NodeList', $domNodeList[1]);
        $this->assertSame($el2->textContent, $domNodeList[1]->getText());
        try {
            $domNodeList[100];
        } catch (ErrorException $ex) {
            $this->assertContains('Undefined offset: 100', $ex->getMessage());
        }

        // offsetExists()
        $this->assertTrue(empty($domNodeList[100]));
        $this->assertFalse(empty($domNodeList[2]));
        $this->assertFalse(isset($domNodeList[100]));
        $this->assertTrue(isset($domNodeList[2]));

        // offsetSet()
        $domNodeList[] = $el4;
        $this->assertSame($el4->textContent, $domNodeList[3]->getText());
        $domNodeList[9] = $el5;
        $this->assertSame($el5->textContent, $domNodeList[9]->getText());
        try {
            $domNodeList[] = 'lol';
        } catch (CM_Exception_Invalid $ex) {
            $this->assertContains('Element is not an instance of `DOMNode`', $ex->getMessage());
        }

        // offsetUnset
        $this->assertTrue(isset($domNodeList[0]));
        unset($domNodeList[0]);
        $this->assertFalse(isset($domNodeList[0]));
    }

    public function testFindDirectChild() {
        $list = new CM_Dom_NodeList('<div><div class="bar"></div></div>');
        $this->assertTrue($list->has('> *'));
    }

    public function testFindDescendantChild() {
        $list = new CM_Dom_NodeList('<div class="foo"><div class="bar"></div></div>');
        $this->assertTrue($list->has('.bar'));
    }
}
